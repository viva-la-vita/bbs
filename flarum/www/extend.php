<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Extend;
use VivalAvita\BbsFilter\Provider\FilterPatchServiceProvider;

return [
    // 无条件加载 fof/filter 补丁（不依赖后台启用 bbs-filter 扩展）：
    // 修复敏感词正则生成 bug + 人工审核通过后不再被机器打回。
    (new Extend\ServiceProvider())
        ->register(FilterPatchServiceProvider::class),
];
