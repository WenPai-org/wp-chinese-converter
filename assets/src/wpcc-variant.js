import URI from 'urijs';

const variantSegments = [
  'zh-tw', 'zh-cn', 'zh-sg', 'zh-hant', 'zh-hans',
  'zh-my', 'zh-mo', 'zh-hk', 'zh', 'zh-reset',
];

export function wpccRedirectToPage(select) {
  wpccRedirectToVariant(select.value);
}

export function wpccRedirectToVariant(variant) {
  const uri = new URI(window.location.href);
  const segments = uri.segment().filter((segment) => segment !== '');
  const firstSegment = segments[0];
  const lastSegment = segments[segments.length - 1];
  const permalinkType = window.wpc_switcher_use_permalink_type;

  if (!permalinkType || permalinkType === '0') {
    uri.removeQuery('variant');
    if (variant) {
      uri.addQuery('variant', variant);
    }
  } else if (permalinkType === '1') {
    if (variantSegments.includes(lastSegment)) {
      uri.segment(segments.length - 1, variant);
    } else {
      segments.push(variant);
      uri.segment(segments);
    }
  } else if (permalinkType === '2') {
    if (variantSegments.includes(firstSegment)) {
      uri.segment(0, variant);
    } else {
      segments.unshift(variant);
      uri.segment(segments);
    }
  }

  window.location.href = uri.toString();
}
