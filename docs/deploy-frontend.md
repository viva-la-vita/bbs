# 前端代码修改部署流程

## 项目架构
- 前端（Flarum）代码位于 `flarum/www/packages/bbs-frontend/js/src/`
- Docker 镜像托管在 `ghcr.io/viva-la-vita/flarum`
- 推送到 master 分支后，GitHub Actions 自动构建并推送新镜像

---

## 完整流程

### 第一步：本地修改代码并提交
```bash
git add .
git commit -m "描述改动"
git push
```

### 第二步：等待 GitHub Actions 构建完成
- 在 GitHub 仓库的 Actions 页面确认绿色 ✓

### 第三步：云服务器拉取新镜像并重启
```bash
docker pull ghcr.io/viva-la-vita/flarum
cd ~/bbs
docker compose up -d --no-deps flarum
```

### 第四步：强制重新发布静态资产（关键步骤）

> ⚠️ **警告：绝对不能使用 `rm -rf /var/www/flarum/public/assets/`**
> 该命令会连同用户上传的头像（`avatars/` 目录）一并删除，造成不可恢复的数据丢失。
> 2026 年 3 月曾因此删掉 5000+ 用户头像，仅能从旧备份部分恢复。

只删编译产物，保留用户数据：
```bash
docker exec bbs-flarum-1 find /var/www/flarum/public/assets/ -maxdepth 1 -type f -delete
docker exec bbs-flarum-1 rm -rf /var/www/flarum/public/assets/extensions
docker exec bbs-flarum-1 php flarum assets:publish
```

### 第五步：清除缓存
```bash
docker exec bbs-flarum-1 php flarum cache:clear
```

### 第六步：浏览器强制刷新
- `Ctrl+Shift+R` 或用无痕模式验证

---

## 故障排查

### 白屏（JS 崩溃）
**症状**：页面一片空白，浏览器 Console 报 `TypeError: Cannot read properties of undefined (reading 'type')`

**原因**：Flarum 清缓存后重建时，偶发性地把坏数据写入缓存，导致预加载 API 数据异常。

**解决**：再跑一次 `cache:clear` 即可：
```bash
docker exec bbs-flarum-1 php flarum cache:clear
```

---

## 注意事项
- `public/assets/` 是 Docker volume，**不会随镜像更新自动替换**，必须手动删除旧资产再重新发布
- 如果不删除旧资产直接 `assets:publish`，可能不会覆盖已存在的文件
- **`avatars/` 是用户上传的头像，属于用户数据，清理资产时必须保留**
- **当前自动备份（`new_flarum_backup.sh`）只备份数据库 SQL，不备份头像文件**。如头像丢失，只能从手动 tar 包恢复，且可能不完整。建议将头像目录纳入备份脚本。
