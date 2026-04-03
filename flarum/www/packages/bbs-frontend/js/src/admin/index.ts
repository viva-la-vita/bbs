import app from 'flarum/admin/app';
import HeroButtonsPage from './HeroButtonsPage';

app.initializers.add('viva-la-vita-bbs-frontend', () => {
  app.extensionData
    .for('viva-la-vita-bbs-frontend')
    .registerPage(HeroButtonsPage);
});
