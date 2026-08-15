const { createElement } = window.React;
const { registerBlockType } = window.wp.blocks;
const { __ } = window.wp.i18n;
const { InnerBlocks, useBlockProps } = window.wp.blockEditor;

registerBlockType('wpcc/no-conversion', {
  edit() {
    const blockProps = useBlockProps({ className: 'wpcc-no-conversion-wrapper' });
    return createElement(
      'div',
      blockProps,
      createElement(
        'div',
        { className: 'wpcc-no-conversion-header' },
        createElement(
          'span',
          { className: 'wpcc-label' },
          __('不转换内容区域', 'wp-chinese-converter'),
        ),
      ),
      createElement(
        'div',
        { className: 'wpcc-no-conversion-content' },
        createElement(InnerBlocks, {
          placeholder: __('在此添加不需要转换的内容...', 'wp-chinese-converter'),
          templateLock: false,
        }),
      ),
    );
  },
  save() {
    const blockProps = useBlockProps.save({
      className: 'wpcc-no-conversion-wrapper',
      'data-wpcc-no-conversion': 'true',
    });
    return createElement(
      'div',
      blockProps,
      createElement(
        'div',
        { className: 'wpcc-no-conversion-content' },
        createElement(InnerBlocks.Content),
      ),
    );
  },
});
