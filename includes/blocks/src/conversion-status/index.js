const { createElement, Fragment } = window.React;
const { registerBlockType } = window.wp.blocks;
const { __ } = window.wp.i18n;
const { PanelBody, SelectControl, ToggleControl } = window.wp.components;
const { InspectorControls, useBlockProps } = window.wp.blockEditor;

registerBlockType('wpcc/conversion-status', {
  edit({ attributes, setAttributes }) {
    const { showIcon, displayFormat } = attributes;
    const blockProps = useBlockProps({
      className: `wpcc-conversion-status wpcc-format-${displayFormat}`,
    });

    return createElement(
      Fragment,
      null,
      createElement(
        InspectorControls,
        null,
        createElement(
          PanelBody,
          { title: __('状态指示器设置', 'wp-chinese-converter') },
          createElement(SelectControl, {
            label: __('显示格式', 'wp-chinese-converter'),
            value: displayFormat,
            options: [
              { label: '徽章样式', value: 'badge' },
              { label: '文本样式', value: 'text' },
              { label: '简洁样式', value: 'minimal' },
            ],
            onChange: (value) => setAttributes({ displayFormat: value }),
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true,
          }),
          createElement(ToggleControl, {
            label: __('显示图标', 'wp-chinese-converter'),
            checked: showIcon,
            onChange: (value) => setAttributes({ showIcon: value }),
            __nextHasNoMarginBottom: true,
          }),
        ),
      ),
      createElement(
        'div',
        blockProps,
        createElement(
          'div',
          { className: 'wpcc-status-preview' },
          createElement(
            'span',
            { className: 'wpcc-status-text' },
            displayFormat === 'badge' && '简体中文',
            displayFormat === 'text' && '当前语言：简体中文',
            displayFormat === 'minimal' && '简体',
          ),
        ),
      ),
    );
  },
  save({ attributes }) {
    const { showIcon, displayFormat } = attributes;
    const blockProps = useBlockProps.save({
      className: `wpcc-conversion-status wpcc-format-${displayFormat}`,
    });
    return createElement(
      'div',
      blockProps,
      createElement(
        'div',
        {
          className: 'wpcc-status-container',
          'data-show-icon': showIcon,
          'data-display-format': displayFormat,
        },
        __('转换状态指示器', 'wp-chinese-converter'),
      ),
    );
  },
});
