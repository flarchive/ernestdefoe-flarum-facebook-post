import app from 'flarum/admin/app';
import { Admin } from 'flarum/common/extenders';

export default [
  new Admin()
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.enabled',
      type: 'boolean',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.enabled_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.enabled_help'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.destination_type',
      type: 'select',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.destination_type_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.destination_type_help'),
      options: {
        page: app.translator.trans('ernestdefoe-facebook-post.admin.settings.destination_type_page'),
        group: app.translator.trans('ernestdefoe-facebook-post.admin.settings.destination_type_group'),
      },
      default: 'page',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.page_id',
      type: 'text',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.page_id_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.page_id_help'),
      placeholder: app.translator.trans('ernestdefoe-facebook-post.admin.settings.page_id_placeholder'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.page_access_token',
      type: 'password',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.page_access_token_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.page_access_token_help'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.group_id',
      type: 'text',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.group_id_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.group_id_help'),
      placeholder: app.translator.trans('ernestdefoe-facebook-post.admin.settings.group_id_placeholder'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.group_access_token',
      type: 'password',
      label: app.translator.trans('ernestdefoe-facebook-post.admin.settings.group_access_token_label'),
      help: app.translator.trans('ernestdefoe-facebook-post.admin.settings.group_access_token_help'),
    }))
    .permission(
      () => ({
        icon: 'fab fa-facebook',
        label: app.translator.trans('ernestdefoe-facebook-post.admin.permissions.manage'),
        permission: 'ernestdefoe-facebook-post.manage',
      }),
      'moderate'
    ),
];
