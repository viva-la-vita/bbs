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
        { class: clsx('Button'), onclick: () => {
          window.goatcounter?.count({ path: 'click-help', title: '使用指南', event: true });
          m.route.set('/p/1-help');
        }},
        '使用指南'
      ));
      items.add('telegram', Button.component(
        { class: clsx('Button'), onclick: () => {
          window.goatcounter?.count({ path: 'click-telegram', title: 'Telegram 群组', event: true });
          m.route.set('/p/2-telegram');
        }},
        'Telegram 群组'
      ));
      items.add('sponsor', Button.component(
        { class: clsx('Button'), onclick: () => {
          window.goatcounter?.count({ path: 'click-sponsor-ai-course', title: '[赞助]Ai强制调教课表', event: true });
          window.open('https://genraton.xyz/zh/explore/apps?ranking=daily_rank&tags=26f21463-ac71-4bfb-8872-30e8b2a90db3&displayMode=simple&order=default&app_type=1&display=simple&ref_id=84b8f4b4-1835-4d25-8a3d-5d2ba33cff00', '_blank');
        }},
        '[赞助]Ai强制调教课表💗'
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
