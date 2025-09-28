(()=>{"use strict";const e=window.React,c=window.wp.blocks,n=window.wp.i18n,t=window.wp.blockEditor;

function generateUniqueId() {
    return Math.floor(Math.random() * 10000) + Date.now();
}

(0,c.registerBlockType)("wpcc/no-conversion",{
    edit:function({attributes,setAttributes}){
        const blockProps=(0,t.useBlockProps)({
            className:"wpcc-no-conversion-wrapper"
        });
        
        return(0,e.createElement)("div",blockProps,
            (0,e.createElement)("div",{className:"wpcc-no-conversion-header"},
                (0,e.createElement)("span",{className:"wpcc-label"},
                    (0,n.__)("不转换内容区域","wp-chinese-converter")
                )
            ),
            (0,e.createElement)("div",{className:"wpcc-no-conversion-content"},
                (0,e.createElement)(t.InnerBlocks,{
                    placeholder:(0,n.__)("在此添加不需要转换的内容...","wp-chinese-converter"),
                    templateLock:false
                })
            )
        );
    },
    save:function({attributes}){
        const blockProps=t.useBlockProps.save({
            className:"wpcc-no-conversion-wrapper",
            "data-wpcc-no-conversion":"true"
        });
        
        return(0,e.createElement)("div",blockProps,
            (0,e.createElement)("div",{className:"wpcc-no-conversion-content"},
                (0,e.createElement)(t.InnerBlocks.Content,null)
            )
        );
    }
});})();