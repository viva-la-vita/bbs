<?php

namespace VivalAvita\BbsRecommend;

use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use VivalAvita\BbsRecommend\Command\RebuildCommand;
use VivalAvita\BbsRecommend\Provider\SortmapProvider;

return [
    // 「推荐」按钮文案（按钮本身由 bbs-frontend 的 availableSorts 渲染，
    //  文案走翻译键 core.forum.index_sort.recommend_button）
    (new Extend\Locales(__DIR__.'/resources/locale')),

    // recommend:rebuild 命令
    (new Extend\Console())
        ->command(RebuildCommand::class),

    // 注册 recommend 排序名 → -recommendScore（Str::snake → recommend_score）
    (new Extend\ServiceProvider())
        ->register(SortmapProvider::class),

    // 声明 recommendScore 为可排序字段，否则 sort=recommend 会被拒
    (new Extend\ApiController(ListDiscussionsController::class))
        ->addSortField('recommendScore'),

    // 新列的类型转换
    (new Extend\Model(Discussion::class))
        ->cast('recommend_score', 'integer')
        ->cast('admin_weight', 'integer')
        ->cast('total_likes', 'integer')
        ->cast('total_chars', 'integer')
        ->cast('avg_post_chars', 'integer')
        ->cast('follow_count', 'integer'),
];
