<?php

namespace VivalAvita\BbsAntispam\Controller;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /privates —— 后端渲染的「全部私密主题」列表页。
 *
 * 仅有 user.viewPrivateDiscussions 权限者可访问（管理员 isAdmin 自动拥有），
 * 其他人抛 403。按 last_posted_at 倒序列出全站所有 byobu 私信，分页。
 *
 * byobu 把私信从首页/全部主题列表里隐藏了，且自带的 /private 页只列自己参与的，
 * 所以管理员没有"集中看所有私信"的入口。这个独立页面补上这个入口。
 * 纯后端 HTML，不依赖也不改动前端 SPA。
 */
class PrivatesController implements RequestHandlerInterface
{
    const PER_PAGE = 50;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // 与可见性权限一致：没权限的人（含游客）不能访问
        if ($actor->isGuest() || ! $actor->hasPermission('user.viewPrivateDiscussions')) {
            throw new PermissionDeniedException();
        }

        $page = max(1, (int) Arr::get($request->getQueryParams(), 'page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $total = Discussion::where('is_private', true)->count();

        $discussions = Discussion::where('is_private', true)
            ->with('user')
            ->orderByRaw('last_posted_at IS NULL, last_posted_at DESC')
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        return new HtmlResponse($this->render($discussions, $page, $total));
    }

    private function render($discussions, int $page, int $total): string
    {
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $rows = '';
        $i = ($page - 1) * self::PER_PAGE;
        foreach ($discussions as $d) {
            $i++;
            $title = htmlspecialchars((string) ($d->title ?? '(无标题)'), ENT_QUOTES);
            $author = htmlspecialchars((string) ($d->user->username ?? '(未知/已删)'), ENT_QUOTES);
            $time = $d->last_posted_at ? $d->last_posted_at->format('Y-m-d H:i') : '—';
            $hidden = $d->hidden_at ? ' <span style="color:#c00">[已隐藏]</span>' : '';
            $rows .= "<tr><td>{$i}</td>"
                . "<td><a href=\"/d/{$d->id}\" target=\"_blank\">{$title}</a>{$hidden}</td>"
                . "<td>{$author}</td>"
                . "<td>{$d->comment_count}</td>"
                . "<td>{$time}</td></tr>";
        }

        $nav = '';
        if ($page > 1) {
            $nav .= '<a href="?page=' . ($page - 1) . '">← 上一页</a> &nbsp; ';
        }
        $nav .= "第 {$page} / {$totalPages} 页（共 {$total} 条）";
        if ($page < $totalPages) {
            $nav .= ' &nbsp; <a href="?page=' . ($page + 1) . '">下一页 →</a>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>全部私密主题</title>
<style>
  body{font-family:-apple-system,"Segoe UI",sans-serif;margin:2rem;color:#222;}
  h2{margin:0 0 1rem;}
  table{border-collapse:collapse;width:100%;font-size:14px;}
  th,td{border:1px solid #e0e0e0;padding:6px 10px;text-align:left;vertical-align:top;}
  th{background:#f5f5f5;}
  tr:nth-child(even) td{background:#fafafa;}
  a{color:#1d6fb8;text-decoration:none;}
  a:hover{text-decoration:underline;}
  .nav{margin:1rem 0;color:#555;}
</style>
</head>
<body>
<h2>全部私密主题（管理员视图）</h2>
<div class="nav">{$nav}</div>
<table>
  <thead><tr><th>#</th><th>标题</th><th>作者</th><th>回复</th><th>最后活动</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
<div class="nav">{$nav}</div>
</body>
</html>
HTML;
    }
}
