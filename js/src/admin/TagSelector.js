import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

const SETTING_KEY = 'ernestdefoe-facebook-post.allowed_tags';

export default class TagSelector extends Component {
    oninit(vnode) {
        super.oninit(vnode);
        this.tags    = null;
        this.loading = true;
        this.saving  = false;
        this.selectedIds = this.readSelected();
    }

    readSelected() {
        try {
            return JSON.parse(app.data.settings[SETTING_KEY] || '[]').map(String);
        } catch {
            return [];
        }
    }

    oncreate(vnode) {
        super.oncreate(vnode);
        this.fetchTags();
    }

    async fetchTags() {
        try {
            const tags = await app.store.find('tags');
            this.tags = tags.filter(t => !t.attribute('isChild'));
        } catch {
            this.tags = [];
        }
        this.loading = false;
        m.redraw();
    }

    toggle(id) {
        const sid = String(id);
        this.selectedIds = this.selectedIds.includes(sid)
            ? this.selectedIds.filter(i => i !== sid)
            : [...this.selectedIds, sid];
        this.persist();
    }

    persist() {
        this.saving = true;
        const value = JSON.stringify(this.selectedIds);
        app.request({
            method: 'POST',
            url: `${app.apiUrl()}/settings`,
            body: { settings: { [SETTING_KEY]: value } },
        }).then(() => {
            app.data.settings[SETTING_KEY] = value;
            this.saving = false;
            m.redraw();
        }).catch(() => {
            this.saving = false;
            m.redraw();
        });
    }

    view() {
        return m('.Form-group.FacebookTagSelector', [
            m('label.label', app.translator.trans('ernestdefoe-facebook-post.admin.tag_selector.title')),
            m('.helpText', [
                app.translator.trans('ernestdefoe-facebook-post.admin.tag_selector.help'),
                ' ',
                m('strong', app.translator.trans('ernestdefoe-facebook-post.admin.tag_selector.help_all')),
            ]),
            this.loading
                ? m(LoadingIndicator, { size: 'small' })
                : this.tags.length === 0
                    ? m('em.FacebookTagSelector-empty', app.translator.trans('ernestdefoe-facebook-post.admin.tag_selector.no_tags'))
                    : m('.FacebookTagSelector-list',
                        this.tags.map(tag => {
                            const id    = String(tag.id());
                            const name  = tag.attribute('name');
                            const color = tag.attribute('color');
                            return m('label.FacebookTagSelector-row', { key: id }, [
                                m('input[type=checkbox]', {
                                    checked:  this.selectedIds.includes(id),
                                    disabled: this.saving,
                                    onchange: () => this.toggle(id),
                                }),
                                color
                                    ? m('span.FacebookTagSelector-pill', { style: `background:${color}` }, name)
                                    : m('span.FacebookTagSelector-name', name),
                            ]);
                        })
                    ),
            this.saving ? m('span.FacebookTagSelector-status', app.translator.trans('ernestdefoe-facebook-post.admin.tag_selector.saving')) : null,
        ]);
    }
}
