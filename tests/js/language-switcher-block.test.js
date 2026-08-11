'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let registeredBlock;
const createElement = (type, props, ...children) => ({ type, props: props || {}, children });
const useBlockProps = (props) => props;
useBlockProps.save = (props) => props;

const context = {
  window: {
    React: { createElement, Fragment: 'Fragment' },
    wp: {
      blocks: {
        registerBlockType(name, definition) {
          assert.equal(name, 'wpcc/language-switcher');
          registeredBlock = definition;
        },
      },
      i18n: { __: (text) => text },
      components: {
        PanelBody: 'PanelBody',
        SelectControl: 'SelectControl',
        ToggleControl: 'ToggleControl',
        CheckboxControl: 'CheckboxControl',
        TextControl: 'TextControl',
      },
      blockEditor: { InspectorControls: 'InspectorControls', useBlockProps },
    },
  },
};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(
  fs.readFileSync(path.join(__dirname, '../../includes/blocks/build/language-switcher/index.js'), 'utf8'),
  context,
);

assert.ok(registeredBlock, 'block must register');
const attributes = {
  displayStyle: 'horizontal',
  showNoConversion: true,
  alignment: 'left',
  enabledLanguages: ['zh-cn', 'zh-tw'],
  buttonSize: 'medium',
  showCurrentFirst: false,
  customNoConversionLabel: '',
  sortOrder: 'default',
  openInNewWindow: false,
  showLanguageCode: false,
  customLabels: {},
};
assert.doesNotThrow(() => registeredBlock.edit({ attributes, setAttributes() {} }));
assert.doesNotThrow(() => registeredBlock.save({ attributes }));
assert.equal(registeredBlock.edit({ attributes, setAttributes() {} }).type, 'Fragment');
assert.equal(registeredBlock.save({ attributes }).type, 'div');
console.log('language switcher block edit/save behavior passed');
