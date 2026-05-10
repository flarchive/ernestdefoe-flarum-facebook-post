import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import TagSelector from './admin/TagSelector';

app.initializers.add('ernestdefoe-facebook-post', () => {
    extend(ExtensionPage.prototype, 'content', function (content) {
        const extId = (this.extension && this.extension.id) || this.attrs.id;
        if (extId !== 'ernestdefoe-facebook-post') return;
        if (!Array.isArray(content)) return;

        // Insert tag selector between the settings section (0) and permissions section (1)
        content.splice(1, 0, m(TagSelector));
    });
});
