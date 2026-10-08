<?php

namespace VivalAvita\BbsReadstate;

use Flarum\Discussion\Command\ReadDiscussion;
use Flarum\Discussion\Command\ReadDiscussionHandler as CoreHandler;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Discussion\Event\UserDataSaving;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class ReadDiscussionHandler extends CoreHandler
{
    /**
     * @var ConnectionInterface
     */
    protected $db;

    public function __construct(Dispatcher $events, DiscussionRepository $discussions, ConnectionInterface $db)
    {
        parent::__construct($events, $discussions);
        $this->db = $db;
    }

    public function handle(ReadDiscussion $command)
    {
        $actor = $command->actor;

        $actor->assertRegistered();

        $discussion = $this->discussions->findOrFail($command->discussionId, $actor);

        $state = $discussion->stateFor($actor);
        $state->read($command->lastReadPostNumber);

        $this->events->dispatch(
            new UserDataSaving($state)
        );

        if (! $state->exists) {
            // getAttributes() 而非固定列清单，保留 UserDataSaving 监听器写入的字段
            $attributes = $state->getAttributes();

            // 原子写入：并发请求同时标记已读时，只有一个能插入成功，
            // 其余落入 update 分支，避免 discussion_user 主键冲突导致 500。
            $affected = $this->db->table('discussion_user')->insertOrIgnore($attributes);

            if ($affected === 0) {
                $this->db->table('discussion_user')
                    ->where('discussion_id', $state->discussion_id)
                    ->where('user_id', $state->user_id)
                    ->where(function ($query) use ($state) {
                        $query->whereNull('last_read_post_number')
                            ->orWhere('last_read_post_number', '<', $state->last_read_post_number ?? 0);
                    })
                    ->update([
                        'last_read_post_number' => $state->last_read_post_number,
                        'last_read_at' => $state->last_read_at,
                    ]);

                $state->exists = true;
                $state->syncOriginal();
            } else {
                $state->exists = true;
                $state->wasRecentlyCreated = true;
                $state->syncOriginal();
            }
        } else {
            $state->save();
        }

        $this->dispatchEventsFor($state);

        return $state;
    }
}
