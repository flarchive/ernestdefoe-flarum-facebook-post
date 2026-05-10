import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import TagSelector from './admin/TagSelector';

app.initializers.add('ernestdefoe-facebook-post', () => {
    // sections() returns an ItemList with 'content' (priority 100) and
    // 'permissions' (priority 60). We slot the tag selector between them.
    extend(ExtensionPage.prototype, 'sections', function (items) {
        if (this.attrs.id !== 'ernestdefoe-facebook-post') return;
        items.add('tag-filter', m('.ExtensionPage-settings', m(TagSelector)), 80);
    });
});
