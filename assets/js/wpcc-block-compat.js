(function(){
  if (!window.wp || !wp.blocks || !wp.blockEditor || !wp.element || !wp.domReady) return;
  const el = wp.element.createElement;
  const useBlockProps = wp.blockEditor.useBlockProps;

  // Deprecated saver for Traditional Chinese placeholder (語言切換器)
  function saveLanguageSwitcherDeprecated(props){
    const {
      displayStyle,
      showNoConversion,
      alignment,
      enabledLanguages,
      buttonSize,
      customNoConversionLabel,
      sortOrder,
      openInNewWindow,
      showLanguageCode,
      customLabels,
    } = props.attributes;

    let finalEnabledLanguages = Array.isArray(enabledLanguages) ? enabledLanguages : [];
    if (typeof wpccBlockSettings !== 'undefined' && Array.isArray(wpccBlockSettings.enabledLanguages)) {
      const base = wpccBlockSettings.enabledLanguages;
      const filtered = finalEnabledLanguages.filter(code => base.includes(code));
      finalEnabledLanguages = filtered.length ? filtered : base;
    }

    const blockProps = useBlockProps.save({
      className: `wpcc-language-switcher wpcc-${displayStyle} wpcc-align-${alignment} wpcc-size-${buttonSize}`
    });

    return el('div', blockProps,
      el('div', {
        className: 'wpcc-switcher-placeholder',
        'data-display-style': displayStyle,
        'data-show-no-conversion': !!showNoConversion,
        'data-enabled-languages': JSON.stringify(finalEnabledLanguages),
        'data-button-size': buttonSize,
        'data-custom-no-conversion-label': customNoConversionLabel || '',
        'data-sort-order': sortOrder,
        'data-open-in-new-window': !!openInNewWindow,
        'data-show-language-code': !!showLanguageCode,
        'data-custom-labels': JSON.stringify(customLabels || {})
      }, '語言切換器')
    );
  }

  function saveConversionStatusDeprecated(props){
    const { showIcon, displayFormat } = props.attributes;
    const blockProps = useBlockProps.save({
      className: `wpcc-conversion-status wpcc-format-${displayFormat}`
    });
    return el('div', blockProps,
      el('div', {
        className: 'wpcc-status-container',
        'data-show-icon': !!showIcon,
        'data-display-format': displayFormat
      }, '轉換狀態指示器')
    );
  }

  wp.domReady(function(){
    const { getBlockType, unregisterBlockType, registerBlockType } = wp.blocks;

    function extendDeprecated(name, saveFn){
      const type = getBlockType(name);
      if (!type) return;
      const deprecated = Array.isArray(type.deprecated) ? type.deprecated.slice() : [];
      deprecated.push({ save: saveFn });
      const settings = Object.assign({}, type, { deprecated });
      unregisterBlockType(name);
      registerBlockType(name, settings);
    }

    extendDeprecated('wpcc/language-switcher', saveLanguageSwitcherDeprecated);
    extendDeprecated('wpcc/conversion-status', saveConversionStatusDeprecated);
  });
})();
