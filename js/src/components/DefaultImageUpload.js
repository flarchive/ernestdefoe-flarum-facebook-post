import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';

export default class DefaultImageUpload extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.uploading = false;
    this.currentUrl = app.data.settings['ernestdefoe-facebook-post.default_image_url'] || null;
    this.error = null;
  }

  view() {
    return m('.Form-group', [
      m('label', 'Default Facebook Image'),
      m('.helpText', 'Uploaded when a new discussion has no image in its first post.'),

      this.currentUrl &&
        m('.facebook-post-preview', [
          m('img', { src: this.currentUrl, alt: 'Default image preview' }),
        ]),

      m('input', {
        type: 'file',
        accept: 'image/jpeg,image/png,image/gif,image/webp',
        disabled: this.uploading,
        onchange: (e) => this.upload(e.target.files[0]),
      }),

      this.uploading && m('p.facebook-post-uploading', 'Uploading…'),
      this.error && m('p.facebook-post-error', this.error),
    ]);
  }

  async upload(file) {
    if (!file) return;

    this.uploading = true;
    this.error = null;
    m.redraw();

    const formData = new FormData();
    formData.append('image', file);

    try {
      const response = await fetch(app.apiUrl() + '/facebook-post/default-image', {
        method: 'POST',
        headers: {
          'X-CSRF-Token': app.session.csrfToken,
        },
        body: formData,
      });

      const data = await response.json();

      if (!response.ok) {
        this.error = data.error || 'Upload failed.';
      } else {
        this.currentUrl = data.url;
        app.data.settings['ernestdefoe-facebook-post.default_image_url'] = data.url;
      }
    } catch (e) {
      this.error = 'Network error during upload.';
    } finally {
      this.uploading = false;
      m.redraw();
    }
  }
}
