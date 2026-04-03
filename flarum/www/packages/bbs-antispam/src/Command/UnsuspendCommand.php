<?php

namespace VivalAvita\BbsAntispam\Command;

use Flarum\Console\AbstractCommand;
use Flarum\User\User;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Console\Input\InputArgument;

class UnsuspendCommand extends AbstractCommand
{
    protected DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('antispam:unsuspend')
            ->setDescription('解除自动封号，并恢复该用户所有帖子和主题的可见性')
            ->addArgument('username', InputArgument::REQUIRED, '要解封的用户名');
    }

    protected function fire(): void
    {
        $username = $this->input->getArgument('username');

        $user = User::where('username', $username)->first();

        if (!$user) {
            $this->error("找不到用户：{$username}");
            return;
        }

        // 解除暂停
        $user->suspended_until = null;
        $user->suspend_reason = null;
        $user->suspend_message = null;
        $user->save();

        // 恢复该用户所有帖子可见性
        $postsRestored = $this->db->table('posts')
            ->where('user_id', $user->id)
            ->whereNotNull('hidden_at')
            ->update(['hidden_at' => null]);

        // 恢复该用户所有主题可见性
        $discussionsRestored = $this->db->table('discussions')
            ->where('user_id', $user->id)
            ->whereNotNull('hidden_at')
            ->update(['hidden_at' => null]);

        $this->info("已解封用户 @{$username}");
        $this->info("恢复帖子：{$postsRestored} 条，主题：{$discussionsRestored} 条");
    }
}
