import grapesjs from 'grapesjs';
import newsletter from 'grapesjs-preset-newsletter';
import 'grapesjs/dist/css/grapes.min.css';

const root = document.getElementById('b-email-editor');
if (root) {
  const projectInput = document.getElementById('email-project');
  const htmlInput = document.getElementById('email-html');
  const data = document.getElementById('email-template-data');
  const state = JSON.parse(data.textContent || '{}');
  const editor = grapesjs.init({
    container: root,
    telemetry: false,
    cssIcons: '/assets/css/font-awesome.min.css',
    height: '640px',
    width: 'auto',
    fromElement: false,
    storageManager: false,
    assetManager: { upload: false, assets: [] },
    plugins: [editor => newsletter(editor, { inlineCss: true, blocks: ['sect100','sect50','sect30','sect37','button','divider','text','text-sect','image','quote','grid-items','list-items'] })],
    components: state.html || '<div><p>Bulbula.et</p><h1>Your email</h1><p>{{message}}</p></div>',
  });
  if (state.project) {
    try { editor.loadProjectData(state.project); } catch (error) { console.warn('Saved project could not be loaded; showing saved HTML.', error); }
  }
  const form = document.getElementById('email-save-form');
  form.addEventListener('submit', (event) => {
    try {
      htmlInput.value = editor.runCommand('gjs-get-inlined-html');
      projectInput.value = JSON.stringify(editor.getProjectData());
      if (!htmlInput.value || !projectInput.value) throw new Error('The design is empty.');
    } catch (error) {
      event.preventDefault();
      document.getElementById('email-editor-status').textContent = 'Could not export the design. Please review your changes.';
    }
  });
  document.getElementById('email-preview')?.addEventListener('click', () => {
    try {
      const html = editor.runCommand('gjs-get-inlined-html');
      const sample = (html || '').replace(/{{code}}/g, '123456').replace(/{{minutes}}/g, '10').replace(/{{message}}/g, 'Sample Bulbula.et account update. No real user data is shown.').replace(/{{name}}/g, 'Sample customer');
      // Null-origin sandbox and CSP: even a draft containing malicious HTML cannot run scripts.
      document.getElementById('email-preview-frame').srcdoc = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src https:">' + sample;
      document.getElementById('email-preview-wrap').hidden = false;
    } catch (_) { document.getElementById('email-editor-status').textContent = 'Preview unavailable; save the draft to validate it.'; }
  });
}
