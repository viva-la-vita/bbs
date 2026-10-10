# AI 爬虫与机器人流量情况记录（2026-10-08 采样）

> 数据来源：bbs-nginx-1 容器 json 日志尾部 5 万行（约数十分钟窗口，高峰期）。
> 原始分析过程见 [worklog-2026-10-08.md](./worklog-2026-10-08.md) 与 [diagnosis-2026-10-08.md](./diagnosis-2026-10-08.md)。

## 一、结论

论坛有相当比例的流量来自机器人，其中 **ExaSearchBot（Exa AI 爬虫）最为恶劣**：不守 robots、直接打 API、占全站流量约 10%，属于纯资源浪费。已于 2026-10-08 在 nginx 层 403 封禁（实测生效）。

## 二、流量构成（5 万行日志采样）

### 按 User-Agent 排名（前 10）

| 请求数 | UA | 性质 |
|---|---|---|
| **4776** | **ExaSearchBot/1.0 (+https://crawler.exa.ai/)** | **恶意 AI 爬虫，已封禁** |
| 2959 | Chrome 154 / Windows（Edge 版） | 真人浏览器 |
| 2722 | Chrome 154 / Linux X11 | 真人浏览器 |
| 1953 | Chrome 153 / Android EdgA | 真人浏览器 |
| 1629 | Chrome 154 / Windows | 真人浏览器 |
| 1077 | Chrome 153 / Android | 真人浏览器 |
| 1065 | Chrome 145 / Linux X11 | 真人浏览器 |
| 1057 | Chrome 149 / Android | 真人浏览器 |
| 800 | **Googlebot/2.1** | 正规搜索引擎，合规 |
| 730 | T7/13.38（HONOR 设备） | 头条/夸克系爬虫 UA，半合规 |

### ExaSearchBot 的请求目标

| 次数 | 路径 | 说明 |
|---|---|---|
| 397 | `/api/posts` | 直接抓 API（最重，每个请求都消耗 PHP+MySQL） |
| 11 | `/api/discussions` | 直接抓 API |
| 其余 | 分散的 `/d/xxxx` 页面 | 普通爬取 |

真人用户特征（对照）：搜索请求 IP 高度分散（top IP 仅 59 次/窗口）、UA 全为正常浏览器 → 真人搜索不是攻击。

## 三、已采取的处置

- ✅ nginx 按 UA 拦截 `ExaSearchBot` → 403（`nginx/conf.d/flarum.conf`）
- ✅ robots.txt 声明 `Disallow: /`（君子协定，防君子不防流氓）
- 验证结果：模拟 ExaSearchBot 请求返回 403，正常 UA 返回 200

## 四、持续监控方法

```bash
# 查看最近有哪些爬虫在访问（宿主机执行，注意日志文件是 json 格式）
LOG=$(docker inspect -f '{{.LogPath}}' bbs-nginx-1)
tail -n 100000 "$LOG" | sed -e 's/^.*"log":"//' -e 's/","stream.*$//' -e 's/\\"/"/g' | awk -F'"' '{print $(NF-1)}' | sort | uniq -c | sort -rn | head -15

# 看 403 拦截量（评估封禁效果）
tail -n 100000 "$LOG" | grep -c ' 403 '
```

## 五、后续候选（观察到再处理，暂不实施）

以下 AI 爬虫若在日志中出现且行为恶劣，可加入 nginx UA 拦截正则：

- `GPTBot`（OpenAI）
- `ClaudeBot`（Anthropic）
- `Bytespider`（字节）
- `CCBot`（Common Crawl）
- `PerplexityBot`

注意：Googlebot、Bingbot 等正规搜索引擎建议保留（SEO 需要）；封禁前建议先确认其请求量和行为，避免误伤。

## 六、背景知识

- Flarum 的 `/api/*` 接口被爬虫直接抓取时代价很高：每个请求都走 PHP + MySQL。ExaSearchBot 打 `/api/posts?filter[id]=...` 时，批量 ID 查询会进一步放大消耗。
- 2026-10-08 修复前，全站平均 ~900 次 SQL/秒，机器人流量是重要推手之一。
