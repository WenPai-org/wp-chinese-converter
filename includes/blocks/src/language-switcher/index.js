const { createElement, Fragment } = window.React;
const { registerBlockType } = window.wp.blocks;
const { __ } = window.wp.i18n;
const components = window.wp.components;
const blockEditor = window.wp.blockEditor;

function getAvailableLanguages() {
    const allLanguages = [
        {code:"zh-cn",name:"简体",label:"简体中文"},
        {code:"zh-tw",name:"繁体",label:"台湾正体"},
        {code:"zh-hk",name:"港澳",label:"港澳繁体"},
        {code:"zh-sg",name:"马新",label:"马新简体"},
        {code:"zh-hans",name:"简体",label:"简体中文"},
        {code:"zh-hant",name:"繁体",label:"繁体中文"},
        {code:"zh-jp",name:"日式",label:"日式汉字"}
    ];
    
    if (typeof wpccBlockSettings !== 'undefined' && wpccBlockSettings.enabledLanguages) {
        const enabledCodes = wpccBlockSettings.enabledLanguages;
        const enabledLanguages = allLanguages.filter(lang => enabledCodes.includes(lang.code));
        
        if (wpccBlockSettings.languageLabels) {
            enabledLanguages.forEach(lang => {
                if (wpccBlockSettings.languageLabels[lang.code]) {
                    lang.name = wpccBlockSettings.languageLabels[lang.code];
                }
            });
        }
        
        return enabledLanguages;
    }
    
    return allLanguages;
}

function sortLanguages(languages, sortOrder, showCurrentFirst) {
    let sorted = [...languages];
    
    switch(sortOrder) {
        case 'alphabetical':
            sorted.sort((a, b) => a.name.localeCompare(b.name));
            break;
        case 'frequency':
            const frequencyOrder = ['zh-cn', 'zh-tw', 'zh-hk', 'zh-sg', 'zh-hans', 'zh-hant', 'zh-jp'];
            sorted.sort((a, b) => frequencyOrder.indexOf(a.code) - frequencyOrder.indexOf(b.code));
            break;
        default:
            break;
    }
    
    if (showCurrentFirst) {
        const currentLang = sorted.find(lang => lang.code === 'zh-cn');
        if (currentLang) {
            sorted = [currentLang, ...sorted.filter(lang => lang.code !== 'zh-cn')];
        }
    }
    
    return sorted;
}

registerBlockType("wpcc/language-switcher",{
    edit:function({attributes,setAttributes}){
        const{
            displayStyle,
            showNoConversion,
            alignment,
            enabledLanguages,
            buttonSize,
            showCurrentFirst,
            customNoConversionLabel,
            sortOrder,
            openInNewWindow,
            showLanguageCode,
            customLabels
        }=attributes;
        
        const blockProps=blockEditor.useBlockProps({
            className:`wpcc-language-switcher wpcc-${displayStyle} wpcc-align-${alignment} wpcc-size-${buttonSize}`
        });
        
        const availableLanguages = getAvailableLanguages();
        
        let currentEnabledLanguages = enabledLanguages;
        if (typeof wpccBlockSettings !== 'undefined' && wpccBlockSettings.enabledLanguages) {
            const wpEnabledLanguages = wpccBlockSettings.enabledLanguages;
            currentEnabledLanguages = enabledLanguages.filter(lang => wpEnabledLanguages.includes(lang));
            
            if (currentEnabledLanguages.length === 0) {
                currentEnabledLanguages = wpEnabledLanguages;
                setAttributes({enabledLanguages: wpEnabledLanguages});
            }
        }
        
        const filteredLanguages = availableLanguages.filter(lang => currentEnabledLanguages.includes(lang.code));
        const sortedLanguages = sortLanguages(filteredLanguages, sortOrder, showCurrentFirst);
        
        const getLanguageLabel = (lang) => {
            if (customLabels[lang.code]) {
                return customLabels[lang.code];
            }
            return showLanguageCode ? `${lang.name} (${lang.code})` : lang.name;
        };
        
        return createElement(Fragment,null,
            createElement(blockEditor.InspectorControls,null,
                createElement(components.PanelBody,{title:__("基础设置","wp-chinese-converter")},
                    createElement(components.SelectControl,{
                        label:__("显示样式","wp-chinese-converter"),
                        value:displayStyle,
                        options:[
                            {label:"水平排列",value:"horizontal"},
                            {label:"下拉菜单",value:"dropdown"}
                        ],
                        onChange:value=>setAttributes({displayStyle:value}),
                        __next40pxDefaultSize:true,
                        __nextHasNoMarginBottom:true
                    }),
                    createElement(components.SelectControl,{
                        label:__("按钮大小","wp-chinese-converter"),
                        value:buttonSize,
                        options:[
                            {label:"小",value:"small"},
                            {label:"中",value:"medium"},
                            {label:"大",value:"large"}
                        ],
                        onChange:value=>setAttributes({buttonSize:value}),
                        __next40pxDefaultSize:true,
                        __nextHasNoMarginBottom:true
                    }),
                    createElement(components.ToggleControl,{
                        label:__('显示"不转换"选项',"wp-chinese-converter"),
                        checked:showNoConversion,
                        onChange:value=>setAttributes({showNoConversion:value}),
                        __nextHasNoMarginBottom:true
                    }),
                    createElement(components.ToggleControl,{
                        label:__("显示语言代码","wp-chinese-converter"),
                        checked:showLanguageCode,
                        onChange:value=>setAttributes({showLanguageCode:value}),
                        __nextHasNoMarginBottom:true
                    })
                ),
                createElement(components.PanelBody,{title:__("语言选择","wp-chinese-converter"),initialOpen:false},
                    createElement("p",null,__("选择要显示的语言变体：","wp-chinese-converter")),
                    availableLanguages.length > 0 ? 
                        availableLanguages.map(lang=>createElement(components.CheckboxControl,{
                            key:lang.code,
                            label:lang.label,
                            checked:currentEnabledLanguages.includes(lang.code),
                            onChange:checked=>{
                                const newLanguages=checked?[...currentEnabledLanguages,lang.code]:currentEnabledLanguages.filter(code=>code!==lang.code);
                                setAttributes({enabledLanguages:newLanguages});
                            },
                            __nextHasNoMarginBottom:true
                        })) :
                        createElement("p",{style:{color:"#d63638"}},__("请先在插件设置中启用语言模块","wp-chinese-converter"))
                 ),
                createElement(components.PanelBody,{title:__("排序和显示","wp-chinese-converter"),initialOpen:false},
                    createElement(components.SelectControl,{
                        label:__("排序方式","wp-chinese-converter"),
                        value:sortOrder,
                        options:[
                            {label:"默认顺序",value:"default"},
                            {label:"按字母排序",value:"alphabetical"},
                            {label:"按使用频率",value:"frequency"}
                        ],
                        onChange:value=>setAttributes({sortOrder:value}),
                        __next40pxDefaultSize:true,
                        __nextHasNoMarginBottom:true
                    }),
                    createElement(components.ToggleControl,{
                        label:__("当前语言显示在首位","wp-chinese-converter"),
                        checked:showCurrentFirst,
                        onChange:value=>setAttributes({showCurrentFirst:value}),
                        __nextHasNoMarginBottom:true
                    }),
                    createElement(components.ToggleControl,{
                        label:__("在新窗口打开","wp-chinese-converter"),
                        checked:openInNewWindow,
                        onChange:value=>setAttributes({openInNewWindow:value}),
                        __nextHasNoMarginBottom:true
                    })
                ),
                createElement(components.PanelBody,{title:__("自定义标签","wp-chinese-converter"),initialOpen:false},
                    createElement(components.TextControl,{
                        label:__("自定义不转换标签","wp-chinese-converter"),
                        value:customNoConversionLabel,
                        placeholder:__("不转换","wp-chinese-converter"),
                        onChange:value=>setAttributes({customNoConversionLabel:value}),
                        __next40pxDefaultSize:true,
                        __nextHasNoMarginBottom:true
                    }),
                    createElement("h4",null,__("自定义语言标签","wp-chinese-converter")),
                    availableLanguages.filter(lang=>currentEnabledLanguages.includes(lang.code)).map(lang=>createElement(components.TextControl,{
                        key:lang.code,
                        label:lang.label,
                        value:customLabels[lang.code]||"",
                        placeholder:lang.name,
                        onChange:value=>{
                            const newLabels={...customLabels};
                            if(value){
                                newLabels[lang.code]=value;
                            }else{
                                delete newLabels[lang.code];
                            }
                            setAttributes({customLabels:newLabels});
                        },
                        __next40pxDefaultSize:true,
                        __nextHasNoMarginBottom:true
                    }))
                )
            ),
            createElement("div",blockProps,
                displayStyle==="horizontal"?
                createElement("div",{className:"wpcc-horizontal-switcher"},
                    showNoConversion&&createElement("span",{className:"wpcc-lang-item wpcc-no-conversion"},
                        createElement("a",{
                            href:"#",
                            className:"wpcc-link",
                            target:openInNewWindow?"_blank":"_self"
                        },customNoConversionLabel||"不转换")
                    ),
                    sortedLanguages.map(lang=>createElement("span",{key:lang.code,className:"wpcc-lang-item"},
                        createElement("a",{
                            href:"#",
                            className:"wpcc-link",
                            title:lang.label,
                            target:openInNewWindow?"_blank":"_self"
                        },getLanguageLabel(lang))
                    ))
                ):
                createElement("select",{className:"wpcc-dropdown-switcher"},
                    showNoConversion&&createElement("option",{value:""},customNoConversionLabel||"不转换"),
                    sortedLanguages.map(lang=>createElement("option",{key:lang.code,value:lang.code},getLanguageLabel(lang)))
                )
            )
        );
    },
    save:function({attributes}){
        const{
            displayStyle,
            showNoConversion,
            alignment,
            enabledLanguages,
            buttonSize,
            customNoConversionLabel,
            sortOrder,
            openInNewWindow,
            showLanguageCode,
            customLabels
        }=attributes;
        
        // Keep save output stable, do not depend on runtime settings
        let finalEnabledLanguages = enabledLanguages;
        
        const blockProps=blockEditor.useBlockProps.save({
            className:`wpcc-language-switcher wpcc-${displayStyle} wpcc-align-${alignment} wpcc-size-${buttonSize}`
        });
        
        return createElement("div",blockProps,
            createElement("div",{
                className:"wpcc-switcher-placeholder",
                "data-display-style":displayStyle,
                "data-show-no-conversion":showNoConversion,
                "data-enabled-languages":JSON.stringify(finalEnabledLanguages),
                "data-button-size":buttonSize,
                "data-custom-no-conversion-label":customNoConversionLabel,
                "data-sort-order":sortOrder,
                "data-open-in-new-window":openInNewWindow,
                "data-show-language-code":showLanguageCode,
                "data-custom-labels":JSON.stringify(customLabels)
            },"")
        );
    }
});
