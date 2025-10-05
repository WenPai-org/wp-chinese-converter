var wpc_switcher_use_permalink_type;
if (typeof wpc_switcher_use_permalink !== 'undefined') {
    wpc_switcher_use_permalink_type = wpc_switcher_use_permalink['type'];
}

function wpccRedirectToPage($event) {
    if (typeof WPCSVariant === 'undefined') return;
    return WPCSVariant.wpccRedirectToPage($event);
}

function wpccRedirectToVariant(variantValue) {
  if (typeof WPCSVariant === 'undefined') return;
  return WPCSVariant.wpccRedirectToVariant(variantValue);
}
