# 「推荐」首页设计方案（双漏斗版）

> ⚠️ **本方案已于 2026-06-11 终止并废弃，不再实施。** 下方原始设计与实现记录仅作存档。
> 最终结论请见本节「最终决策」。

---

## 最终决策（2026-06-11）：放弃算法推荐，改用「全站置顶 1~2 天」

经过 P0/P1 本地实现并实测后，决定**彻底放弃首页推荐算法这条路线**，相关代码已全部删除（见文末「废弃与删除记录」）。

### 为什么放弃

1. **体量不匹配。** 算法推荐的前提是「内容过载、人工选不过来」。本论坛 ~2 万主题 / ~6 万用户，属中小型，**远没到过载**。读者用现成的「最新 / 精华 / 热门」三个入口 + 板块/标签浏览已基本够用，首页前 20 条的精确排序对核心体验影响很小。
2. **实测观感差。** P1 双漏斗「每 10 条插 3 条推荐」节奏生硬，精华池混入默认列表观感不自然——这不是调参问题，而是**手段对场景过度设计**，边际收益低。
3. **推荐算法 ≠ 激励创作者。** 真正目标是「激励优秀创作者」，而算法只动了「曝光」一环，且**隐形、不透明**：创作者被悄悄排到前面也感知不到，拿不到正反馈，激励几乎为零。激励真正依赖的闭环是 **曝光 → 反馈(回帖/赞) → 认可(精华/徽章) → 声誉积累**，算法对后三环毫无帮助。

### 替代方案：好帖直接「全站置顶 1~2 天」

遇到优质内容，由管理员**直接全站置顶 1~2 天，到期自动恢复**。它本质是「显式编辑推荐」的极简实现，一举三得：

- **最强曝光**：置顶就是首页最高位，流量远胜算法的隐形加权；
- **公开认可**：全站可见「这帖被论坛推荐了」，对创作者是公开荣誉（解决「隐形→无激励」的根本问题）；
- **零算法、可控、可解释**：纯人工运营，质量可把控，无黑箱。

> **待核查（下一步）**：现有已装扩展 `flarum/sticky` + `the-turk/flarum-stickiest` 很可能已支持「全站置顶 + 定时到期」。**若支持，则此方案零开发**，只需启用/配置。需确认两点：(a) 能否全站置顶（非仅板块内）；(b) 能否设定到期时间自动取消置顶。

### 激励创作者更优先的方向（备忘，按性价比）

1. **显式「编辑推荐 / 加精」标记**（读者可见 + 创作者有荣誉感）——置顶即属此类；
2. **新帖冷启动曝光**（让好内容快速拿到第一波反馈，反馈密度是创作动力的关键）；
3. **轻量声誉体系**（优秀创作者标识 / 等级 / 精华排行榜）；
4. **强化已有精华机制**（`fof/frontpage` 已装，做足曝光与奖励，比新造算法划算）。

---

状态：~~方案草案 v2（双漏斗），待评审（2026-05-30）~~ → **已废弃（2026-06-11）**

论坛规模（2026-05 备份估算）：主题 ~2 万、帖子 ~13 万、用户 ~6 万。中小型，性能压力极小。

---

## 0. 核心设计思想：两个漏斗

「推荐」首页不是一条复杂排序，而是**两个独立池子（漏斗）各自排好，再简单交错合并**：

```
┌─────────────────────────┐      ┌─────────────────────────┐
│  漏斗 T（时间池，70%）    │      │  漏斗 R（推荐池，30%）    │
│  = 现在的"最新回复"       │      │  = 优质内容              │
│  ORDER BY last_posted_at │      │  ORDER BY recommend_score│
│  完全复用现有逻辑，不改   │      │  分由后台 rebuild 预计算  │
└───────────┬─────────────┘      └───────────┬─────────────┘
            │                                 │
            └──────────────┬──────────────────┘
                           ▼
              按位置模板交错合并（§4）
                每 10 条 ≈ 7 条 T + 3 条 R
                合并时记 seen 集合，撞到重复就跳过
                           ▼
                    「推荐」首页列表
```

两个漏斗**各自独立**：T 池零风险复用现有时间序；R 池的算法可以慢慢迭代，不影响 T。改 70/30 比例 = 改交错模板，一行配置。

> 关于"同一帖可能同时进两池"：合并时用一个 `seen` 集合，已放过的 ID 再出现就跳过，下一条顶上。简单，不单独处理。

---

## 1. 整体架构：全部封装进一个插件

**所有逻辑封装进单个插件 `viva-la-vita/bbs-recommend`**（2026-05-30 用户确认），与现有 `bbs-antispam` / `bbs-filter` 同构。改算法、调参数、改权限都只在这一个插件目录里完成，不碰核心、不碰其他插件。

插件职责：

1. **数据库**：migration 给 `discussions` 加列（见 §2）。
2. **评分命令** `php flarum recommend:rebuild`：全量聚合原始信号 + 算 `recommend_score` 写回，挂 cron。
3. **排序注册**：注册名为 `recommend` 的 sort。接管查询，按 §4 取两池、交错、分页返回。
4. **前端按钮注入**：由本插件向 `IndexPage` 的 `viewItems` 注入"推荐"按钮，**不改 `bbs-frontend`**（让现有前端插件保持不动，更干净）。注入时检测自身启用状态——禁用插件首页即恢复原样。
5. **运营按钮**：帖子 `...` 菜单的推流/限流（管理员/版主）、顶帖（用户）按钮。
6. **权限**：注册标准权限项，后台可勾选给不同用户组（见 §5、§6）。
7. **即时更新**：推/限流、顶帖只改该主题的 `admin_weight` / `bump_at`，立即生效（毫秒级单行 update），不等 cron。
8. **admin 设置面板**：比例、权重、衰减半衰期等参数（见 §8）。

插件结构（参照 `bbs-antispam`）：

```
packages/bbs-recommend/
  composer.json
  extend.php                    # 注册 migration / 命令 / sort / 前端 / 权限 / 设置
  migrations/                   # 加列
  src/
    Command/RebuildCommand.php  # recommend:rebuild
    Search/RecommendFilter.php  # 双漏斗交错 sort
    Listener/...                # 推/限流、顶帖处理
    Access/...                  # 权限策略
  js/src/forum/                 # 注入"推荐"按钮 + 帖子菜单按钮
  js/src/admin/                 # 设置面板
  resources/locale/zh-Hans.yml  # "推荐"等文案（随插件走，进 git）
```

> 「禁用插件 = 首页恢复原样」：因为"推荐"按钮和 sort 都由本插件提供，停用插件后按钮消失、回到 latest/front/top。零残留。

---

## 2. 数据库设计（方案 A：加列，已定 2026-05-30）

**原始信号也落库**（2026-05-30 用户确认）：不只存最终分，把"需要聚合"的原始信号一并缓存进表。好处：调权重时只需重算一行算术（不必每次重扫 13 万帖聚合）、方便观察调试、未来可在帖子上展示标签。

```sql
-- 原始信号（rebuild 时聚合写入并缓存；核心已有的列不重复存）
ALTER TABLE discussions ADD COLUMN total_chars     INT NOT NULL DEFAULT 0;  -- 主题总字数（SUM LENGTH(posts.content)）
ALTER TABLE discussions ADD COLUMN avg_post_chars  INT NOT NULL DEFAULT 0;  -- 每层楼平均字数
ALTER TABLE discussions ADD COLUMN follow_count    INT NOT NULL DEFAULT 0;  -- 收藏数（subscriptions）
ALTER TABLE discussions ADD COLUMN total_likes     INT NOT NULL DEFAULT 0;  -- 总点赞（聚合缓存）

-- 最终结果
ALTER TABLE discussions ADD COLUMN recommend_score INT NOT NULL DEFAULT 0;  -- 由上面信号 × 权重算出（R 池排序用）

-- 运营控制
ALTER TABLE discussions ADD COLUMN admin_weight    INT NOT NULL DEFAULT 0;  -- 管理员推/限流档位（带符号）

ALTER TABLE discussions ADD INDEX idx_recommend_score (recommend_score);
-- 注：bump_at 列已取消（顶帖沿用原生回帖顶，见 §11-c）
```

- 通过 Flarum migration 完成（插件 `migrations/` 目录）。
- `recommend_score` 决定主题在 **R 池**里的排名；`admin_weight` / `bump_at` 进一步微调（§5、§6）。
- T 池排序用现成的 `last_posted_at`，无需新列。
- 表多几列对 2 万行 discussions 可忽略；换来调参时不必反复扫 posts 大表。
- 方案 B（独立表存分项明细做"推荐理由可解释"）暂不采用。

**哪些存、哪些用现成的：**

| 信号 | 来源 | 处理 |
|---|---|---|
| 精华 | `fof/frontpage` | 现成标记，直接读 |
| 回复数 | `discussions.comment_count` | 核心现成列，不重复存 |
| 参与人数 | `discussions.participant_count` | 核心现成列，不重复存 |
| 点赞数 | `flarum/likes` | 聚合缓存到 `total_likes` |
| 总字数 / 每层楼长度 | `posts.content` | 聚合缓存到 `total_chars` / `avg_post_chars` |
| 收藏数 | `flarum/subscriptions` | 聚合缓存到 `follow_count` |
| 时间 | `discussions.last_posted_at` | 核心现成列，T 池直接用 |

---

## 3. 推荐池排序分 recommend_score（决定 R 池内排名）

每个主题算一个内容质量分（**不含时间衰减**，旧贴优质也能高分，满足"旧贴但优质也要推"）：

```
recommend_score =
    w_front     * is_frontpage               // 精华帖
  + w_likes     * log(1 + total_likes)       // 高赞
  + w_replies   * log(1 + comment_count)     // 高回复
  + w_people    * log(1 + participant_count) // 参与人数多
  + w_length    * normalize(total_chars)     // 总字数长（高质量）
  + w_avglen    * normalize(avg_post_chars)  // 每层楼回复长（高质量）
  + w_subs      * log(1 + follow_count)      // 收藏多
```

- `log` / 归一化压制极端值，避免单项一家独大。
- 所有 `w_*` 存 admin 设置，上线后看真实数据调。
- `recommend:rebuild` 把这套 SQL 聚合算出来，批量 UPDATE 回 `recommend_score`。

---

## 4. 两池交错合并（核心）

「推荐」排序在后端这样产出一页：

**两个查询，各取所需：**

```sql
-- 漏斗 T（时间池）：就是现在的"最新回复"
SELECT id FROM discussions
WHERE <可见性等基础条件>
ORDER BY last_posted_at DESC;

-- 漏斗 R（推荐池）：优质内容
SELECT id FROM discussions
WHERE <可见性等基础条件>
ORDER BY recommend_score DESC;
```

**位置模板交错（70/30）：**

每 10 个位置里，第 3、6、9 位放 R 池，其余放 T 池：

```
位置:  1  2 [3] 4  5 [6] 7  8 [9] 10  11 12[13]...
来源:  T  T  R  T  T  R  T  T  R  T   T  T  R ...
```

- 每页 20 条 ≈ 14 条 T + 6 条 R。
- **去重**：维护一个 `seen` 集合，某 ID 已放过就跳过，用同池下一条补上。
- **R 池取完**（优质帖有限）：剩余位置全用 T 池填，列表照常铺满。
- **比例可调**：改模板里 R 的密度（`recommend.ratio`），如 20% 就每 5 个放 1 个 R。

**分页（翻页不乱）：**

模板是确定的，第 N 页用了多少 T、多少 R 可直接算出。两种实现：

- **4a（推荐）**：后端在自定义 sort 里按模板取两池对应区间，合并去重后返回该页。前端无感，分页正确。
- **4b（备选）**：把交错后的有序 ID 列表整体算出再切页。实现更直白，主题量两万级内存完全够。

> 倾向 **4a**：每页只取需要的量，开销最小。游标记录"T 已消费到第几条、R 已消费到第几条 + seen 集合"即可。

**轮换防霸榜：** R 池如果总是同几个最高分帖，首页会僵。rebuild 时对高分池做轻度随机/轮换（或对"已长期占据"的帖在 R 池排序里轻度降权），保证隔几小时有新鲜优质内容上来。旧的优质帖也能轮到，不靠时间惩罚。

---

## 5. 管理员推流 / 限流

参考短视频平台：**加权不隐藏**（"不一定完全看不到，稍微排后面就行"）。用 `admin_weight` 档位影响主题在漏斗中的位置：

| 操作 | admin_weight | 在漏斗里的效果 |
|---|---|---|
| 🔼🔼 强推 | +2 | 直接进 R 池且排最前（或 R 池分大幅加成） |
| 🔼 微推 | +1 | 进 R 池靠前 |
| 正常 | 0 | 按算法 |
| 🔽 限流 | −1 | 移出 R 池，且 T 池里靠后 |
| 🔽🔽 沉底 | −2 | T 池里大幅靠后，仍可见可搜，不删除 |

- 具体地：R 池排序用 `recommend_score + admin_weight×大系数`；T 池排序在 `last_posted_at` 上对负 weight 做等效时间回拨。
- **入口**：帖子 `...` 菜单按钮（仿现有 `fof/frontpage` 的"设为精华"）。
- 点击即时写 `admin_weight`，立刻生效（漏斗查询读这一列），无需等 cron。

**权限（可配置，2026-05-30 确认）：** 注册权限项 `recommend.moderate`（推流/限流），通过 Flarum 标准 Permissions 页面授予用户组——**默认仅管理员，可后台勾选开放给版主**。无需改代码。

---

## 6. 顶帖（用户可顶，顶后快速下浮）

**确认需求（2026-05-30）：顶帖要出现在「推荐」首页；用户也能顶（传统 BBS）。**

顶帖 = 临时把主题塞进 **R 池前部**，加成随时间快速衰减（抖音"初始流量包"，顶完比普通帖沉得更快）：

```
bump_boost(now) = bump_initial * exp(-(now - bump_at) / half_life)
```

- 顶帖时写 `bump_at = now`；R 池排序分临时叠加 `bump_boost`。
- `half_life` 取 6~12 小时（比正常帖新鲜度寿命短 → 下浮更快）。
- `bump_boost` 在漏斗查询时按 `bump_at` 现算（只对少量有 `bump_at` 的帖），平滑下浮。

**权限（可配置）：** 权限项 `recommend.bump`，后台可设哪些用户组能顶帖（默认注册用户，可限信任等级/老用户）。

**滥用 / 频率控制（因为用户可顶）：**

- 同人同帖顶帖**冷却**：如 24h 内只能顶 1 次（`recommend.bump_cooldown`）。
- 可选：每人每日顶帖总次数上限。
- 可选：限信任等级 / 仅自己参与的主题（与现有反广告封号体系协同）。
- "快速下浮"本身已抑制刷屏。

> 另注：Flarum 的"回帖"本就会更新 `last_posted_at`，自然在 T 池冒头（传统回帖顶帖已天然存在）。本节是**显式一键顶帖**（不回帖也能顶），二者并存。是否要显式按钮见 §11。

---

## 7. 定时重算

- 命令：`php flarum recommend:rebuild`（全量算 recommend_score；含轮换处理）。
- cron：
  ```
  0 */6 * * *  docker exec bbs-flarum-1 php flarum recommend:rebuild
  ```
- 全量重算预期 1~3 秒（2 万主题 / 13 万帖聚合），保持全量、简单不易错。
- 管理员推/限流、顶帖为即时单行更新，不依赖 cron。

---

## 8. Admin 可调参数（Extend\Settings）

| 设置键 | 含义 | 默认 |
|---|---|---|
| `recommend.ratio` | R 池在交错模板里的占比（%） | 30 |
| `recommend.w_front / w_likes / w_replies / w_people / w_length / w_avglen / w_subs` | 各质量信号权重 | 待定 |
| `recommend.admin_offset_per_step` | admin_weight 每档的加成/回拨量 | 待定 |
| `recommend.bump_initial` | 顶帖初始加成 | 待定 |
| `recommend.bump_half_life` | 顶帖衰减半衰期（小时） | 6~12 |
| `recommend.bump_cooldown` | 同人同帖顶帖冷却（小时） | 24 |
| `recommend.rotation` | R 池轮换强度（防霸榜） | 待定 |

`ratio`/`admin`/`bump`/`rotation` 影响读取时的漏斗行为，多数即时生效；改质量权重需 rebuild 后生效。

---

## 9. 前端改动

1. **"推荐"按钮由本插件注入**（不改 `bbs-frontend`）：插件向 `IndexPage.viewItems` 加 `recommend` 项。
2. 文案：随插件的语言文件 `resources/locale/zh-Hans.yml`（进 git，跟插件部署），key 如 `recommend.button = 推荐`。
3. 帖子 `...` 菜单加：推流/限流按钮（受 `recommend.moderate` 权限）、顶帖按钮（受 `recommend.bump` 权限 + 冷却）。
4. 本阶段不改默认首页。后续设为默认时调 `fof/frontpage` 默认路由 + 按钮顺序。

---

## 10. 分阶段落地

- **P0（链路验证）**：建扩展骨架 + 加列 + `recommend:rebuild`（先算 精华+高赞+高回复）+ 注册 `recommend` 排序（先只输出 R 池：`ORDER BY recommend_score`）+ 前端"推荐"按钮。确认能点、能出榜。
- **P1（双漏斗合并）**：实现 §4 —— T 池 + R 池 + 位置模板交错 + seen 去重 + 分页。这是核心里程碑。
- **P2（质量信号）**：补 总字数 / 每层楼均长 / 收藏，公式完整化 + 轮换防霸榜。
- **P3（推流/限流 + 顶帖）**：admin_weight 按钮 + 即时生效；顶帖按钮 + 衰减下浮 + 冷却。
- **P4（调参）**：admin 设置项齐全 + cron 定时 + 真实数据调权重。

---

## 11. 决策记录与待办

**已定（2026-05-30）：**
1. 数据库 → 方案 A（加列）。
2. 排序架构 → 双漏斗交错合并（非 feed_rank）。
3. 全部封装进单个插件 `viva-la-vita/bbs-recommend`，可一键禁用恢复原样。
4. 原始信号（总字数、每层楼均长、收藏、点赞）也落库缓存。
5. cron 频率 → 6 小时一次。
6. 权限可配置（标准 Permissions 页面）：推/限流默认管理员、可开放给版主；顶帖默认注册用户、可限信任等级。

**已定（续，2026-05-30）：**
- a. 字数信号 → 放 P2（P0 先用 精华+高赞+高回复 跑通链路）。
- b. "推荐"按钮文案 → 随插件的语言文件（进 git）。
- c. 顶帖 → **仅靠"回帖自动顶"（Flarum 默认已有），不做显式顶帖按钮**。
- d. 不加信任等级 / 顶帖限制（用户未要求，保持原样）。

> 因 c：原 §6 的"显式一键顶帖 + bump_at 衰减下浮"**不实现**。顶帖沿用 Flarum 原生回帖更新 `last_posted_at` → 在 T 池（时间池）自然冒头。`bump_at` 列与 §6 衰减逻辑取消，相关 admin 参数（bump_*）一并去掉。

---

## 附录：为什么不用单一 feed_rank 排序

早期方案曾考虑把时间 + 推荐压成一个排序键 `feed_rank` 预计算，请求时一句 `ORDER BY feed_rank`。性能更极致，但：两池法**更直观、两个漏斗能各自独立调**（改比例只动模板、改算法只动 R 池），且在两万主题体量下双查询开销也可忽略。故采用双漏斗。若未来规模暴涨、双查询成为瓶颈，可再回退到 feed_rank 作为优化。

---

## 废弃与删除记录（2026-06-11）

整套推荐算法（P0 榜单 + P1 双漏斗交错）已实现并本地实测，最终决定放弃（理由见文首「最终决策」）。已删除的代码：

**删除：**
- 整个插件目录 `flarum/www/packages/bbs-recommend/`（含 migrations / RebuildCommand / SortmapProvider / RecommendFilterMutator / locale / extend.php）。

**还原：**
- `flarum/www/composer.json`：移除 `viva-la-vita/bbs-recommend` 依赖。
- `flarum/www/packages/bbs-frontend/js/src/forum/changeViewItems.tsx`：还原为推荐功能引入前的版本（`availableSorts` 回到 `['latest','front','top']`，移除 sortMap 注入、文案兜底）。首页恢复原 3 按钮。

**云端遗留（曾随 commit `7db39bc`/`05b79ff` 推送）：** 上述删除需重新构建镜像并部署才能在生产生效。

**本地数据库残留（无害）：** 本地开发库的 `discussions` 表曾迁移加列（`recommend_score`/`admin_weight`/`total_chars`/`avg_post_chars`/`follow_count`/`total_likes`）。删插件后这些列成为孤儿列，不影响功能；生产库若从未跑过该迁移则无残留。如需彻底清理可手动 `ALTER TABLE discussions DROP COLUMN ...`。

**下一步：** 不再投入算法。转向核查 `the-turk/flarum-stickiest` 能否直接满足「全站置顶 + 定时到期」，优先零开发落地。
