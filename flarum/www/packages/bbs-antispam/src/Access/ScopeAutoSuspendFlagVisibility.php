<?php

namespace VivalAvita\BbsAntispam\Access;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class ScopeAutoSuspendFlagVisibility
{
    /**
     * autoSuspend 类型的通知仅管理员可见，版主和普通用户不可见。
     */
    public function __invoke(User $actor, Builder $query): void
    {
        if (!$actor->isAdmin()) {
            $query->where('flags.type', '!=', 'autoSuspend');
        }
    }
}
