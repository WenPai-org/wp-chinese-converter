'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function loadScript(path, overrides = {}) {
  const location = { href: 'https://example.test/article/?variant=zh-tw' };
  const context = {
    URL,
    console,
    window: { location },
    document: {
      addEventListener() {},
      querySelectorAll() { return []; },
    },
    wpcc_target_lang: 'zh-tw',
    wpcc_noconversion_url: 'https://example.test/article/',
    wpcc_langs_urls: {
      zh: 'https://example.test/article/?variant=zh',
      'zh-tw': 'https://example.test/article/?variant=zh-tw',
    },
    ...overrides,
  };
  vm.createContext(context);
  vm.runInContext(fs.readFileSync(path, 'utf8'), context, { filename: path });
  return context;
}

const wrapper = loadScript('assets/js/wpcc-block-script-ok.js', {
  WPCSVariant: {
    wpccRedirectToVariant(value) {
      throw new Error(`compiled redirect should not run for blank value: ${value}`);
    },
  },
});
wrapper.wpccRedirectToPage({ value: '' });
assert.equal(
  wrapper.window.location.href,
  'https://example.test/article/?variant=zh',
  'classic dropdown should force the no-conversion sentinel',
);

const fallback = loadScript('assets/js/wpcc-block-script-ok.js', {
  wpcc_langs_urls: {},
  wpcc_noconversion_url: 'https://example.test/article/?preview=true',
});
fallback.wpccRedirectToVariant('');
assert.equal(
  fallback.window.location.href,
  'https://example.test/article/?preview=true&variant=zh',
  'fallback should preserve unrelated query parameters and add the sentinel',
);

const blocks = loadScript('assets/js/blocks-frontend.js');
assert.equal(
  blocks.getLanguageUrl(''),
  'https://example.test/article/?variant=zh',
  'block dropdown should use the same explicit no-conversion URL',
);

console.log('variant reset tests passed');
