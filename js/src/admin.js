import app from 'flarum/admin/app';

app.initializers.add('ernestdefoe-facebook-post', () => {
  app.extensionData
    .for('ernestdefoe-facebook-post')

    // ── Settings fields ──────────────────────────────────────────────────────
    .registerSetting({
      setting: 'ernestdefoe-facebook-post.enabled',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.enabled_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.enabled_help'),
      type: 'boolean',
    })

    .registerSetting({
      setting: 'ernestdefoe-facebook-post.page_id',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.page_id_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.page_id_help'),
      type: 'text',
      placeholder: '123456789012345',
    })

    .registerSetting({
      setting: 'ernestdefoe-facebook-post.page_access_token',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.token_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.token_help'),
      type: 'password',
      placeholder: 'EAAxxxxxxxx…',
    })

    // ── Permission ───────────────────────────────────────────────────────────
    .registerPermission(
      {
        icon: 'fab fa-facebook',
        label: app.translator.trans('ernestdefoe-facebook-post.admin.permissions.manage_label'),
        permission: 'ernestdefoe-facebook-post.manage',
      },
      'moderate'
    );
});
