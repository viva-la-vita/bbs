import { override } from 'flarum/common/extend';
import WelcomeHero, { IWelcomeHeroAttrs } from 'flarum/forum/components/WelcomeHero';
import clsx from 'flarum/common/utils/classList';
import Mithril from 'mithril';
import app from 'flarum/forum/app';
import listItems from 'flarum/common/helpers/listItems';
import ItemList from 'flarum/common/utils/ItemList';
import Button from 'flarum/common/components/Button';

export default function () {
  override(WelcomeHero.prototype, 'view', function (original, vnode: Mithril.Vnode<IWelcomeHeroAttrs>) {
    const getItems = () => {
      const items = new ItemList<Mithril.Children>();
      items.add('help', Button.component(
        { class: clsx('Button'), onclick: () => m.route.set('/p/1-help') },
        '使用指南'
      ));
      items.add('telegram', Button.component(
        { class: clsx('Button'), onclick: () => m.route.set('/p/2-telegram') },
        'Telegram 群组'
      ));
      return items;
    }
    return (
      <header class="Hero WelcomeHero">
        <div class="container">
          <div class="containerNarrow">
            <h2 class="Hero-title">生如夏花论坛</h2>
            <div class="Hero-subtitle">
              <ul class="Hero-links">
                { listItems(getItems().toArray()) }
              </ul>
            </div>
          </div>
        </div>
      </header>
    )
  });
}
