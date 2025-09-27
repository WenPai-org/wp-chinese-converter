/*
Powered by WP WP Chinese Converter Plugin  ( https://oogami.name/project/wpcc/ )
Search Variant JS

This JS  try to get your blog's search form element , and append a '<input type="hidden" name="variant" value="VALUE" />' child to this element . So If you run a search , browser will submit the "variant" var to server , the "variant" 's value is set  by your current Chinese Language ( 'zh-hans' for Chinese Simplfied or 'zh-hant' for Chinese Traditional etc...)

If you are in a page with no WP Chinese Converter, this file will not be loaded .

*/

window.addEventListener('load', function () {
  if (typeof wpcc_target_lang == 'undefined') return;

  var theTextNode = document.querySelector('input[name="s"]');
  if (theTextNode) {
    var wpcc_input_variant = document.createElement("input");
    wpcc_input_variant.id = 'wpcc_input_variant';
    wpcc_input_variant.type = 'hidden';
    wpcc_input_variant.name = 'variant';
    wpcc_input_variant.value = wpcc_target_lang;
    theTextNode.parentNode.appendChild(wpcc_input_variant);
  }
});
