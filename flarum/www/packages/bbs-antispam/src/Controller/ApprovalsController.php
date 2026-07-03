<?php

namespace VivalAvita\BbsAntispam\Controller;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /approvals —— 待审核内容管理页面。
 *
 * 有 discussion.approvePosts 权限者（管理员、版主）可访问，
 * 其他人抛 403。列出所有 is_approved = 0 的主题和帖子，
 * 提供一键审核通过功能。
 */
class ApprovalsController implements RequestHandlerInterface
{
    const PER_PAGE = 50;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // 检查 discussion.approvePosts 权限：管理员 isAdmin 自动 true，版主默认有
        if ($actor->isGuest() || ! $actor->hasPermission('discussion.approvePosts')) {
            throw new PermissionDeniedException();
        }

        // 处理 POST 审核通过动作
        $method = $request->getMethod();
        if ($method === 'POST') {
            $body = $request->getParsedBody() ?? [];
            $action = Arr::get($body, 'action');
            $id = (int) Arr::get($body, 'id', 0);
            $type = Arr::get($body, 'type', '');
            $page = max(1, (int) Arr::get($body, 'page', 1));

            if ($action === 'approve' && $id > 0) {
                $this->approve($actor, $id, $type);
            }
            // POST 后重定向回 GET，避免刷新重复提交
            return new \Laminas\Diactoros\Response\RedirectResponse('/approvals?page=' . $page);
        }

        $page = max(1, (int) Arr::get($request->getQueryParams(), 'page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        // 待审核主题
        $discussionTotal = Discussion::where('is_approved', false)->count();
        $discussions = Discussion::where('is_approved', false)
            ->with('user')
            ->orderByRaw('created_at IS NULL, created_at DESC')
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        // 待审核帖子（排除首帖，因为首帖的审核通过主题审核即可）
        $postTotal = Post::where('is_approved', false)
            ->where('number', '!=', 1)
            ->count();
        $posts = Post::where('is_approved', false)
            ->where('number', '!=', 1)
            ->with(['user', 'discussion'])
            ->orderByRaw('created_at IS NULL, created_at DESC')
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        return new HtmlResponse($this->render(
            $discussions, $posts, $page, $discussionTotal, $postTotal
        ));
    }

    private function approve($actor, int $id, string $type): void
    {
        if ($type === 'discussion') {
            $discussion = Discussion::where('id', $id)->where('is_approved', false)->first();
            if ($discussion && $actor->can('approvePosts', $discussion)) {
                $discussion->is_approved = true;
                $discussion->save();
                // 同时将该主题下所有未审核帖子也设为已审核
                Post::where('discussion_id', $discussion->id)
                    ->where('is_approved', false)
                    ->update(['is_approved' => true]);
            }
        } elseif ($type === 'post') {
            $post = Post::where('id', $id)->where('is_approved', false)->first();
            if ($post && $actor->can('approvePosts', $post->discussion)) {
                $post->is_approved = true;
                $post->save();
            }
        }
    }

    private function render($discussions, $posts, int $page, int $discussionTotal, int $postTotal): string
    {
        $totalPages = max(1, (int) ceil(max($discussionTotal, $postTotal) / self::PER_PAGE));

        // 主题行
        $discRows = '';
        $i = ($page - 1) * self::PER_PAGE;
        foreach ($discussions as $d) {
            $i++;
            $title = htmlspecialchars((string) ($d->title ?? '(无标题)'), ENT_QUOTES);
            $author = htmlspecialchars((string) ($d->user->username ?? '(未知/已删)'), ENT_QUOTES);
            $time = $d->created_at ? $d->created_at->format('Y-m-d H:i') : '—';
            $hidden = '';
            if (!$d->is_approved) {
                $hidden .= ' <span style="color:#c00">[审核中]</span>';
            }
            if ($d->hidden_at) {
                $hidden .= ' <span style="color:#c00">[已隐藏]</span>';
            }
            $approveBtn = '<form method="POST" action="/approvals" style="display:inline">'
                . '<input type="hidden" name="action" value="approve">'
                . '<input type="hidden" name="type" value="discussion">'
                . '<input type="hidden" name="id" value="' . $d->id . '">'
                . '<input type="hidden" name="page" value="' . $page . '">'
                . '<button type="submit" class="btn">审核通过</button></form>';
            $discRows .= "<tr><td>{$i}</td>"
                . "<td><a href=\"/d/{$d->id}\" target=\"_blank\">{$title}</a>{$hidden}</td>"
                . "<td>{$author}</td>"
                . "<td>{$time}</td>"
                . "<td>{$approveBtn}</td></tr>";
        }
        if ($discRows === '') {
            $discRows = '<tr><td colspan="5" style="text-align:center;color:#888">暂无待审核主题</td></tr>';
        }

        // 帖子行
        $postRows = '';
        $j = ($page - 1) * self::PER_PAGE;
        foreach ($posts as $p) {
            $j++;
            $discTitle = htmlspecialchars((string) ($p->discussion->title ?? '(无标题)'), ENT_QUOTES);
            $author = htmlspecialchars((string) ($p->user->username ?? '(未知/已删)'), ENT_QUOTES);
            $time = $p->created_at ? $p->created_at->format('Y-m-d H:i') : '—';
            $content = htmlspecialchars(mb_substr(strip_tags((string) $p->content), 0, 80), ENT_QUOTES);
            $approveBtn = '<form method="POST" action="/approvals" style="display:inline">'
                . '<input type="hidden" name="action" value="approve">'
                . '<input type="hidden" name="type" value="post">'
                . '<input type="hidden" name="id" value="' . $p->id . '">'
                . '<input type="hidden" name="page" value="' . $page . '">'
                . '<button type="submit" class="btn">审核通过</button></form>';
            $postRows .= "<tr><td>{$j}</td>"
                . "<td><a href=\"/d/{$p->discussion_id}\" target=\"_blank\">{$discTitle}</a></td>"
                . "<td>{$content}</td>"
                . "<td>{$author}</td>"
                . "<td>{$time}</td>"
                . "<td>{$approveBtn}</td></tr>";
        }
        if ($postRows === '') {
            $postRows = '<tr><td colspan="6" style="text-align:center;color:#888">暂无待审核帖子</td></tr>';
        }

        $nav = '';
        if ($page > 1) {
            $nav .= '<a href="?page=' . ($page - 1) . '">← 上一页</a> &nbsp; ';
        }
        $nav .= "第 {$page} / {$totalPages} 页";
        if ($page < $totalPages) {
            $nav .= ' &nbsp; <a href="?page=' . ($page + 1) . '">下一页 →</a>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>待审核内容</title>
<style>
  body{font-family:-apple-system,"Segoe UI",sans-serif;margin:2rem;color:#222;max-width:1200px;margin:2rem auto;}
  h2{margin:0 0 1rem;}
  h3{margin:1.5rem 0 .5rem;font-size:16px;color:#555;}
  table{border-collapse:collapse;width:100%;font-size:14px;}
  th,td{border:1px solid #e0e0e0;padding:6px 10px;text-align:left;vertical-align:top;}
  th{background:#f5f5f5;}
  tr:nth-child(even) td{background:#fafafa;}
  a{color:#1d6fb8;text-decoration:none;}
  a:hover{text-decoration:underline;}
  .nav{margin:1rem 0;color:#555;}
  .btn{display:inline-block;padding:3px 10px;background:#28a745;color:#fff;border-radius:3px;font-size:12px;text-decoration:none;border:none;cursor:pointer;}
  .btn:hover{background:#218838;}
  .count{color:#888;font-size:13px;margin-left:8px;}
</style>
</head>
<body>
<h2>待审核内容</h2>
<div class="nav">{$nav}</div>

<h3>待审核主题 <span class="count">（共 {$discussionTotal} 条）</span></h3>
<table>
  <thead><tr><th>#</th><th>标题</th><th>作者</th><th>发布时间</th><th>操作</th></tr></thead>
  <tbody>{$discRows}</tbody>
</table>

<h3>待审核帖子（非首帖） <span class="count">（共 {$postTotal} 条）</span></h3>
<table>
  <thead><tr><th>#</th><th>所属主题</th><th>内容预览</th><th>作者</th><th>发布时间</th><th>操作</th></tr></thead>
  <tbody>{$postRows}</tbody>
</table>

<div class="nav">{$nav}</div>
</body>
</html>
HTML;
    }
}
