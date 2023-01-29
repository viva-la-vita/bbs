import { extend, override } from 'flarum/common/extend';
import clsx from 'flarum/common/utils/classList';
import IndexPage from 'flarum/forum/components/IndexPage';
import ItemList from 'flarum/common/utils/ItemList';
import listItems from 'flarum/common/helpers/listItems';
import DiscussionList from 'flarum/forum/components/DiscussionList';
import Button from 'flarum/common/components/Button';
import Search from 'flarum/forum/components/Search';
import type Mithril from 'mithril';
import app from 'flarum/forum/app';

override(IndexPage.prototype, 'viewItems', function(original) {
  const items = new ItemList<Mithril.Children>();
  // items.add('search', Search.component({ state: app.search }));
  const sortMap = app.discussions.sortMap();
  console.log(sortMap);

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

override(IndexPage.prototype, 'view', function (original) {
  return (
    <div className="IndexPage">
    {this.hero()}
    <div className="container">
      <div className="sideNavContainer">
        <nav className="IndexPage-nav sideNav">
          <ul>{listItems(this.sidebarItems().toArray())}</ul>
        </nav>
        <div className="IndexPage-results sideNavOffset">
          <div className='IndexPage-searchbar'>
            <Search state={app.search} />
          </div>
          <div className="IndexPage-toolbar">
            <ul className="IndexPage-toolbar-view">{listItems(this.viewItems().toArray())}</ul>
            <ul className="IndexPage-toolbar-action">{listItems(this.actionItems().toArray())}</ul>
          </div>
          <DiscussionList state={app.discussions} />
        </div>
      </div>
    </div>
  </div>
  )
});
