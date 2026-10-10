# 待办事项（截至 2026-10-08 晚：已部署上线）

> 诊断详情见 [diagnosis-2026-10-08.md](./diagnosis-2026-10-08.md)
> 部署记录：2026-10-08 完成 flarum + nginx 两个镜像的构建与部署，数据库迁移已执行（仅 fof/follow-tags 有两条），网站恢复正常。

## 一、代码改动（✅ 已提交、已构建、已部署）

- [x] `flarum/www/packages/bbs-readstate/` + `composer.json`：修复 `discussion_user` 主键冲突（标记已读 500 / 小黑屋问题）——已在后台启用（BBS Read State Fix）
- [x] `bbs-frontend` 搜索防抖 250ms → 600ms（随 flarum 镜像部署）
- [x] `nginx/conf.d/flarum.conf`：拦截 ExaSearchBot（UA 403）+ robots.txt 声明
- [x] `flarum/Dockerfile`：`composer update -W`；`composer.json` 关闭安全公告拦截（policy.advisories.block=false）——修复 Actions 构建失败

## 二、部署步骤（已完成，留存备查）

> **重要：flarum 和 nginx 是两条独立的部署线。**
> - flarum 镜像 = PHP-FPM 代码；nginx 镜像 = nginx + conf.d/（Dockerfile 里 `COPY . .` 把配置打进镜像）。
> - 只 pull/重建 flarum 容器 → 仅已读 500 修复生效，**ExaSearchBot 拦截不生效**。
> - 只 pull/重建 nginx 容器 → 仅爬虫拦截生效。
> - `nginx/conf.d/flarum.conf` 的改动已入仓库，是源文件；只要将来重建过 nginx 容器就会自动生效，**切勿回撤**。
> - 可分开、按任意顺序部署，互不影响。push 一次 Actions 会同时构建两个镜像（构建不代表部署）。

### 2.1 部署 flarum（问题一修复）

```bash
cd ~/bbs
docker compose pull flarum
docker stop bbs-flarum-1 && docker rm bbs-flarum-1
docker compose up -d
docker exec -u www-data bbs-flarum-1 php flarum extension:enable viva-la-vita/bbs-readstate   # 关键，勿漏
```

### 2.2 部署 nginx（ExaSearchBot 拦截）

```bash
cd ~/bbs
docker compose pull nginx
docker stop bbs-nginx-1 && docker rm bbs-nginx-1
docker compose up -d
```

## 三、验证清单

### 3.1 问题一（已读 500 / 小黑屋）— ⏳ 待验证

- [ ] 管理员账号实测点击"小黑屋"，确认正常加载（不再 500）
- [ ] **明天**日志无新增主键冲突：
  ```bash
  docker exec bbs-flarum-1 grep -c "Duplicate entry" storage/logs/flarum-2026-10-09.log
  ```
  预期：0 或接近 0（对照：修复前每天 100~200 条）
- [x] `flarum migrate` 已执行（2026-10-08，仅 fof/follow-tags 两条迁移）

### 3.2 ExaSearchBot 拦截 — ⏳ 待 curl 验证

- [ ] 模拟爬虫应返回 403：
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" -A "Mozilla/5.0 (compatible; ExaSearchBot/1.0; +https://crawler.exa.ai/)" https://bbs.viva-la-vita.org/api/posts
  ```
- [ ] 正常 UA 返回 200：
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" -A "Mozilla/5.0" https://bbs.viva-la-vita.org/
  ```
- [ ] robots.txt 内容正确：
  ```bash
  curl -s https://bbs.viva-la-vita.org/robots.txt
  ```
- [ ] 观察 30 分钟：日志中 ExaSearchBot 请求全部变为 403，总请求量下降（修复前约占 10%）

### 3.3 搜索防抖 — ⏳ 待浏览器验证

- [ ] F12 Network：搜索框慢慢打字，请求防抖约 600ms（原 250ms）
- [ ] 快速回归：投票帖、附件帖、第三方登录（本次批量升级了 fof/polls、fof/upload、fof/oauth）

## 四、P0 剩余项（服务器操作，未做）

- [ ] **MySQL 内存治理**：先跑验证 SQL 确认 20G 内存来源，再挂 `my.cnf` 限制（`innodb_buffer_pool_size` / `max_connections` / `tmp_table_size`），重启 mysql 容器（约 1 分钟停机）。需选低峰期。
  ```bash
  docker exec bbs-mysql-1 sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SELECT @@innodb_buffer_pool_size/1024/1024/1024, @@max_connections, @@tmp_table_size/1024/1024;"' 2>/dev/null
  docker exec bbs-mysql-1 sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT * FROM sys.memory_global_total;"' 2>/dev/null
  ```
- [ ] ~~**Docker 日志轮转**~~ **已跳过（2026-10-08 决定不做）**：现状 0.5G/天、磁盘可用 183G，够用约一年，不值得现在投入。方案已备好：配置在仓库 `scripts/logrotate/`（日轮转保留 30 天 + 每周归档 1 份保留 52 周），将来想做了直接 `cp` 到宿主机 `/etc/logrotate.d/` 和 `/etc/cron.weekly/` 即可，无需重启容器。

## 五、P1 项（改代码，下次发版）

- [ ] 搜索限流：对 `/api/discussions?filter[q]=` 按 IP 限速（~1 次/秒），前端加大搜索防抖（`bbs-frontend`）
- [ ]（可选）补齐 `.well-known/assetlinks.json`，排查缺失头像 404
- [ ]（可选）评估是否拦截其他 AI 爬虫（GPTBot、ClaudeBot、Bytespider 等），扩展 nginx UA 正则名单

## 六、观察指标（部署后几天）

- [ ] 高峰期 `docker stats`：bbs-flarum 与 bbs-mysql CPU 是否回落（修复前 440% / 236%）
- [ ] `uptime` 的 load average（修复前 8~11）
- [ ] swap 使用量是否下降（修复前 1.9G/2.4G）
