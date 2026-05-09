import app from 'flarum/admin/app';
import DefaultImageUpload from './components/DefaultImageUpload';

app.initializers.add('ernestdefoe-facebook-post', () => {
  app.registry
    .for('ernestdefoe-facebook-post')

    .registerSetting({
      setting: 'ernestdefoe-facebook-post.enabled',
      label: 'Enable Facebook Auto-Post',
      help: 'When enabled, new discussions will be automatically posted to your Facebook Page.',
      type: 'boolean',
    })

    .registerSetting({
      setting: 'ernestdefoe-facebook-post.page_id',
      label: 'Facebook Page ID',
      help: 'The numeric ID of your Facebook Page (e.g. 123456789012345).',
      type: 'text',
      placeholder: '123456789012345',
    })

    .registerSetting({
      setting: 'ernestdefoe-facebook-post.page_access_token',
      label: 'Page Access Token',
      help: 'Never-expiring Page Access Token from Meta for Developers.',
      type: 'password',
      placeholder: 'EAAxxxxxxxx…',
    })

    .registerSetting(() => m(DefaultImageUpload))

    .registerPermission(
      {
        icon: 'fab fa-facebook',
        label: 'Manage Facebook Auto-Post',
        permission: 'ernestdefoe-facebook-post.manage',
      },
      'moderate'
    );
});
