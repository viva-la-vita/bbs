<?php

use Flarum\Database\Migration;

return Migration::addColumns('discussions', [
    // 原始信号（rebuild 时聚合缓存；核心已有的 comment_count/participant_count/last_posted_at 不重复存）
    'total_chars'     => ['integer', 'default' => 0],  // 主题总字数（P2 启用）
    'avg_post_chars'  => ['integer', 'default' => 0],  // 每层楼平均字数（P2 启用）
    'follow_count'    => ['integer', 'default' => 0],  // 收藏数（P2 启用）
    'total_likes'     => ['integer', 'default' => 0],  // 总点赞数

    // 最终结果：R 池排序分
    'recommend_score' => ['integer', 'default' => 0],

    // 运营控制：管理员推流/限流档位（带符号，正=推流 负=限流）
    'admin_weight'    => ['integer', 'default' => 0],
]);
