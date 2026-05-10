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
            // Primary tags only — no parent
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
        return m('.Form', m('.Form-group.FacebookTagSelector', [
            m('label.label', 'Post to Facebook for Primary Tags'),
            m('.helpText', [
                'Select which primary tags trigger a Facebook post. ',
                m('strong', 'Leave all unchecked to post every new discussion regardless of tag.'),
            ]),
            this.loading
                ? m(LoadingIndicator, { size: 'small' })
                : this.tags.length === 0
                    ? m('em.FacebookTagSelector-empty', 'No primary tags found. Install and configure the Flarum Tags extension first.')
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
            this.saving ? m('span.FacebookTagSelector-status', 'Saving…') : null,
        ]));
    }
}
