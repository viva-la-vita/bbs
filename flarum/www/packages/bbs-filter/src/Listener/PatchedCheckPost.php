<?php

namespace VivalAvita\BbsFilter\Listener;

use FoF\Filter\Listener\CheckPost;

/**
 * Replaces FoF\Filter\Listener\CheckPost via IoC binding.
 *
 * Fixes two bugs in CensorGenerator::generateCensors():
 * 1. User-supplied regex patterns like /pattern/flags were double-wrapped,
 *    producing invalid regexes and silently disabling the entire filter.
 * 2. Plain-text words containing '/' (e.g. URLs) were not escaped,
 *    also producing invalid regexes.
 */
class PatchedCheckPost extends CheckPost
{
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
