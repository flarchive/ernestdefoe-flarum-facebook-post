import { Admin } from 'flarum/common/extenders';

export default [
  new Admin()
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.enabled',
      type: 'boolean',
      label: 'Enable Facebook Auto-Post',
      help: 'When enabled, new discussions will be automatically posted to your chosen Facebook destination.',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.destination_type',
      type: 'select',
      label: 'Destination Type',
      help: 'Choose whether to post new discussions to a Facebook Page or a Facebook Group.',
      options: {
        page: 'Facebook Page',
        group: 'Facebook Group',
      },
      default: 'page',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.page_id',
      type: 'text',
      label: 'Facebook Page ID',
      help: 'The numeric ID of your Facebook Page (e.g. 123456789012345). Required when Destination Type is "Facebook Page".',
      placeholder: '123456789012345',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.page_access_token',
      type: 'password',
      label: 'Page Access Token',
      help: 'Never-expiring Page Access Token from Meta for Developers (requires pages_manage_posts permission). Used when Destination Type is "Facebook Page".',
      placeholder: 'EAAxxxxxxxx…',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.group_id',
      type: 'text',
      label: 'Facebook Group ID',
      help: 'The numeric ID of your Facebook Group. Required when Destination Type is "Facebook Group".',
      placeholder: '123456789012345',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-facebook-post.group_access_token',
      type: 'password',
      label: 'Group Access Token',
      help: 'User Access Token with the publish_to_groups permission. Used when Destination Type is "Facebook Group".',
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
