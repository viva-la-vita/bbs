<?php

namespace VivalAvita\BbsRecommend\Command;

use Flarum\Console\AbstractCommand;
use Illuminate\Database\DatabaseManager;

/**
 * php flarum recommend:rebuild
 *
 * 全量重算每个主题的 recommend_score。
 *
 * P0：只算 精华 + 高赞 + 高回复 三项，先跑通链路。
 * P2 再补：总字数 / 每层楼均长 / 收藏 等质量信号。
 *
 * 公式（P0）：
 *   recommend_score = W_FRONT   * frontpage(精华)
 *                   + W_REPLIES * log(1 + 回复数)
 *   再叠加 admin_weight（管理员推/限流）× W_ADMIN
 * （点赞 total_likes 在 P2 聚合后再纳入）
 *
 * 注：当前权重写死为常量，P4 再做成 admin 可调设置。
 */
class RebuildCommand extends AbstractCommand
{
    // P0 权重（暂定，上线后看真实数据调）
    const W_FRONT   = 50;   // 精华帖一次性加成
    const W_LIKES   = 10;   // 每点赞（取 log 后）
    const W_REPLIES = 8;    // 每回复（取 log 后）
    const W_ADMIN   = 100;  // 管理员每档推/限流的等效分

    protected DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('recommend:rebuild')
            ->setDescription('全量重算所有主题的推荐分 recommend_score');
    }

    protected function fire(): void
    {
        $start = microtime(true);

        // 是否安装了 fof/frontpage（精华）。列名为 frontpage（布尔）。列不存在则视为 0。
        $hasFrontpage = $this->db->getSchemaBuilder()->hasColumn('discussions', 'frontpage');

        $frontExpr = $hasFrontpage
            ? '(CASE WHEN frontpage = 1 THEN '.self::W_FRONT.' ELSE 0 END)'
            : '0';

        // 一条 UPDATE 全量重算：用 discussions 现成的聚合列，避免扫 posts 大表。
        //   comment_count = 回复数（核心现成）
        //   total_likes   = 这里先不依赖（P0 用 comment_count 近似热度；点赞在 P2 聚合后启用）
        // log 用 LN(1 + x)。
        $sql = "
            UPDATE discussions
            SET recommend_score = ROUND(
                {$frontExpr}
                + ".self::W_REPLIES." * LN(1 + GREATEST(comment_count, 0))
                + ".self::W_ADMIN." * admin_weight
            )
        ";

        $affected = $this->db->connection()->update($sql);

        $secs = round(microtime(true) - $start, 2);
        $this->info("推荐分重算完成：更新 {$affected} 个主题，耗时 {$secs}s");

        if (!$hasFrontpage) {
            $this->info('提示：未检测到 fof/frontpage 的 frontpage 列，精华加成已跳过。');
        }
    }
}
