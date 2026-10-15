// Dependency-free runtime smoke tests. These mocks are not real Telegram clients.
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const code = fs.readFileSync(path.join(__dirname, '../public_html/assets/js/core/surface-adapter.js'), 'utf8');

function boot(bridge) {
  const callbacks = {};
  const document = {
    documentElement: { dataset: {} },
    addEventListener: (event, fn) => { callbacks[event] = fn; },
    querySelectorAll: () => [],
    dispatchEvent: () => {}
  };
  const window = {
    Telegram: bridge ? { WebApp: bridge } : undefined,
    location: { pathname: '/', origin: 'https://example.test' }
  };
  const context = { window, document, CustomEvent: class {}, navigator: {} };
  vm.runInNewContext(code, context, { filename: 'surface-adapter.js' });
  callbacks.DOMContentLoaded();
  return { surface: window.BulbulaSurface, dataset: document.documentElement.dataset };
}

let instance = boot(null);
assert.equal(instance.surface.isTelegramNative, false);
assert.equal(instance.dataset.surface, 'web');

// The official bridge can load in any browser; its default platform is unknown.
let called = 0;
instance = boot({ initData: '', platform: 'unknown', ready: () => called++ });
assert.equal(instance.surface.isTelegramNative, false);
assert.equal(instance.dataset.surface, 'web');
assert.equal(called, 0);

// Official Telegram launch modes can have empty initData; platform capability
// must still initialize the UI, without granting Customer identity.
const actions = [];
instance = boot({
  initData: '', platform: 'ios',
  ready: () => actions.push('ready'), expand: () => actions.push('expand'),
  setHeaderColor: () => actions.push('header'), setBackgroundColor: () => actions.push('background'),
  HapticFeedback: { impactOccurred: () => actions.push('haptic') },
  openTelegramLink: (url) => actions.push(url)
});
assert.equal(instance.surface.isTelegramNative, true);
assert.equal(instance.dataset.surface, 'telegram-mini-app');
assert.equal(instance.dataset.telegramPlatform, 'ios');
assert.deepEqual(actions.slice(0, 4), ['ready', 'expand', 'header', 'background']);
instance.surface.hapticImpact('light');
assert.equal(actions[4], 'haptic');
instance.surface.shareCanonical('A clinic', 'https://example.test/b/clinic/branch', null);
assert.match(actions[5], /^https:\/\/t\.me\/share\/url\?/);
assert.ok(!actions[5].includes('initData'));

// Preserve support for clients that supply initData but no platform string.
instance = boot({ initData: 'query_id=sample', platform: 'unknown', ready: () => {} });
assert.equal(instance.surface.isTelegramNative, true);
console.log('[PASS] Web, default bridge, empty-data Telegram and data-bearing Telegram capability checks. Real-device acceptance remains open.');
