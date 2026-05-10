import { Admin } from 'flarum/common/extenders';

export default [
  new Admin()
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.enabled',
      type: 'boolean',
      label: 'Enable Facebook Auto-Post',
      help: 'When enabled, new discussions will be automatically posted to your Facebook Page.',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.page_id',
      type: 'text',
      label: 'Facebook Page ID',
      help: 'The numeric ID of your Facebook Page (e.g. 123456789012345).',
      placeholder: '123456789012345',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.page_access_token',
      type: 'password',
      label: 'Page Access Token',
      help: 'Never-expiring Page Access Token from Meta for Developers.',
      placeholder: 'EAAxxxxxxxx…',
    }))
    .permission(
      () => ({
        icon: 'fab fa-facebook',
        label: 'Manage Facebook Auto-Post',
        permission: 'ernestdefoe-facebook-post.manage',
      }),
      'moderate'
    ),
];
