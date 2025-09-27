(function() {
    'use strict';

    if (typeof wp === 'undefined' || !wp.blocks) {
        return;
    }

    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;
    const { __ } = wp.i18n;

    registerBlockType('wpcc/language-switcher', {
        title: '中文语言切换器',
        description: '显示中文简繁体语言切换器',
        icon: 'translation',
        category: 'common',
        keywords: ['语言', '中文', '简繁'],
        supports: {
            html: false,
        },
        
        edit: function() {
            return el('div', {
                style: {
                    padding: '20px',
                    border: '1px dashed #ccc',
                    textAlign: 'center',
                    backgroundColor: '#f9f9f9'
                }
            }, '中文语言切换器 - 编辑模式');
        },

        save: function() {
            return null;
        }
    });

    registerBlockType('wpcc/conversion-indicator', {
        title: '转换状态指示器',
        description: '显示当前页面的中文转换状态',
        icon: 'info',
        category: 'common',
        keywords: ['转换', '状态', '指示器'],
        supports: {
            html: false,
        },
        
        edit: function() {
            return el('div', {
                style: {
                    padding: '20px',
                    border: '1px dashed #ccc',
                    textAlign: 'center',
                    backgroundColor: '#f9f9f9'
                }
            }, '转换状态指示器 - 编辑模式');
        },

        save: function() {
            return null;
        }
    });

})();
