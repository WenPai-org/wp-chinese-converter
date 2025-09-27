<?php

/**
 *wp super cache plugin. Add basic compability with "php cache" mode of wp super cache plugin. Experimental!
 *
 *Don't edit this file manully.
 *
 * @since 1.1.11
 *
 */

$GLOBALS['wp_supercache_wpcc_version'] = '1.0';

function wp_supercache_wpcc_get_prefered_language($accept_languages, $target_langs, $flag = 0) {
    $langs = array();
    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $accept_languages, $lang_parse);

    if (count($lang_parse[1])) {
        $langs = array_combine($lang_parse[1], $lang_parse[4]);//array_combine需要php5以上版本
        foreach ($langs as $lang => $val) {
            if ($val === '') {
                $langs[$lang] = '1';
            }
        }
        arsort($langs, SORT_NUMERIC);
        $langs = array_keys($langs);
        $langs = array_map('strtolower', $langs);

        foreach ($langs as $val) {
            if (in_array($val, $target_langs)) {
                return $val;
            }
        }

        if ($flag) {
            $array = array('zh-hans', 'zh-cn', 'zh-sg', 'zh-my');
            $a     = array_intersect($array, $target_langs);
            if ( ! empty($a)) {
                $b = array_intersect($array, $langs);
                if ( ! empty($b)) {
                    return reset($a);
                }
            }

            $array = array('zh-hant', 'zh-tw', 'zh-hk', 'zh-mo');
            $a     = array_intersect($array, $target_langs);
            if ( ! empty($a)) {
                $b = array_intersect($array, $langs);
                if ( ! empty($b)) {
                    return reset($a);
                }
            }
        }

        return 'null';
    }

    return 'null';
}

function wp_supercache_wpcc_admin() {
    $id = 'wpcc-section';
    ?>
  <fieldset id="<?php echo $id; ?>" class="options">
    <h4>WP WP Chinese Converter</h4>
    <p>Wp super cache - WP WP Chinese Converter plugin is activated. To uninstall it, go to WP WP Chinese Converter plugin
      setting page.</p>
  </fieldset>
    <?php
}

add_cacheaction('cache_admin_page', 'wp_supercache_wpcc_admin');

function wp_supercache_wpcc_cache_key($cachekey) {
    global $wpcc_options;

    $browser_lang = wp_supercache_wpcc_get_prefered_language(
        ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '' ), 
        $wpcc_options['wpcc_used_langs'], 
        $wpcc_options['wpcc_auto_language_recong']
    );

    $cookie_lang = 'null';
    foreach ($_COOKIE as $key => $val) {
        if (preg_match("/^wpcc_variant_/", $key)) {
            $cookie_lang = $val;
            break;
        }
    }

    $is_redirect = 'direct';
    foreach ($_COOKIE as $key => $val) {
        if (preg_match("/^wpcc_is_redirect_/", $key)) {
            $is_redirect = 'redirect';
            break;
        }
    }

    return $cachekey . '_' . $browser_lang . '_' . $cookie_lang . '_' . $is_redirect;
}

add_cacheaction('wp_cache_key', 'wp_supercache_wpcc_cache_key');
