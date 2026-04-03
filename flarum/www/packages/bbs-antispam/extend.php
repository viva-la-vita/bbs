<?php

use Flarum\Extend;
use Flarum\Flags\Event\Created;
use Flarum\Flags\Flag;
use VivalAvita\BbsAntispam\Access\ScopeAutoSuspendFlagVisibility;
use VivalAvita\BbsAntispam\Command\UnsuspendCommand;
use VivalAvita\BbsAntispam\Listener\AutoSuspendSpammer;

return [
    (new Extend\Event())
        ->listen(Created::class, AutoSuspendSpammer::class),

    (new Extend\Console())
        ->command(UnsuspendCommand::class),

    // autoSuspend 通知仅管理员可见，版主不可见
    (new Extend\ModelVisibility(Flag::class))
        ->scope(ScopeAutoSuspendFlagVisibility::class),
];
