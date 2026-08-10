<?php

namespace VivalAvita\BbsFilter\Listener;

use Flarum\Post\Event\Saving;
use FoF\Filter\Listener\CheckPost;

/**
 * Replaces FoF\Filter\Listener\CheckPost via IoC binding.
 *
 * Fixes two bugs in CensorGenerator::generateCensors():
 * 1. User-supplied regex patterns like /pattern/flags were double-wrapped,
 *    producing invalid regexes and silently disabling the entire filter.
 * 2. Plain-text words containing '/' (e.g. URLs) were not escaped,
 *    also producing invalid regexes.
 *
 * Additionally implements "人审终局"：人工审核通过的保存操作跳过敏感词检查。
 */
class PatchedCheckPost extends CheckPost
{
    /**
     * 本次保存是「审核通过」操作（data 中带 isApproved: true）时直接放行。
     *
     * 否则管理员/版主点击审核通过会触发一次帖子保存，CheckPost 重新匹配到
     * 敏感词又把帖子打回待审核——机器判断不应推翻人工审核的结论。
     */
    public function handle(Saving $event): void
    {
        $attributes = $event->data['attributes'] ?? [];

        if (!empty($attributes['isApproved'])) {
            // 人审终局：人工审核通过的帖子打 auto_mod 标记，
            // 今后对该帖子的任何保存（如作者编辑）都不再重新过滤，
            // 避免「审核通过 → 再次保存 → 又被打回待审核」循环。
            $event->post->auto_mod = true;
            return;
        }

        parent::handle($event);
    }
    private const LEET_REPLACE = [
        'a' => '(a|a\.|a\-|4|@|Á|á|À|Â|à|Â|â|Ä|ä|Ã|ã|Å|å|α|Δ|Λ|λ)',
        'b' => '(b|b\.|b\-|8|\|3|ß|Β|β)',
        'c' => '(c|c\.|c\-|Ç|ç|¢|€|<|\(|{|©)',
        'd' => '(d|d\.|d\-|&part;|\|\)|Þ|þ|Ð|ð)',
        'e' => '(e|e\.|e\-|3|€|È|è|É|é|Ê|ê|∑)',
        'f' => '(f|f\.|f\-|ƒ)',
        'g' => '(g|g\.|g\-|6|9)',
        'h' => '(h|h\.|h\-|Η)',
        'i' => '(i|i\.|i\-|!|\||\]\[|]|1|∫|Ì|Í|Î|Ï|ì|í|î|ï)',
        'j' => '(j|j\.|j\-)',
        'k' => '(k|k\.|k\-|Κ|κ)',
        'l' => '(l|1\.|l\-|!|\||\]\[|]|£|∫|Ì|Í|Î|Ï)',
        'm' => '(m|m\.|m\-)',
        'n' => '(n|n\.|n\-|η|Ν|Π)',
        'o' => '(o|o\.|o\-|0|Ο|ο|Φ|¤|°|ø)',
        'p' => '(p|p\.|p\-|ρ|Ρ|¶|þ)',
        'q' => '(q|q\.|q\-)',
        'r' => '(r|r\.|r\-|®)',
        's' => '(s|s\.|s\-|5|\$|§)',
        't' => '(t|t\.|t\-|Τ|τ|7)',
        'u' => '(u|u\.|u\-|υ|µ)',
        'v' => '(v|v\.|v\-|υ|ν)',
        'w' => '(w|w\.|w\-|ω|ψ|Ψ)',
        'x' => '(x|x\.|x\-|Χ|χ)',
        'y' => '(y|y\.|y\-|¥|γ|ÿ|ý|Ÿ|Ý)',
        'z' => '(z|z\.|z\-|Ζ)',
    ];

    protected function getCensors(): array
    {
        $wordsList = (string) $this->settings->get('fof-filter.words', '');

        return $this->generateFixedCensors($wordsList);
    }

    private function generateFixedCensors(string $wordsList): array
    {
        $badwords = explode("\n", trim($wordsList));
        $filteredBadwords = array_filter(array_map('trim', $badwords));

        $censors = [];

        foreach ($filteredBadwords as $word) {
            // If the entry is already a regex pattern (/pattern/flags), use it as-is.
            if (preg_match('/^\/.*\/[a-zA-Z]*$/', $word)) {
                if (@preg_match($word, '') !== false) {
                    $censors[] = $word;
                }
                // Silently skip invalid user-supplied patterns instead of
                // crashing the entire filter.
                continue;
            }

            // Plain-text word: escape special regex chars first (including '/'),
            // then apply leet substitution on the letters.
            $escaped = preg_quote($word, '/');
            $pattern = str_ireplace(
                array_keys(self::LEET_REPLACE),
                array_values(self::LEET_REPLACE),
                $escaped
            );
            $regex = '/' . $pattern . '/iu';

            if (@preg_match($regex, '') !== false) {
                $censors[] = $regex;
            }
        }

        return $censors;
    }
}
