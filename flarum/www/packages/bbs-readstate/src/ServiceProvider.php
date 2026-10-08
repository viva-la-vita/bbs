<?php

namespace VivalAvita\BbsReadstate;

use Flarum\Discussion\Command\ReadDiscussionHandler as CoreHandler;
use Flarum\Foundation\AbstractServiceProvider;

class ServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        // 用原子写入的 handler 覆盖 core 的 ReadDiscussionHandler，
        // 消除并发标记已读时的 discussion_user 主键冲突。
        $this->container->bind(CoreHandler::class, ReadDiscussionHandler::class);
    }
}
