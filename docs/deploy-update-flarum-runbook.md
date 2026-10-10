# 部署时出现 "Update Flarum" 的应对策略

> 场景：部署新 flarum 镜像后，访问网站出现以下页面，全站不可用：
>
> ```
> Update Flarum
> Enter your database password to update Flarum.
> Before you proceed, you should back up your database.
> ```
>
> 相关文档：DEPLOY.md 步骤 4、docs/deploy-frontend.md 故障排查。

## 一、这是什么（不要慌）

**这不是数据库损坏，是 Flarum 的正常保护机制。**

原因：仓库 `composer.json` 中大量扩展版本约束写的是 `"*"`，而 Dockerfile 每次构建都执行 `composer update`，所以**每次构建镜像都会把所有 `*` 扩展升级到当时的最新版**。若其中某个扩展的新版本带了数据库结构变更（migration），Flarum 检测到迁移未执行，就锁住前端并提示更新。

触发条件：距上次构建时间越长，积压的扩展升级越多，越容易出现此提示（2026-10-08 部署时一次性升级了 20+ 个扩展，因此触发）。

## 二、标准处理流程

### 第 1 步：确认备份存在（10 秒）

```bash
ls -lh ~/ | grep -i sql | tail -3
```

有近期的数据库备份文件即可继续。没有则先执行备份脚本再往下走。

### 第 2 步：执行数据库迁移

```bash
docker exec -it bbs-flarum-1 php flarum migrate
```

提示 `Enter your database password:` 时输入数据库密码（在 `~/bbs/.env` 的 `MYSQL_PASSWORD=`，输入不显示字符）。

免交互写法（密码明文进 shell history，按需使用）：

```bash
echo "密码" | docker exec -i bbs-flarum-1 php flarum migrate
```

预期输出：一串 `Migrating extension: xxx` / `Nothing to migrate`，个别扩展显示 `Migrated: 时间戳_迁移名`，最后 `DONE`。

### 第 3 步：清缓存

```bash
docker exec -u www-data bbs-flarum-1 php flarum cache:clear
```

### 第 4 步：浏览器验证

`Ctrl+Shift+R` 强刷，提示消失、论坛恢复。

### 第 5 步（次日）：错误日志巡检

```bash
docker exec bbs-flarum-1 tail -n 50 storage/logs/flarum-$(date +%Y-%m-%d).log
```

批量升级后头两天留意有没有新报错。

## 三、本次（2026-10-08）踩过的坑

1. **`cache:clear` 报 "Could not clear contents of `storage/cache`" 或卡死**
   原因：执行 `chmod -R 775 storage/cache` 时按 Ctrl+C 中断，目录内 root/www-data 所有权混杂，www-data 删不动。
   处置（用 root 整体重建缓存目录）：
   ```bash
   docker exec bbs-flarum-1 sh -c 'rm -rf /var/www/flarum/storage/cache && mkdir -p /var/www/flarum/storage/cache && chown -R www-data:www-data /var/www/flarum/storage/cache && chmod -R 775 /var/www/flarum/storage/cache'
   docker exec -u www-data bbs-flarum-1 php flarum cache:clear
   ```

2. **migrate 前必须先完成"重新发布资产"步骤**
   本次部署顺序：重建容器 →（跳过 enable）→ 删旧编译资产 → `assets:publish` → migrate → cache:clear。前端 JS 有改动时漏掉 assets 步骤会白屏/404，见 docs/deploy-frontend.md。

3. **`docker exec -it ... php flarum migrate` 无响应**
   通常是 `-it` 的 TTY 问题或容器正繁忙，等一下；不行就换 `-i` 加管道输入密码。

## 四、预防（可选，后续再议）

若不想"每次构建全量升级"，可把关键扩展的版本号钉死（如 `"fof/polls": "2.2.15"`），代价是安全/缺陷修复需手动跟进版本。截至 2026-10-08 维持 `"*"` 现状。
