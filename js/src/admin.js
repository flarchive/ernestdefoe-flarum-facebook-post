import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import TagSelector from './admin/TagSelector';

const EXT = 'ernestdefoe-facebook-post';
const T = (key) => app.translator.trans(`${EXT}.admin.${key}`);

app.initializers.add(EXT, () => {
    // Settings + permission registrations live next to the tag-selector
    // extension in the same initializer so a single bundle entrypoint
    // owns the admin surface. The previous build kept these in a
    // sibling `extend.js` that was never imported — they shipped as
    // dead source and never reached the admin UI.
    //
    // Note: Flarum 2 exposes the admin registry as `app.registry` (the
    // class is `AdminRegistry`). Flarum 1.x called this `extensionData`
    // and that's still the name in older docs — be careful when
    // copy-pasting examples from elsewhere.
    app.registry
        .for(EXT)
        .registerSetting({
            setting: `${EXT}.enabled`,
            type: 'boolean',
            label: T('settings.enabled_label'),
            help: T('settings.enabled_help'),
        })
        .registerSetting({
            setting: `${EXT}.destination_type`,
            type: 'select',
            label: T('settings.destination_type_label'),
            help: T('settings.destination_type_help'),
            options: {
                page: T('settings.destination_type_page'),
                group: T('settings.destination_type_group'),
            },
            default: 'page',
        })
        .registerSetting({
            setting: `${EXT}.page_id`,
            type: 'text',
            label: T('settings.page_id_label'),
            help: T('settings.page_id_help'),
            placeholder: T('settings.page_id_placeholder'),
        })
        .registerSetting({
            setting: `${EXT}.page_access_token`,
            type: 'password',
            label: T('settings.page_access_token_label'),
            help: T('settings.page_access_token_help'),
        })
        .registerSetting({
            setting: `${EXT}.group_id`,
            type: 'text',
            label: T('settings.group_id_label'),
            help: T('settings.group_id_help'),
            placeholder: T('settings.group_id_placeholder'),
        })
        .registerSetting({
            setting: `${EXT}.group_access_token`,
            type: 'password',
            label: T('settings.group_access_token_label'),
            help: T('settings.group_access_token_help'),
        })
        .registerSetting({
            setting: `${EXT}.graph_api_version`,
            type: 'text',
            label: T('settings.graph_api_version_label'),
            help: T('settings.graph_api_version_help'),
            placeholder: 'v19.0',
        })
        .registerPermission(
            {
                icon: 'fab fa-facebook',
                label: T('permissions.manage'),
                permission: `${EXT}.manage`,
            },
            'moderate'
        );

    // The tag selector is a custom component, not a simple setting row,
    // so it goes into the Settings section via the ExtensionPage hook.
    // sections() returns an ItemList with 'content' (priority 100) and
    // 'permissions' (priority 60) — slot the selector between them.
    extend(ExtensionPage.prototype, 'sections', function (items) {
        if (this.attrs.id !== EXT) return;
        items.add('tag-filter', m('.ExtensionPage-settings', m(TagSelector)), 80);
    });
});
