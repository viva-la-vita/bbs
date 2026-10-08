import addSearchBar from './addSearchBar';
import addApprovalButtons from './addApprovalButtons';
import changeViewItems from './changeViewItems';
import changeWelcomeHero from './changeWelcomeHero';
import Search from 'flarum/forum/components/Search';

// 降低搜索请求频率：防抖 250ms → 600ms（默认间隔偏激进，打字稍慢就会每个词触发多次全文搜索，曾压满 MySQL）
// 注意该属性在 core 中是 protected，需要 as any 绕过 TS 检查
(Search as any).SEARCH_DEBOUNCE_TIME_MS = 600;

addSearchBar();
addApprovalButtons();
changeViewItems();
changeWelcomeHero();
