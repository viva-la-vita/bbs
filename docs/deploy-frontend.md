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
> 该命令会把用户数据（头像、Logo、Favicon）一并删除，造成不可恢复的数据丢失。
> 2026 年 3 月曾因此删掉 5000+ 用户头像及论坛 Logo，仅能从旧备份部分恢复。

**`public/assets/` 目录下的文件分两类，操作时必须区分：**

| 路径 | 来源 | 可否删除 |
|---|---|---|
| `assets/*.js` / `*.css` / `*.map` / `*.json` | Flarum 运行时编译生成 | ✅ 可删，会自动重建 |
| `assets/extensions/` | `assets:publish` 发布 | ✅ 可删，会重新发布 |
| `assets/fonts/` | `assets:publish` 发布（Font Awesome） | ✅ 可删，会重新发布 |
| `assets/avatars/` | 用户上传的头像 | ❌ 绝对不能删 |
| `assets/logo-*.png` | 管理员在后台上传的 Logo | ❌ 绝对不能删 |
| `assets/favicon-*.png` | 管理员在后台上传的 Favicon | ❌ 绝对不能删 |

只删编译产物，完整保留用户数据：
```bash
# 只删 assets 根目录下的编译文件（按扩展名过滤，logo-*.png / favicon-*.png 不受影响）
docker exec bbs-flarum-1 find /var/www/flarum/public/assets/ -maxdepth 1 -type f \
  \( -name "*.js" -o -name "*.css" -o -name "*.map" -o -name "*.json" \) -delete

# 删除可重新发布的子目录
docker exec bbs-flarum-1 rm -rf /var/www/flarum/public/assets/extensions
docker exec bbs-flarum-1 rm -rf /var/www/flarum/public/assets/fonts

# 重新发布
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
- **`avatars/`、`logo-*.png`、`favicon-*.png` 均为用户/管理员上传的数据，清理资产时必须完整保留**
- **当前自动备份（`new_flarum_backup.sh`）只备份数据库 SQL，不备份 `assets/` 下的用户数据**。如文件丢失，只能从手动 tar 包恢复，且可能不完整。建议将 `avatars/`、`logo-*.png`、`favicon-*.png` 纳入备份脚本。
