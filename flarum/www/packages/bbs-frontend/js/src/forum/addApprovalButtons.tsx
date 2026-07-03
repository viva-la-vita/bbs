import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import Discussion from 'flarum/common/models/Discussion';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import Button from 'flarum/common/components/Button';

/**
 * 给未审核的主题添加「审核通过」按钮。
 *
 * flarum/approval 扩展已给 Post 提供了审核功能，
 * 但 Discussion 缺少 canApprove 属性和审核按钮。
 *
 * 本模块补充：
 * 1. Discussion 模型的 canApprove 属性（依赖后端 bbs-frontend/extend.php 注册）
 * 2. DiscussionControls.moderationControls 添加「审核通过」按钮
 */
export default function () {
  // 给 Discussion 模型补充 canApprove 属性
  Discussion.prototype.canApprove = Discussion.attribute('canApprove');

  // ===== 主题控制菜单：在 moderationControls 添加「审核通过」按钮 =====
  // DiscussionListItem 和 DiscussionPage 都通过 DiscussionControls.controls() 生成控制菜单
  extend(DiscussionControls, 'moderationControls', function (items, discussion) {
    if (!discussion.isApproved() && discussion.canApprove()) {
      items.add(
        'approve',
        <Button
          icon="fas fa-check"
          onclick={() => {
            // Flarum 核心没有处理 Discussion 的 isApproved 属性，
            // 必须通过审核首帖来间接审核主题。
            // 首帖审核通过后，flarum/approval 的 UpdateDiscussionAfterPostApproval
            // 会自动将 discussion.is_approved 设为 true。
            const firstPost = discussion.firstPost();
            if (firstPost) {
              firstPost.save({ isApproved: true }).then(() => {
                discussion.pushAttributes({ isApproved: true });
                app.alerts.show({ type: 'success' }, '主题已审核通过');
              });
            }
          }}
        >
          审核通过
        </Button>,
        10
      );
    }
  });
}
