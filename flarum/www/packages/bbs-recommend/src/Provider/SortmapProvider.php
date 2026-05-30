<?php

namespace VivalAvita\BbsRecommend\Provider;

use Flarum\Foundation\AbstractServiceProvider;

/**
 * 注册「推荐」排序名。
 *
 * sort=recommend 映射到 -recommendScore（降序）。
 * Flarum 用 Str::snake() 把 recommendScore 转成列名 recommend_score。
 *
 * P0：仅按 recommend_score 降序（R 池）。
 * P1：将改为双漏斗交错（T 池时间序 70% + R 池 30%）。
 */
class SortmapProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->extend('flarum.forum.discussions.sortmap', function (array $options) {
            return array_merge($options, [
                'recommend' => '-recommendScore',
            ]);
        });
    }
}
