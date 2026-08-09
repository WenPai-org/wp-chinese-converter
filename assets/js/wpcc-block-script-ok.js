var wpc_switcher_use_permalink_type;
if (typeof wpc_switcher_use_permalink !== 'undefined') {
    wpc_switcher_use_permalink_type = wpc_switcher_use_permalink['type'];
}

/**
 * Build an explicit no-conversion URL. The `zh` sentinel is required because
 * merely removing `variant` lets browser and cookie preferences convert the
 * freshly loaded page again.
 *
 * @return {string} URL that forces WordPress to render the original content.
 */
function wpccGetNoConversionUrl() {
    if (
        typeof wpcc_langs_urls === 'object' &&
        wpcc_langs_urls &&
        typeof wpcc_langs_urls.zh === 'string' &&
        wpcc_langs_urls.zh
    ) {
        return wpcc_langs_urls.zh;
    }

    var baseUrl =
        typeof wpcc_noconversion_url === 'string' && wpcc_noconversion_url
            ? wpcc_noconversion_url
            : window.location.href;

    try {
        var url = new URL(baseUrl, window.location.href);
        url.searchParams.delete('variant');
        url.pathname = url.pathname.replace(
            /^\/(zh(?:-[a-z]+)?|zh-reset)(?:\/|$)/i,
            '/',
        );
        url.searchParams.set('variant', 'zh');
        return url.toString();
    } catch (error) {
        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
        return baseUrl + separator + 'variant=zh';
    }
}

function wpccRedirectToPage($event) {
    var variantValue = $event && typeof $event.value === 'string' ? $event.value : '';
    return wpccRedirectToVariant(variantValue);
}

function wpccRedirectToVariant(variantValue) {
    if (!variantValue) {
        window.location.href = wpccGetNoConversionUrl();
        return;
    }

    if (typeof WPCSVariant === 'undefined') return;
    return WPCSVariant.wpccRedirectToVariant(variantValue);
}
