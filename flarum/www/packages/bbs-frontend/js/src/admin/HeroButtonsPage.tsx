import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import Mithril from 'mithril';

interface HeroButton {
  key: string;
  label: string;
  type: 'route' | 'url';
  target: string;
  gcPath: string;
}

export default class HeroButtonsPage extends ExtensionPage {
  private buttons!: Stream<HeroButton[]>;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const raw = app.data.settings['hero_buttons'];
    this.buttons = Stream<HeroButton[]>(raw ? JSON.parse(raw) : []);
  }

  data() {
    return {
      hero_buttons: JSON.stringify(this.buttons()),
    };
  }

  content() {
    const buttons = this.buttons();

    return (
      <div className="container">
        <div className="Form">
          <h3>首页 Hero 按钮</h3>
          <p className="helpText">配置显示在首页横幅上的按钮。内部页面填写路径（如 /p/1-help），外部链接填写完整 URL。</p>

          {buttons.map((btn, i) => (
            <div className="Form-group" style="border: 1px solid #e3e3e3; padding: 12px; border-radius: 6px; margin-bottom: 8px;" key={btn.key}>
              <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 0 0 auto;">
                  <label>按钮文字</label>
                  <input
                    className="FormControl"
                    style="width: 140px"
                    placeholder="使用指南"
                    value={btn.label}
                    oninput={(e: InputEvent) => {
                      buttons[i].label = (e.target as HTMLInputElement).value;
                      this.buttons([...buttons]);
                    }}
                  />
                </div>
                <div style="flex: 0 0 auto;">
                  <label>类型</label>
                  <select
                    className="FormControl"
                    style="width: 100px"
                    value={btn.type}
                    onchange={(e: Event) => {
                      buttons[i].type = (e.target as HTMLSelectElement).value as 'route' | 'url';
                      this.buttons([...buttons]);
                    }}
                  >
                    <option value="route">内部页面</option>
                    <option value="url">外部链接</option>
                  </select>
                </div>
                <div style="flex: 1; min-width: 200px;">
                  <label>目标地址</label>
                  <input
                    className="FormControl"
                    placeholder={btn.type === 'route' ? '/p/1-help' : 'https://example.com'}
                    value={btn.target}
                    oninput={(e: InputEvent) => {
                      buttons[i].target = (e.target as HTMLInputElement).value;
                      this.buttons([...buttons]);
                    }}
                  />
                </div>
                <div style="flex: 0 0 auto;">
                  <label>统计路径（可选）</label>
                  <input
                    className="FormControl"
                    style="width: 160px"
                    placeholder="click-help"
                    value={btn.gcPath || ''}
                    oninput={(e: InputEvent) => {
                      buttons[i].gcPath = (e.target as HTMLInputElement).value;
                      this.buttons([...buttons]);
                    }}
                  />
                </div>
                <div style="flex: 0 0 auto; align-self: flex-end;">
                  <Button
                    className="Button Button--danger"
                    onclick={() => {
                      this.buttons(buttons.filter((_, j) => j !== i));
                    }}
                  >
                    删除
                  </Button>
                </div>
              </div>
            </div>
          ))}

          <div className="Form-group">
            <Button
              className="Button"
              onclick={() => {
                this.buttons([
                  ...buttons,
                  { key: `btn-${Date.now()}`, label: '', type: 'route', target: '', gcPath: '' },
                ]);
              }}
            >
              + 添加按钮
            </Button>
          </div>
        </div>
      </div>
    );
  }
}
