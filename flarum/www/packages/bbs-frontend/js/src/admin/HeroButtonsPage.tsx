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
  private saving: boolean = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const raw = app.data.settings['hero_buttons'];
    this.buttons = Stream<HeroButton[]>(raw ? JSON.parse(raw) : []);
  }

  save() {
    if (this.saving) return;
    this.saving = true;
    m.redraw();

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/settings',
      body: { hero_buttons: JSON.stringify(this.buttons()) },
    }).then(() => {
      app.alerts.show({ type: 'success' }, '保存成功');
    }).catch(() => {
      app.alerts.show({ type: 'error' }, '保存失败，请重试');
    }).finally(() => {
      this.saving = false;
      m.redraw();
    });
  }

  content() {
    const buttons = this.buttons();

    return (
      <div className="container">
        <div className="Form">
          <h3>首页 Hero 按钮</h3>
          <p className="helpText">
            配置移动端首页横幅上的按钮。所有字段均需手动填写。<br />
            · 按钮文字示例：<code>使用指南</code><br />
            · 内部页面（FoF Pages）目标地址示例：<code>/p/1-help</code><br />
            · 外部链接目标地址示例：<code>https://example.com</code><br />
            · 统计路径示例：<code>click-help</code>（可不填）
          </p>

          {buttons.map((btn, i) => (
            <div className="Form-group" style="border: 1px solid #e3e3e3; padding: 12px; border-radius: 6px; margin-bottom: 8px;" key={btn.key}>
              <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 0 0 auto;">
                  <label>按钮文字</label>
                  <input
                    className="FormControl"
                    style="width: 140px"
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
            {' '}
            <Button
              className="Button Button--primary"
              onclick={() => this.save()}
              loading={this.saving}
            >
              保存
            </Button>
          </div>
        </div>
      </div>
    );
  }
}
