<?php

use Flarum\Api\Serializer\BasicDiscussionSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Settings())
        ->serializeToForum('heroButtons', 'hero_buttons'),

    // 给 DiscussionSerializer 补充 canApprove 属性，让前端能判断当前用户能否审核该主题
    (new Extend\ApiSerializer(BasicDiscussionSerializer::class))
        ->attribute('canApprove', function ($serializer, Discussion $discussion) {
            return (bool) $serializer->getActor()->can('approvePosts', $discussion);
        }),
];
