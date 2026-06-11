<?php

use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Flags\Event\Created;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use VivalAvita\BbsAntispam\Access\ScopeAutoSuspendFlagVisibility;
use VivalAvita\BbsAntispam\Access\ScopeViewPrivateDiscussions;
use VivalAvita\BbsAntispam\Command\UnsuspendCommand;
use VivalAvita\BbsAntispam\Controller\PrivatesController;
use VivalAvita\BbsAntispam\Listener\AutoSuspendSpammer;

return [
    (new Extend\Event())
        ->listen(Created::class, AutoSuspendSpammer::class),

    (new Extend\Console())
        ->command(UnsuspendCommand::class),

    // autoSuspend 通知仅管理员可见，版主不可见
    (new Extend\ModelVisibility(Flag::class))
        ->scope(ScopeAutoSuspendFlagVisibility::class),

    // 「查看他人私信」权限 user.viewPrivateDiscussions：
    // 有此权限者（管理员自动拥有）可见所有 byobu 私信讨论及其帖子。
    (new Extend\ModelVisibility(Discussion::class))
        ->scope(ScopeViewPrivateDiscussions::class, 'viewPrivate'),
    (new Extend\ModelVisibility(Post::class))
        ->scope(ScopeViewPrivateDiscussions::class, 'viewPrivate'),

    // GET /privates —— 「全部私密主题」列表页，仅有 user.viewPrivateDiscussions 权限者可访问
    (new Extend\Routes('forum'))
        ->get('/privates', 'bbs-antispam.privates', PrivatesController::class),
];
