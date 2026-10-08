# 2026-10-08 网站性能与错误诊断记录

## 一、小黑屋（/api/flags）500 问题 → 已定位，修复代码已就绪

### 现象

管理员点击主页"小黑屋"（flarum/flags 的举报列表）持续加载，最后弹窗"糟糕，出错啦！请刷新页面重试"。浏览器 Network 面板确认 `GET /api/flags` 返回 500。

### 直接原因

Flarum 错误日志中，**当天全部错误为同一类**（`flarum/www` 服务器 `storage/logs/flarum-2026-10-08.log`）：

```
Illuminate\Database\QueryException: SQLSTATE[23000]: Integrity constraint violation:
1062 Duplicate entry '<user_id>-<discussion_id>' for key 'discussion_user.PRIMARY'
```

发生在 `Flarum\Discussion\Command\ReadDiscussionHandler.php:56`（标记帖子已读时）。

### 根因

Core 的写入逻辑是"先 SELECT 有没有已读状态行，没有则 INSERT"的两步非原子操作。同一用户两个并发请求（两个标签页、前端几乎同时的 PATCH）都查不到、都去 INSERT，后到者撞主键 → 500。

### 频率（慢性问题）

最近一周每天 100~200 条堆栈（2026-10-02: 183，10-03: 165，10-04: 211，10-05: 131，10-06: 109，10-07: 180，10-08: 70），长期影响真实用户的"标记已读"操作。

### 修复（已写好，待部署）

新增本地扩展 `viva-la-vita/bbs-readstate`（`flarum/www/packages/bbs-readstate/`）：

- `src/ReadDiscussionHandler.php`：继承 core handler，覆盖 `handle()`。"行不存在"时用 `INSERT IGNORE`（`insertOrIgnore`）原子写入；插入被忽略（并发者已插入）时退化为带条件的 `UPDATE`（`last_read_post_number` 只增不减），不再 500。
- `src/ServiceProvider.php`：容器绑定覆盖 core 的 `ReadDiscussionHandler`。
- 已在 `flarum/www/composer.json` 注册依赖。

### 部署步骤（DEPLOY.md 流程 + 关键一步）

```bash
cd ~/bbs
docker compose pull flarum
docker stop bbs-flarum-1 && docker rm bbs-flarum-1
docker compose up -d
# 新扩展默认禁用，必须手动启用（勿漏）：
docker exec -u www-data bbs-flarum-1 php flarum extension:enable viva-la-vita/bbs-readstate
# 次日验证：
docker exec bbs-flarum-1 grep -c "Duplicate entry" storage/logs/<次日日志>
```

### 遗留问题

`/api/flags` 500 与上述错误的关系未完全闭环（当天日志中只有这一类错误，但 GET /api/flags 本身不触发 ReadDiscussion）。部署修复后需复测小黑屋。

---

## 二、vCPU 过高诊断 → 结论与治理方案

服务器：24G 内存，负载长期 8~11，高峰 vCPU 超 100%。

### 资源分布（docker stats 采样）

| 容器 | CPU | 内存 |
|---|---|---|
| bbs-flarum (php-fpm) | 440%（≈4.4 核） | 1.9% |
| **bbs-mysql** | **236%（≈2.4 核）** | **82.8%（≈20G/24G，已用 1.9G swap）** |
| 其余（nginx、strapi 等） | 可忽略 | 可忽略 |

php-fpm 池配置（`flarum/php/zzz-flarum.conf`）：max_children=40，max_requests=500。

MySQL 累计：58 天 uptime，Com_select 45.4 亿（≈900 次/秒），连接 ID 已到 7099 万。

### 排查过程中的关键发现

1. **PID 2295 是 mysqld**（LXC 内 docker，属 lxd 用户），CPU 135%、RES 16.6G。
2. MySQL 正在反复执行的巨型 SQL 是 **Flarum 全文搜索**（`MATCH(posts.content) AGAINST (...)` UNION 标题搜索 + 几十层可见性权限子查询），同一个词 "stella" 反复以 limit 4 / 21 出现——搜索框"边输边搜"模式。单次查询几百毫秒、吃一核。
3. **ExaSearchBot（Exa AI 爬虫）占全站流量约 10%**（4776/50000 行），主要在直接打 `/api/posts` API——恶意爬虫浪费。
4. 搜索请求（1547 次窗口内）IP 高度分散、UA 全是正常浏览器 → **真人用户**，非攻击。
5. 错误日志只有无害 404（缺头像、缺 .well-known/assetlinks.json）。
6. **nginx access.log 是指向 /dev/stdout 的软链**，日志全部由 Docker json-file 收集，**单容器日志文件达 80G**——这就是 `docker logs` / `docker exec` 全部"执行过久"卡死的真正原因（读 /dev/stdout 永不返回）。
7. 磁盘 469G 用 59%（183G 可用），暂无危险。

### 治理方案（按优先级）

**P0 — 服务器操作（不改代码）：**

1. 封禁 ExaSearchBot：nginx 按 UA 拦截（`ExaSearchBot`）+ robots.txt 声明。
2. MySQL 加 `my.cnf` 显式限制内存（`innodb_buffer_pool_size`、`max_connections`、`tmp_table_size`），重启 mysql 容器（约 1 分钟停机）。原因：compose 中 mysql 服务无任何自定义配置，16.6G RES 更像被巨型搜索查询的内存临时表/连接缓冲"喂"出来的，需戴笼头。
   - 待验证：`SELECT @@innodb_buffer_pool_size, @@max_connections, @@tmp_table_size;` 与 `sys.memory_global_total`（当时未跑通）。
3. Docker 日志轮转：`/etc/docker/daemon.json` 设 `log-opts: {max-size: 100m, max-file: 3}`，清空现有 80G 日志（`: > $LOG`）。

**P1 — 代码改动：**

4. 搜索限流：对带 `filter[q]` 的请求按 IP 限速（每 IP ~1 次/秒）；~~前端加大搜索防抖间隔~~ ✅ 已完成（见下文"前端防抖改动"）。
5. 可选：补 `.well-known/assetlinks.json`。

### 前端防抖改动（2026-10-08 完成）

- 修改：`flarum/www/packages/bbs-frontend/js/src/forum/index.ts`
- 内容：`Search.SEARCH_DEBOUNCE_TIME_MS` 250ms → 600ms（core 定义见 `vendor/flarum/core/js/src/forum/components/Search.tsx:65`，`MIN_SEARCH_LEN = 3` 保持不变）。
- 实现写法：`(Search as any).SEARCH_DEBOUNCE_TIME_MS = 600;`
- **关于 `as any` 的风险评估结论（团队已确认保留此写法）**：
  - 该属性在 core 中为 `protected`，直接赋值会导致 TS 编译失败（构建挂掉），必须 cast。
  - `as any` 仅存在于编译期，编译后 JS 中完全消失，**无任何运行时风险**（JS 本无 protected 概念）。
  - 唯一隐患：未来 core 升级若重命名/删除该属性，赋值静默失效（防抖回退 250ms，不报错）；代码注释已标明所改属性，届时可直接发现。
  - 替代方案评估：子类继承改不到 core 页面头部的搜索框（无法全局生效）；不加 cast 则构建失败。此为 Flarum 社区覆盖 core 静态属性的标准做法。

### 备注

- 问题一（discussion_user 主键冲突）的修复不会降低 CPU，纯错误消除。
- 真人搜索是高流量下的结构性成本：P0 完成后 CPU 应明显回落，P1 提供进一步保护。
