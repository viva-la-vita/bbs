# 部署流程

## 前提

代码修改完成后，commit + push 到 master 分支，GitHub Actions 会自动构建新的 Docker 镜像。
等 Actions 跑完（GitHub 仓库 Actions 页面显示绿色）再进行以下步骤。

## 登录云服务器后的操作

```bash
cd ~/bbs

# 1. 拉取最新镜像
docker compose pull flarum

# 2. 停止并删除旧容器
docker stop bbs-flarum-1
docker rm bbs-flarum-1

# 3. 启动新容器
docker compose up -d

# 4. 如有数据库结构变更（页面提示 Update Flarum），执行迁移
docker exec -it bbs-flarum-1 php flarum migrate

# 5. 清除前端编译缓存（前端代码有改动时必须执行）
docker exec bbs-flarum-1 chmod -R 775 /var/www/flarum/storage/cache
docker exec -u www-data bbs-flarum-1 php flarum cache:clear

# 6. 访问网站，触发 Flarum 重新编译 JS/CSS
```

## 注意事项

- `docker compose up -d flarum` 只启动 flarum，可能导致 nginx 容器丢失，应使用 `docker compose up -d` 启动所有服务
- 每次 `composer update` 会将 `composer.json` 中标注为 `*` 的插件升级到最新版，可能触发数据库迁移提示，执行步骤 4 即可
- 前端缓存（步骤 5）不清除的话，新编译的 JS 不会生效，页面仍显示旧版本
