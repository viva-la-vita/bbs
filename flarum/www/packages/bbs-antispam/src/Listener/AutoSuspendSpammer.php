<?php

namespace VivalAvita\BbsAntispam\Listener;

use Carbon\Carbon;
use Flarum\Flags\Event\Created;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\DatabaseManager;

class AutoSuspendSpammer
{
    // 被举报账号注册不足多少天才触发
    const ACCOUNT_AGE_DAYS = 90;

    // 累计多少个不同举报人触发封号
    const FLAG_THRESHOLD = 3;

    // 举报人自身须注册满多少天（防马甲）
    const FLAGGER_MIN_AGE_DAYS = 7;

    protected DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    public function handle(Created $event): void
    {
        $flag = $event->flag;
        $flagger = $event->actor;

        // 只处理用户手动举报，跳过 autoMod 等系统自动举报
        if ($flag->type !== 'user') {
            return;
        }

        // 举报人自身须注册满 FLAGGER_MIN_AGE_DAYS 天
        if ($flagger->created_at->diffInDays(Carbon::now()) < self::FLAGGER_MIN_AGE_DAYS) {
            return;
        }

        // 获取被举报帖子及其作者
        $post = Post::find($flag->post_id);
        if (!$post || !$post->user_id) {
            return;
        }

        $target = User::find($post->user_id);
        if (!$target) {
            return;
        }

        // 跳过管理员
        if ($target->isAdmin()) {
            return;
        }

        // 被举报账号须注册不足 ACCOUNT_AGE_DAYS 天
        if ($target->created_at->diffInDays(Carbon::now()) >= self::ACCOUNT_AGE_DAYS) {
            return;
        }

        // 统计对该用户的帖子举报过的不同用户数（举报人须注册满 FLAGGER_MIN_AGE_DAYS 天）
        $distinctFlaggers = $this->db->table('flags')
            ->join('posts', 'flags.post_id', '=', 'posts.id')
            ->join('users as flaggers', 'flags.user_id', '=', 'flaggers.id')
            ->where('posts.user_id', $target->id)
            ->where('flags.type', 'user')
            ->where('flaggers.created_at', '<=', Carbon::now()->subDays(self::FLAGGER_MIN_AGE_DAYS))
            ->distinct()
            ->count('flags.user_id');

        if ($distinctFlaggers < self::FLAG_THRESHOLD) {
            return;
        }

        $accountAgeDays = $target->created_at->diffInDays(Carbon::now());
        $alreadySuspended = $target->suspended_until && $target->suspended_until->isFuture();

        // 隐藏该用户的所有帖子（无论是否已被暂停，帖子可能仍公开）
        $this->db->table('posts')
            ->where('user_id', $target->id)
            ->whereNull('hidden_at')
            ->update(['hidden_at' => Carbon::now()]);

        // 隐藏该用户发起的所有主题
        $this->db->table('discussions')
            ->where('user_id', $target->id)
            ->whereNull('hidden_at')
            ->update(['hidden_at' => Carbon::now()]);

        // 若尚未暂停，则暂停账号并通知管理员
        if (!$alreadySuspended) {
            $target->suspended_until = Carbon::now()->addYears(10);
            $target->suspend_reason = 'auto';
            $target->suspend_message = '您的账号因短时间内被多位用户举报，已被自动暂停。如有疑问，请联系管理员申诉。';
            $target->save();

            // 在 Flags 队列中创建一条管理员通知
            Flag::unguard();
            $adminFlag = new Flag();
            $adminFlag->post_id = $post->id;
            $adminFlag->type = 'autoSuspend';
            $adminFlag->reason_detail = "【自动封号】@{$target->username} 注册 {$accountAgeDays} 天，被 {$distinctFlaggers} 位用户举报，已自动暂停并隐藏其所有内容。如判断为误封，请运行：php flarum antispam:unsuspend {$target->username}";
            $adminFlag->created_at = Carbon::now();
            $adminFlag->save();
        }
    }
}
