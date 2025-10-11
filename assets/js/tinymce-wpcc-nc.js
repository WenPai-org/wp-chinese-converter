(function() {
  if (typeof window.tinymce === 'undefined') {
    return;
  }

  tinymce.PluginManager.add('wpcc_nc', function(editor, url) {
    function insertShortcode() {
      var sel = editor.selection ? editor.selection.getContent({ format: 'text' }) : '';
      var before = '[wpcc_nc]';
      var after = '[/wpcc_nc]';
      var content = before + (sel || '') + after;
      editor.insertContent(content);
    }

    // TinyMCE 5+ API
    if (editor.ui && editor.ui.registry && editor.ui.registry.addButton) {
      editor.ui.registry.addButton('wpcc_nc', {
        text: 'wpcc_NC',
        tooltip: '插入不转换包裹 [wpcc_nc]...[/wpcc_nc]',
        onAction: insertShortcode
      });
      editor.ui.registry.addMenuItem('wpcc_nc', {
        text: '插入不转换包裹',
        onAction: insertShortcode
      });
    } else if (editor.addButton) {
      // TinyMCE 4 API (WordPress Classic Editor)
      editor.addButton('wpcc_nc', {
        text: 'wpcc_NC',
        tooltip: '插入不转换包裹 [wpcc_nc]...[/wpcc_nc]',
        onclick: insertShortcode
      });
    }

    return {
      getMetadata: function () {
        return {
          name: 'WPCC No-Conversion Shortcode Helper',
          url: 'https://wpcc.net'
        };
      }
    };
  });
})();
