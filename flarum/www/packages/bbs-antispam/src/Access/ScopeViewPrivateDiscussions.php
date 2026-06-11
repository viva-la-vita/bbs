<?php

namespace VivalAvita\BbsAntispam\Access;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 「查看他人私信」权限。
 *
 * fof/byobu 的私信（is_private=1 的讨论及其帖子）默认只对参与者可见，
 * 连管理员走普通的 findOrFail / 讨论页 / API 这条路径都看不到（404）。
 * byobu 没有给管理员开后门（它的 RecipientsConstraint 用 hasPermission 检查，
 * 但只覆盖"被举报的私信"）。
 *
 * 这个 scoper 追加进核心的 viewPrivate 可见性桶（Discussion 和 Post 都挂）：
 * 只要 actor 拥有 user.viewPrivateDiscussions 权限，就放行**所有**私信。
 *
 * - 管理员：User::hasPermission() 对 isAdmin 直接返回 true → 自动拥有，立即生效。
 * - 其他用户组：在 group_permission 表加一行 user.viewPrivateDiscussions 即可授予。
 *
 * 原理：核心 ScopeDiscussionVisibility / ScopePostVisibility 的结构是
 *   WHERE (is_private = false OR <viewPrivate 子查询>)
 * 我们往 viewPrivate 子查询里 orWhere 一个恒真条件，使有权限者的该子查询恒成立，
 * 从而私信也落入可见范围。无权限者不加任何条件，行为完全不变。
 *
 * 不修改 byobu 源码（避免重建镜像时 composer update 覆盖），随本地包走、持久生效。
 */
class ScopeViewPrivateDiscussions
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->hasPermission('user.viewPrivateDiscussions')) {
            $query->orWhereRaw('1 = 1');
        }
    }
}
