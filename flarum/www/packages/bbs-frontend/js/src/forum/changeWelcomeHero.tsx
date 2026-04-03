import { override } from 'flarum/common/extend';
import WelcomeHero, { IWelcomeHeroAttrs } from 'flarum/forum/components/WelcomeHero';
import clsx from 'flarum/common/utils/classList';
import Mithril from 'mithril';
import app from 'flarum/forum/app';
import listItems from 'flarum/common/helpers/listItems';
import ItemList from 'flarum/common/utils/ItemList';
import Button from 'flarum/common/components/Button';

interface HeroButton {
  key: string;
  label: string;
  type: 'route' | 'url';
  target: string;
  gcPath: string;
}

export default function () {
  override(WelcomeHero.prototype, 'view', function (original, vnode: Mithril.Vnode<IWelcomeHeroAttrs>) {
    const getItems = () => {
      const items = new ItemList<Mithril.Children>();
      const raw = app.forum.attribute<string>('heroButtons');
      const buttons: HeroButton[] = raw ? JSON.parse(raw) : [];

      buttons.forEach((btn) => {
        items.add(btn.key, Button.component(
          { class: clsx('Button'), onclick: () => {
            if (btn.gcPath) {
              window.goatcounter?.count({ path: btn.gcPath, title: btn.label, event: true });
            }
            if (btn.type === 'url') {
              window.open(btn.target, '_blank');
            } else {
              m.route.set(btn.target);
            }
          }},
          btn.label
        ));
      });

      return items;
    };

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
