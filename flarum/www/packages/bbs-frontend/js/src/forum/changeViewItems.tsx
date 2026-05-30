import { override } from 'flarum/common/extend';
import clsx from 'flarum/common/utils/classList';
import IndexPage from 'flarum/forum/components/IndexPage';
import ItemList from 'flarum/common/utils/ItemList';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import app from 'flarum/forum/app';

export default function () {
  override(IndexPage.prototype, 'viewItems', function(original) {
    const items = new ItemList<Mithril.Children>();
    const sortMap = app.discussions.sortMap();

    const sortOptions = Object.keys(sortMap).reduce((acc: any, sortId) => {
      acc[sortId] = app.translator.trans(`core.forum.index_sort.${sortId}_button`);
      return acc;
    }, {});

    // 'recommend' 排序由 viva-la-vita/bbs-recommend 插件提供（后端注册 sort + 文案翻译）。
    const availableSorts = ['recommend', 'latest', 'front', 'top'];

    availableSorts.forEach((value) => {
      // 直接按排序名取翻译，不依赖前端 sortMap 是否登记了该键。
      // （recommend 排序仅在后端注册，前端 sortMap 没有它，故旧写法 sortOptions[value] 取不到文字。）
      const label = sortOptions[value] ?? app.translator.trans(`core.forum.index_sort.${value}_button`);
      const active = (app.search.params().sort || Object.keys(sortMap)[0]) === value;

      items.add(
        value,
        Button.component(
          {
            class: clsx('Button', active && 'active'),
            onclick: app.search.changeSort.bind(app.search, value),
            active: active,
          },
          label
        )
      );
    });

    return items;
  });
}
