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

    const availableSorts = ['latest', 'front', 'top'];

    availableSorts.forEach((value) => {
      const label = sortOptions[value];
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
