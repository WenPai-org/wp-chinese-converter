<?php
/*
* Plugin Name: WP Chinese Switcher
* Description: Adds the language conversion function between Chinese Simplified and Chinese Traditional to your WP Website.
* Author: WenPai.org
* Author URI: https://wenpai.org
* Text Domain: wpchinese-switcher
* Domain Path: /languages
* Version: 1.0.0
* License: GPLv3 or later
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/*
Copyright 2012-2021 WenPai (http://wenpai.org)
Developer: WenPai

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * WP Chinese Switcher Plugin main file
 *
 * 为Wordpress增加中文繁简转换功能. 转换过程在服务器端完成. 使用的繁简字符映射表来源于Mediawiki.
 * 本插件比较耗费资源. 因为对页面内容繁简转换时载入了一个几百KB的转换表(ZhConversion.php), 编译后占用内存超过1.5MB
 * 如果可能, 建议安装xcache/ eAccelerator之类PHP缓存扩展. 可以有效提高速度并降低CPU使用,在生产环境下测试效果非常显着.
 *
 * @package WPCS
 * @version see wpcs_VERSION constant below
 * @TODO 用OO方式重写全部代码, 计划1.2版本实现.
 * @link http://wordpress.org/plugins/wpchinese-switcher Plugin Page on wordpress.org, including guides and docs.
 *
 */

//define('wpcs_DEBUG', true); $wpcs_deubg_data = array(); //uncomment this line to enable debug
define('wpcs_DIR_URL', WP_PLUGIN_URL . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)));
define('wpcs_VERSION', '1.0');

$wpcs_options = get_option('wpcs_options');
// /**********************
//初始化所有全局变量.其实不初始化也没关系,主要是防止某些古董php版本register_globals打开可能造成意想不到问题.
$wpcs_admin                  = false;
$wpcs_noconversion_url       = false;
$wpcs_redirect_to            = false;
$wpcs_direct_conversion_flag = false;
$wpcs_langs_urls             = array();
$wpcs_target_lang            = false;
// ***************************/

//您可以更改提示文本,如"简体中文","繁体中文".但是不要改动其它.
//不要改键值的语言代码zh-xx, 本插件一些地方使用了硬编码的语言代码.
$wpcs_langs = array(
    'zh-cn' => array('zhconversion_cn', 'cntip', '简体中文', 'zh-CN'),
    'zh-tw' => array('zhconversion_tw', 'twtip', '繁体中文', 'zh-TW'),
    /*
'zh-hans' => array('zhconversion_hans', 'hanstip', '简体中文','zh-Hans'),
'zh-hant' => array('zhconversion_hant', 'hanttip', '繁体中文','zh-Hant'),
'zh-hk' => array('zhconversion_hk', 'hktip', '港澳繁体','zh-HK'),
'zh-mo' => array('zhconversion_hk', 'motip', '澳门繁体','zh-MO'),
'zh-sg' => array('zhconversion_sg', 'sgtip', '马新简体','zh-SG'),
'zh-my' => array('zhconversion_sg', 'mytip', '马来西亚简体','zh-MY'),
    */
);

//容错处理.
if ($wpcs_options != false && is_array($wpcs_options) && is_array($wpcs_options['wpcs_used_langs'])) {
    add_action('widgets_init', function () {
        return register_widget('wpcs_Widget');
    }, 1);
    add_filter('query_vars', 'wpcs_insert_query_vars');//修改query_vars钩子,增加一个'variant'公共变量.
    add_action('init', 'wpcs_init');//插件初始化

    if (
        WP_DEBUG ||
        (defined('wpcs_DEBUG') && wpcs_DEBUG == true)
    ) {
        add_action('init', function () {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        });
        add_action('wp_footer', 'wpcs_debug');
    }
}

add_action('admin_menu', 'wpcs_admin_init');//插件后台菜单钩子


/* 全局代码END; 下面的全是函数定义 */

/**
 * 插件初始化
 *
 * 本函数做了下面事情:
 * A. 调用wpcs_get_noconversion_url函数设置 $wpcs_noconversion_url全局变量
 * A. 调用wpcs_get_lang_url函数设置 $wpcs_langs_urls全局(数组)变量
 * B. 如果当前为POST方式提交评论请求, 直接调用wpcs_do_conversion
 * B. 否则, 加载parse_request接口
 */
function wpcs_init() {
    global $wpcs_options, $wp_rewrite;

    if ($wpcs_options['wpcs_use_permalink'] != 0 && empty($wp_rewrite->permalink_structure)) {
        $wpcs_options['wpcs_use_permalink'] = 0;
        update_option('wpcs_options', $wpcs_options);
    }
    if ($wpcs_options['wpcs_use_permalink'] != 0) {
        add_filter('rewrite_rules_array', 'wpcs_rewrite_rules');
    }

    if ((strpos($_SERVER['PHP_SELF'], 'wp-comments-post.php') !== false
         || strpos($_SERVER['PHP_SELF'], 'ajax-comments.php') !== false
         || strpos($_SERVER['PHP_SELF'], 'comments-ajax.php') !== false
        ) &&
        $_SERVER["REQUEST_METHOD"] == "POST" &&
        ! empty($_POST['variant']) && in_array($_POST['variant'], $wpcs_options['wpcs_used_langs'])
    ) {
        global $wpcs_target_lang;
        $wpcs_target_lang = $_POST['variant'];
        wpcs_do_conversion();

        return;
    }

    if ('page' == get_option('show_on_front') && get_option('page_on_front')) {
        add_action('parse_query', 'wpcs_parse_query_fix');
    }
    add_action('parse_request', 'wpcs_parse_query');
    add_action('template_redirect', 'wpcs_template_redirect', - 100);//本插件核心代码.
}

/**
 * 修复首页显示Page时繁简转换页仍然显示最新posts的问题
 * dirty but should works
 * based on wp 3.5
 * @since 1.1.13
 * @see wp-include/query.php
 *
 */
function wpcs_parse_query_fix($this_WP_Query) {

    //copied and modified from wp-includes/query.php
    $qv = &$this_WP_Query->query_vars;

    // Correct is_* for page_on_front and page_for_posts
    if ($this_WP_Query->is_home && 'page' == get_option('show_on_front') && get_option('page_on_front')) {
        $_query = wp_parse_args($this_WP_Query->query);
        // pagename can be set and empty depending on matched rewrite rules. Ignore an empty pagename.
        if (isset($_query['pagename']) && '' == $_query['pagename']) {
            unset($_query['pagename']);
        }
        if (empty($_query) || ! array_diff(array_keys($_query), array(
                'preview',
                'page',
                'paged',
                'cpage',
                'variant'
            ))) {
            $this_WP_Query->is_page = true;
            $this_WP_Query->is_home = false;
            $qv['page_id']          = get_option('page_on_front');
            // Correct <!--nextpage--> for page_on_front
            if ( ! empty($qv['paged'])) {
                $qv['page'] = $qv['paged'];
                unset($qv['paged']);
            }
        }
    }

    if ('' != $qv['pagename']) {
        $this_WP_Query->queried_object = get_page_by_path($qv['pagename']);
        if ( ! empty($this_WP_Query->queried_object)) {
            $this_WP_Query->queried_object_id = (int) $this_WP_Query->queried_object->ID;
        } else {
            unset($this_WP_Query->queried_object);
        }

        if ('page' == get_option('show_on_front') && isset($this_WP_Query->queried_object_id) && $this_WP_Query->queried_object_id == get_option('page_for_posts')) {
            $this_WP_Query->is_page       = false;
            $this_WP_Query->is_home       = true;
            $this_WP_Query->is_posts_page = true;
        }
    }

    if ($qv['page_id']) {
        if ('page' == get_option('show_on_front') && $qv['page_id'] == get_option('page_for_posts')) {
            $this_WP_Query->is_page       = false;
            $this_WP_Query->is_home       = true;
            $this_WP_Query->is_posts_page = true;
        }
    }

    if ( ! empty($qv['post_type'])) {
        if (is_array($qv['post_type'])) {
            $qv['post_type'] = array_map('sanitize_key', $qv['post_type']);
        } else {
            $qv['post_type'] = sanitize_key($qv['post_type']);
        }
    }

    if ( ! empty($qv['post_status'])) {
        if (is_array($qv['post_status'])) {
            $qv['post_status'] = array_map('sanitize_key', $qv['post_status']);
        } else {
            $qv['post_status'] = preg_replace('|[^a-z0-9_,-]|', '', $qv['post_status']);
        }
    }

    if ($this_WP_Query->is_posts_page && ( ! isset($qv['withcomments']) || ! $qv['withcomments'])) {
        $this_WP_Query->is_comment_feed = false;
    }

    $this_WP_Query->is_singular = $this_WP_Query->is_single || $this_WP_Query->is_page || $this_WP_Query->is_attachment;
    // Done correcting is_* for page_on_front and page_for_posts

    /*
        if ( '404' == $qv['error'] )
            $this_WP_Query->set_404();

        $this_WP_Query->query_vars_hash = md5( serialize( $this_WP_Query->query_vars ) );
        $this_WP_Query->query_vars_changed = false;
        */

}

//开发中功能, 发表文章时进行繁简转换
/*
add_filter('content_save_pre', $wpcs_langs['zh-tw'][0]);
add_filter('title_save_pre', $wpcs_langs['zh-tw'][0]);

add_action('admin_menu', 'wpcs_ep');
function wpcs_ep() {
	add_meta_box('chinese-conversion', 'Chinese Switcher', 'wpcs_edit_post', 'post');
	add_meta_box('chinese-conversion', 'Chinese Switcher', 'wpcs_edit_post', 'page');
}

function wpcs_edit_post() {
	echo '111';
}
*/

/**
 * 输出Header信息
 *
 * 在繁简转换页<header>部分输出一些JS和noindex的meta信息.
 * noindex的meta头是为了防止搜索引擎索引重复内容;
 *
 * JS信息是为了客户端一些应用和功能保留的.
 * 举例, 当访客在一个繁简转换页面提交搜索时, 本插件载入的JS脚本会在GET请求里附加一个variant变量,
 * 如 /?s=test&variant=zh-tw
 * 这样服务器端能够获取用户当前中文语言, 并显示对应语言的搜索结果页
 *
 */
function wpcs_header() {
    global $wpcs_target_lang, $wpcs_langs_urls, $wpcs_noconversion_url, $wpcs_direct_conversion_flag;
    echo "\n" . '<!-- WP Chinese Switcher Plugin Version ' . wpcs_VERSION . ' -->';
    echo "<script type=\"text/javascript\">
//<![CDATA[
var wpcs_target_lang=\"$wpcs_target_lang\";var wpcs_noconversion_url=\"$wpcs_noconversion_url\";var wpcs_langs_urls=new Array();";

    foreach ($wpcs_langs_urls as $key => $value) {
        echo 'wpcs_langs_urls["' . $key . '"]="' . $value . '";';
    }
    echo '
//]]>
</script>';
    if ( ! $wpcs_direct_conversion_flag) {
        wp_enqueue_script('wpcs-search-js', wpcs_DIR_URL . 'assets/js/search-variant.min.js', array(), '1.1', false);
    }
    //echo '<script type="text/javascript" src="' . wpcs_DIR_URL . 'assets/js/search-variant.min.js' . '"></script>';

    if ($wpcs_direct_conversion_flag ||
        ((class_exists('All_in_One_SEO_Pack') || class_exists('Platinum_SEO_Pack')) &&
         ! is_single() && ! is_home() && ! is_page() && ! is_search())
    ) {
        return;
    } else {
        echo '<meta name="robots" content="noindex,nofollow" />';
    }
}

/*
 * 设置url. 包括当前页面原始URL和各个语言版本URL
 * @since 1.1.7
 *
 */
function wpcs_template_redirect() {
    global $wpcs_noconversion_url, $wpcs_langs_urls, $wpcs_options, $wpcs_target_lang, $wpcs_redirect_to;

    if ($wpcs_noconversion_url == get_option('home') . '/' && $wpcs_options['wpcs_use_permalink']) {
        foreach ($wpcs_options['wpcs_used_langs'] as $value) {
            $wpcs_langs_urls[$value] = $wpcs_noconversion_url . $value . '/';
        }
    } else {
        foreach ($wpcs_options['wpcs_used_langs'] as $value) {
            $wpcs_langs_urls[$value] = wpcs_link_conversion($wpcs_noconversion_url, $value);
        }
    }

    if ( ! is_404() && $wpcs_redirect_to) {
        setcookie('wpcs_is_redirect_' . COOKIEHASH, '1', 0, COOKIEPATH, COOKIE_DOMAIN);
        wp_redirect($wpcs_langs_urls[$wpcs_redirect_to], 302);
    }

    if ( ! $wpcs_target_lang) {
        return;
    }

    add_action('comment_form', 'wpcs_modify_comment_form');
    function wpcs_modify_comment_form() {
        global $wpcs_target_lang;
        echo '<input type="hidden" name="variant" value="' . $wpcs_target_lang . '" />';
    }

    wpcs_do_conversion();
}

/**
 * 在Wordpress的query vars里增加一个variant变量.
 *
 */
function wpcs_insert_query_vars($vars) {
    array_push($vars, 'variant');

    return $vars;
}

/**
 * Widget Class
 * @since 1.1.8
 *
 */
class wpcs_Widget extends WP_Widget {
    function __construct() {
        parent::__construct('widget_wpcs', 'Chinese Switcher', array(
            'classname'   => 'widget_wpcs',
            'description' => 'Chinese Switcher Widget'
        ));
    }

    function widget($args, $instance) {
        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;
        if ($title) {
            echo $before_title . $title . $after_title;
        }
        wpcs_output_navi(isset($instance['args']) ? $instance['args'] : '');
        echo $after_widget;
    }

    function update($new_instance, $old_instance) {
        return $new_instance;
    }

    function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $args  = isset($instance['args']) ? esc_attr($instance['args']) : '';
        ?>
      <p>
        <label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat"
                                                                               id="<?php echo $this->get_field_id('title'); ?>"
                                                                               name="<?php echo $this->get_field_name('title'); ?>"
                                                                               type="text"
                                                                               value="<?php echo $title; ?>"/></label>
        <label for="<?php echo $this->get_field_id('args'); ?>">Args: <input class="widefat"
                                                                             id="<?php echo $this->get_field_id('args'); ?>"
                                                                             name="<?php echo $this->get_field_name('args'); ?>"
                                                                             type="text" value="<?php echo $args; ?>"/></label>
      </p>
        <?php
    }
}

/**
 * 转换字符串到当前请求的中文语言
 *
 * @param string $str string inputed
 * @param string $variant optional, Default to null, chinese language code you want string to be converted, if null( default), will use $GLOBALS['wpcs_target_lang']
 *
 * @return converted string
 *
 * 这是本插件繁简转换页使用的基本中文转换函数. 例如, 如果访客请求一个"台湾正体"版本页面,
 * $wpcs_conversion_function被设置为'zhconversion_tw',
 * 本函数调用其把字符串转换为"台湾正体"版本
 *
 */
function zhconversion($str, $variant = null) {
    global $wpcs_options, $wpcs_langs;
    if ($variant === null) {
        $variant = $GLOBALS['wpcs_target_lang'];
    }
    if ($variant == false) {
        return $str;
    }

    //if( !empty($wpcs_options['wpcs_no_conversion_tag']) || $wpcs_options['wpcs_no_conversion_ja'] == 1 )
    //	return limit_zhconversion($str, $wpcs_langs[$variant][0]);
    return $wpcs_langs[$variant][0]($str);
}


function zhconversion2($str, $variant = null) { // do not convert content within <!--wpcs_NC_START--> and <!--wpcs_NC_END-->.
    global $wpcs_options, $wpcs_langs;
    if ($variant === null) {
        $variant = $GLOBALS['wpcs_target_lang'];
    }
    if ($variant == false) {
        return $str;
    }

    return limit_zhconversion($str, $wpcs_langs[$variant][0]);
}


$_wpcs_id = 1000;
/**
 * get a unique id number
 */
function wpcs_id() {
    global $_wpcs_id;

    return $_wpcs_id ++;
}

/**
 * filter the content
 * @since 1.1.14
 *
 */
function wpcs_no_conversion_filter($str) {
    global $wpcs_options;

    $html  = str_get_html($str);
    $query = '';

    if ( ! empty($wpcs_options['wpcs_no_conversion_ja'])) {
        $query .= '*[lang="ja"]';
    }

    if ( ! empty($wpcs_options['wpcs_no_conversion_tag'])) {
        if ($query != '') {
            $query .= ',';
        }
        if (preg_match('/^[a-z1-9|]+$/', $wpcs_options['wpcs_no_conversion_tag'])) {
            $query .= str_replace('|', ',', $wpcs_options['wpcs_no_conversion_tag']);
        } // backward compatability
        else {
            $query .= $wpcs_options['wpcs_no_conversion_tag'];
        }
    }

    $elements = $html->find($query);
    if (count($elements) == 0) {
        return $str;
    }
    foreach ($elements as $element) {
        $id                 = wpcs_id();
        $element->innertext = '<!--wpcs_NC' . $id . '_START-->' . $element->innertext . '<!--wpcs_NC' . $id . '_END-->';
    }

    return (string) $html;

}


/**
 * 安全转换字符串到当前请求的中文语言
 *
 * @param string $str string inputed
 * @param string $variant optional, Default to null, chinese language code you want string to be converted, if null( default), will use $GLOBALS['wpcs_target_lang']
 *
 * @return converted string
 *
 * 与zhconversion函数不同的是本函数首先确保载入繁简转换表, 因为多了一次判断, 不可避免多耗费资源.
 *
 */
function zhconversion_safe($str, $variant = null) {
    wpcs_load_conversion_table();

    return zhconversion($str, $variant);
}

/**
 * 转换字符到多种中文语言,返回数组
 *
 * @param string $str string to be converted
 * @param array $langs Optional, Default to array('zh-tw', 'zh-cn'). array of chinese languages codes you want string to be converted to
 *
 * @return array converted strings array
 *
 * Example: zhconversion('网络');
 * Return: array('网络', '网络');
 *
 */
function zhconversion_all($str, $langs = array('zh-tw', 'zh-cn', 'zh-hk', 'zh-sg', 'zh-hans', 'zh-hant')) {
    global $wpcs_langs;
    $return = array();
    foreach ($langs as $value) {
        $tmp = $wpcs_langs[$value][0] ($str);
        if ($tmp != $str) {
            $return[] = $tmp;
        }
    }

    return array_unique($return);
}

/**
 * 递归的对数组中元素用zhconversion函数转换, 返回处理后数组.
 *
 */
function zhconversion_deep($value) {
    $value = is_array($value) ? array_map('zhconversion_deep', $value) : zhconversion($value);

    return $value;
}

/**
 * 对输入字符串进行有限中文转换. 不转换<!--wpcs_NC_START-->和<!--wpcs_NC_END-->之间的中文
 *
 * @param string $str string inputed
 * @param string $function conversion function for current requested chinese language
 *
 * @return converted string
 *
 */
function limit_zhconversion($str, $function) {
    if ($m = preg_split('/(<!--wpcs_NC(\d*)_START-->)(.*?)(<!--wpcs_NC\2_END-->)/s', $str, - 1, PREG_SPLIT_DELIM_CAPTURE)) {
        $r     = '';
        $count = 0;
        foreach ($m as $v) {
            $count ++;
            if ($count % 5 == 1) {
                $r .= $function ($v);
            } else if ($count % 5 == 4) {
                $r .= $v;
            }
        }

        return $r;
    } else {
        return $function($str);
    }
}


/**
 * 中文转换函数. (zhconversion_hans转换字符串为简体中文, zhconversion_hant转换字符串为繁体中文, zhconversion_tw转换字符串为台湾正体, 依次类推)
 *
 * @param string $str string to be converted
 *
 * @return string converted chinese string
 *
 * 对于zh-hans和zh-hant以外中文语言(如zh-tw),Mediawiki里的做法是 先array_merge($zh2Hans, $zh2TW),再做一次strtr. 但这里考虑到内存需求和CPU资源,采用两次strtr方法.其中$zh2TW先做,因为其中项目可能覆盖zh2Hant里的项目
 *
 * 注意: 如果您想在其他地方(如Theme)里使用下面中文转换函数, 请保证首先调用一次wpcs_load_conversion_table(); , 因为出于节省内存需求, 本插件仅在繁简转换页面才会加载中文转换表.
 *
 */
function zhconversion_hant($str) {
    global $zh2Hant;

    return strtr($str, $zh2Hant);
}

function zhconversion_hans($str) {
    global $zh2Hans;

    return strtr($str, $zh2Hans);
}

function zhconversion_cn($str) {
    global $zh2Hans, $zh2CN;

    return strtr(strtr($str, $zh2CN), $zh2Hans);
}

function zhconversion_tw($str) {
    global $zh2Hant, $zh2TW;

    return strtr(strtr($str, $zh2TW), $zh2Hant);
}

function zhconversion_sg($str) {
    global $zh2Hans, $zh2SG;

    return strtr(strtr($str, $zh2SG), $zh2Hans);
}

function zhconversion_hk($str) {
    global $zh2Hant, $zh2HK;

    return strtr(strtr($str, $zh2HK), $zh2Hant);
}

/**
 * 不推荐, 为向后兼容保留的函数
 * 为模板预留的函数, 把链接安全转换为当前中文语言版本的, 您可以在模板中调用其转换硬编码的链接.
 * 例如, 您可以在您的footer.php里显示博客About页链接: <a href="<?php echo wpcs_link_safe_conversion('http://domain.tld/about/'); ?>" >About</a>
 * 如果用户请求一个繁简转换页面, 则输出为该页的对应繁简转换版本链接,如 http://domain.tld/about/zh-tw/
 *
 * @param string $link URL to be converted
 *
 * @return string converted URL
 *
 * @deprecated Use wpcs_link_conversion($link)
 */
function wpcs_link_safe_conversion($link) {
    return wpcs_link_conversion($link);
}

/**
 * 取消WP错误的重定向
 *
 * @param string $redirect_to 'redirect_canonical' filter's first argument
 * @param string $redirect_from 'redirect_canonical' filter's second argument
 *
 * @return string|false
 *
 * 因为Wordpress canonical url机制, 有时会把繁简转换页重定向到错误URL
 * 本函数检测并取消这种重定向(通过返回false)
 *
 */
function wpcs_cancel_incorrect_redirect($redirect_to, $redirect_from) {
    global $wp_rewrite;
    if (preg_match('/^.*\/(zh-tw|zh-cn|zh-sg|zh-hant|zh-hans|zh-my|zh-mo|zh-hk|zh|zh-reset)\/?.+$/', $redirect_to)) {
        if (($wp_rewrite->use_trailing_slashes && substr($redirect_from, - 1) != '/') ||
            ( ! $wp_rewrite->use_trailing_slashes && substr($redirect_from, - 1) == '/')
        ) {
            return user_trailingslashit($redirect_from);
        }

        return false;
    }

    return $redirect_to;
}

/**
 * 修改WP Rewrite规则数组, 增加本插件添加的Rewrite规则
 *
 * @param array $rules 'rewrite_rules_array' filter's argument , WP rewrite rules array
 *
 * @return array processed rewrite rules array
 *
 *
 * 基本上, 本函数对WP的Rewrite规则数组这样处理:
 *
 * 对 '..../?$' => 'index.php?var1=$matches[1]..&varN=$matches[N]' 这样一条规则,
 * 如果规则体部分 '.../?$' 含有 'trackback', 'attachment', 'print', 不做处理
 * 否则, 增加一条 '.../zh-tw|zh-hant|...|zh-hans|zh|zh-reset/?$' => 'index.php?var1=$matches[1]..&varN=$matches[N]&variant=$matches[N+1]'的新规则
 * 1.1.6版本后, 因为增加了/zh-tw/original/permalink/这种URL形式, 情况更加复杂
 *
 */
function wpcs_rewrite_rules($rules) {
    global $wpcs_options;
    $reg    = implode('|', $wpcs_options['wpcs_used_langs']);
    $rules2 = array();
    if ($wpcs_options['wpcs_use_permalink'] == 1) {
        foreach ($rules as $key => $value) {
            if (strpos($key, 'trackback') !== false || strpos($key, 'print') !== false || strpos($value, 'lang=') !== false) {
                continue;
            }
            if (substr($key, - 3) == '/?$') {
                if ( ! preg_match_all('/\$matches\[(\d+)\]/', $value, $matches, PREG_PATTERN_ORDER)) {
                    continue;
                }
                $number                                                          = count($matches[0]) + 1;
                $rules2[substr($key, 0, - 3) . '/(' . $reg . '|zh|zh-reset)/?$'] = $value . '&variant=$matches[' . $number . ']';
            }
        }
    } else { // $wpcs_options['wpcs_use_permalink'] == 2
        foreach ($rules as $key => $value) {
            if (strpos($key, 'trackback') !== false || strpos($key, 'print') !== false || strpos($value, 'lang=') !== false) {
                continue;
            }
            if (substr($key, - 3) == '/?$') {
                $rules2['(' . $reg . '|zh|zh-reset)/' . $key] = preg_replace_callback('/\$matches\[(\d+)\]/', '_wpcs_permalink_preg_callback', $value) . '&variant=$matches[1]';
            }
        }
    }
    $rules2['^(' . $reg . '|zh|zh-reset)/?$'] = 'index.php?variant=$matches[1]';//首页的繁简转换版本rewrite规则
    $return                                   = array_merge($rules2, $rules);

    return $return;
}

function _wpcs_permalink_preg_callback($matches) {
    return '$matches[' . (intval($matches[1]) + 1) . ']';
}

/**
 * 修改繁简转换页面WP内部链接
 *
 * @param string $link URL to be converted
 *
 * @return string converted URL
 *
 * 如果访客请求一个繁简转换页面, 本函数把该页的所有链接转换为对应中文语言版本的
 * 例如把分类页链接转换为 /category/cat-name/zh-xx/, 把Tag页链接转换为 /tag/tag-name/zh-xx/
 *
 */
function wpcs_link_conversion($link, $variant = null) {
    global $wpcs_options;

    static $wpcs_wp_home;
    if (empty($wpcs_wp_home)) {
        $wpcs_wp_home = home_url();
    }

    if ($variant === null) {
        $variant = $GLOBALS['wpcs_target_lang'];
    }
    if ($variant == false) {
        return $link;
    }

    if (strpos($link, '?') !== false || ! $wpcs_options['wpcs_use_permalink']) {
        return add_query_arg('variant', $variant, $link);
    }
    if ($wpcs_options['wpcs_use_permalink'] == 1) {
        return user_trailingslashit(trailingslashit($link) . $variant);
    }

    return preg_replace('#^(http(s?)://[^/]+' . $wpcs_wp_home . ')#', '\\1' . $variant . '/', $link);
}

/**
 * don't convert a link in "direct_conversion" mode;
 * @since 1.1.14.2
 */
function wpcs_link_conversion_auto($link, $variant = null) {
    global $wpcs_target_lang, $wpcs_direct_conversion_flag, $wpcs_options;

    if ($link == home_url('')) {
        $link .= '/';
    }
    if ( ! $wpcs_target_lang || $wpcs_direct_conversion_flag) {
        return $link;
    } else {
        if ($link == home_url('/') && ! empty($wpcs_options['wpcs_use_permalink'])) {
            return trailingslashit(wpcs_link_conversion($link));
        }

        return wpcs_link_conversion($link);
    }
}

/**
 * 获取当前页面原始URL
 * @return original permalink of current page
 *
 * 本函数返回当前请求页面"原始版本" URL.
 * 即如果当前URL是 /YYYY/mm/sample-post/zh-tw/ 形式的台湾正体版本,
 * 会返回 /YYYY/mm/sample-post/ 的原始(不进行中文转换)版本链接.
 *
 */
function wpcs_get_noconversion_url() {
    global $wpcs_options;
    $reg = implode('|', $wpcs_options['wpcs_used_langs']);
    $tmp = (is_ssl() ? 'https://' : 'http://') .
           $_SERVER['HTTP_HOST'] .
           $_SERVER['REQUEST_URI'];
    $tmp = trim(strtolower(remove_query_arg('variant', $tmp)));

    if (preg_match('/^(.*)\/(' . $reg . '|zh|zh-reset)(\/.*)?$/', $tmp, $matches)) {
        $tmp = user_trailingslashit(trailingslashit($matches[1]) . ltrim($matches[3], '/')); //为什幺这样写代码? 是有原因的- -(众人: 废话!)
        if ($tmp == get_option('home')) {
            $tmp .= '/';
        }
    }

    return $tmp;
}

/**
 * 修复繁简转换页分页链接
 *
 * @param string $link URL to be fixed
 *
 * @return string Fixed URL
 *
 * 本函数修复繁简转换页面 /.../page/N 形式的分页链接为正确形式. 具体说明略.
 *
 * 您可以在本函数内第一行加上 'return $link;' 然后访问您博客首页或存盘页的繁体或简体版本,
 * 会发现"上一页"(previous posts page)和"下一页"(next posts page)的链接URL是错误的.
 * 本函数算法极为愚蠢- -, 但是没有其它办法, 因为wordpress对于分页链接的生成策略非常死板且无法更多地通过filter控制
 *
 */
function wpcs_pagenum_link_fix($link) {
    global $wpcs_target_lang, $wpcs_options;
    global $paged;
    if ($wpcs_options['wpcs_use_permalink'] != 1) {
        return $link;
    }

    if (preg_match('/^(.*)\/page\/\d+\/' . $wpcs_target_lang . '\/page\/(\d+)\/?$/', $link, $tmp) ||
        preg_match('/^(.*)\/' . $wpcs_target_lang . '\/page\/(\d+)\/?$/', $link, $tmp)) {
        return user_trailingslashit($tmp[1] . '/page/' . $tmp[2] . '/' . $wpcs_target_lang);
    } else if (preg_match('/^(.*)\/page\/(\d+)\/' . $wpcs_target_lang . '\/?$/', $link, $tmp) && $tmp[2] == 2 && $paged == 2) {
        if ($tmp[1] == get_option('home')) {
            return $tmp[1] . '/' . $wpcs_target_lang . '/';
        }

        return user_trailingslashit($tmp[1] . '/' . $wpcs_target_lang);
    }

    return $link;
}

/**
 * 修复繁简转换后页面部分内部链接.
 *
 * @param string $link URL to be fixed
 *
 * @return string Fixed URL
 *
 * 本插件会添加 post_link钩子, 从而修改繁简转换页单篇文章页永久链接, 但WP的很多内部链接生成依赖这个permalink.
 * (为什幺加载在post_link钩子上而不是the_permalink钩子上? 有很多原因,这里不说了.)
 *
 * 举例而言, 本插件把 繁简转换页的文章permalink修改为 /YYYY/mm/sample-post/zh-tw/ (如果您原来的Permalink是/YYYY/mm/sample-post/)
 * 那幺WP生成的该篇文章评论Feed链接是 /YYYY/mm/sample-post/zh-tw/feed/, 出错
 * 本函数把这个链接修复为 /YYYY/mm/sample-post/feed/zh-tw/ 的正确形式.
 *
 */
function wpcs_fix_link_conversion($link) {
    global $wpcs_options;
    if ($wpcs_options['wpcs_use_permalink'] == 1) {
        if ($flag = strstr($link, '#')) {
            $link = substr($link, 0, - strlen($flag));
        }
        if (preg_match('/^(.*\/)(zh-tw|zh-cn|zh-sg|zh-hant|zh-hans|zh-my|zh-mo|zh-hk|zh|zh-reset)\/(.+)$/', $link, $tmp)) {
            return user_trailingslashit($tmp[1] . trailingslashit($tmp[3]) . $tmp[2]) . $flag;
        }

        return $link . $flag;
    } else if ($wpcs_options['wpcs_use_permalink'] == 0) {
        if (preg_match('/^(.*)\?variant=([-a-zA-Z]+)\/(.*)$/', $link, $tmp)) {
            return add_query_arg('variant', $tmp[2], trailingslashit($tmp[1]) . $tmp[3]);
        }

        return $link;
    } else {
        return $link;
    }
}

/**
 * "取消"繁简转换后页面部分内部链接转换.
 *
 * @param string $link URL to be fixed
 *
 * @return string Fixed URL
 *
 * 本函数作用与上面的wpcs_fix_link_conversion类似, 不同的是本函数"取消"而不是"修复"繁简转换页内部链接
 * 举例而言, 对繁简转换页面而言, WP生成的单篇文章trackback地址 是 /YYYY/mm/sample-post/zh-tw/trackback/
 * 本函数把它修改为 /YYYY/mm/sample-post/trackback/的正确形式(即去除URL中 zh-xx字段)
 *
 */
function wpcs_cancel_link_conversion($link) {
    global $wpcs_options;
    if ($wpcs_options['wpcs_use_permalink']) {
        if (preg_match('/^(.*\/)(zh-tw|zh-cn|zh-sg|zh-hant|zh-hans|zh-my|zh-mo|zh-hk|zh|zh-reset)\/(.+)$/', $link, $tmp)) {
            return $tmp[1] . $tmp[3];
        }

        return $link;
    } else {
        if (preg_match('/^(.*)\?variant=[-a-zA-Z]+\/(.*)$/', $link, $tmp)) {
            return trailingslashit($tmp[1]) . $tmp[2];
        }

        return $link;
    }
}

/**
 * ...
 */
function wpcs_rel_canonical() {
    if ( ! is_singular()) {
        return;
    }
    global $wp_the_query;
    if ( ! $id = $wp_the_query->get_queried_object_id()) {
        return;
    }
    $link = wpcs_cancel_link_conversion(get_permalink($id));
    echo "<link rel='canonical' href='$link' />\n";
}


/**
 * 返回w3c标准的当前中文语言代码,如 zh-CN, zh-Hans
 * 返回值可以用在html元素的 lang=""标签里
 *
 * @since 1.1.9
 * @link http://www.w3.org/International/articles/language-tags/ W3C关于language attribute文章.
 */
function variant_attribute($default = "zh", $variant = false) {
    global $wpcs_langs;
    if ( ! $variant) {
        $variant = $GLOBALS['wpcs_target_lang'];
    }
    if ( ! $variant) {
        return $default;
    }

    return $wpcs_langs[$variant][3];
}

/**
 * 返回当前语言代码
 * @since 1.1.9
 */
function variant($default = false) {
    global $wpcs_target_lang;
    if ( ! $wpcs_target_lang) {
        return $default;
    }

    return $wpcs_target_lang;
}

/**
 * 输出当前页面不同中文语言版本链接
 *
 * @param bool $return Optional, Default to false, return or echo result.
 *
 * 本插件Widget会调用这个函数.
 *
 */
function wpcs_output_navi($args = '') {
    global $wpcs_target_lang, $wpcs_noconversion_url, $wpcs_langs_urls, $wpcs_langs, $wpcs_options;

    extract(wp_parse_args($args, array('mode' => 'normal', 'echo' => 1)));
    if ($mode == 'wrap') {
        wpcs_output_navi2();

        return;
    }

    if ( ! empty($wpcs_options['nctip'])) {
        $noconverttip = $wpcs_options['nctip'];
    } else {
        $locale = str_replace('_', '-', strtolower(get_locale()));
        if (in_array($locale, array('zh-hant', 'zh-tw', 'zh-hk', 'zh-mo'))) //zh-mo = 澳门繁体, 目前与zh-hk香港繁体转换表相同
        {
            $noconverttip = '不转换';
        } else {
            $noconverttip = '不转换';
        }
    }
    if ($wpcs_target_lang) {
        $noconverttip = zhconversion($noconverttip);
    }
    if (($wpcs_options['wpcs_browser_redirect'] == 2 || $wpcs_options['wpcs_use_cookie_variant'] == 2) &&
        $wpcs_target_lang
    ) {
        $default_url = wpcs_link_conversion($wpcs_noconversion_url, 'zh');
        if ($wpcs_options['wpcs_use_permalink'] != 0 && is_home() && ! is_paged()) {
            $default_url = trailingslashit($default_url);
        }
    } else {
        $default_url = $wpcs_noconversion_url;
    }


    $output = "\n" . '<div id="wpcs_widget_inner"><!--wpcs_NC_START-->' . "\n";
    $output .= '	<span id="wpcs_original_link" class="' . ($wpcs_target_lang == false ? 'wpcs_current_lang' : 'wpcs_lang') . '" ><a class="wpcs_link" href="' . esc_url($default_url) . '" title="' . esc_html($noconverttip) . '">' . esc_html($noconverttip) . '</a></span>' . "\n";

    foreach ($wpcs_langs_urls as $key => $value) {
        $tip    = ! empty($wpcs_options[$wpcs_langs[$key][1]]) ? esc_html($wpcs_options[$wpcs_langs[$key][1]]) : $wpcs_langs[$key][2];
        $output .= '	<span id="wpcs_' . $key . '_link" class="' . ($wpcs_target_lang == $key ? 'wpcs_current_lang' : 'wpcs_lang') . '" ><a class="wpcs_link" rel="nofollow" href="' . esc_url($value) . '" title="' . esc_html($tip) . '" >' . esc_html($tip) . '</a></span>' . "\n";
    }
    $output .= '<!--wpcs_NC_END--></div>' . "\n";
    if ( ! $echo) {
        return $output;
    }
    echo $output;
}

/**
 * Another function for outputing navi. You should not want to use it.
 *
 */
function wpcs_output_navi2() {
    global $wpcs_target_lang, $wpcs_noconversion_url, $wpcs_langs_urls, $wpcs_options;

    if (($wpcs_options['wpcs_browser_redirect'] == 2 || $wpcs_options['wpcs_use_cookie_variant'] == 2) &&
        $wpcs_target_lang
    ) {
        $default_url = wpcs_link_conversion($wpcs_noconversion_url, 'zh');
        if ($wpcs_options['wpcs_use_permalink'] != 0 && is_home() && ! is_paged()) {
            $default_url = trailingslashit($default_url);
        }
    } else {
        $default_url = $wpcs_noconversion_url;
    }

    $output = "\n" . '<div id="wpcs_widget_inner"><!--wpcs_NC_START-->' . "\n";
    $output .= '	<span id="wpcs_original_link" class="' . ($wpcs_target_lang == false ? 'wpcs_current_lang' : 'wpcs_lang') . '" ><a class="wpcs_link" href="' . esc_url($default_url) . '" title="不转换">不转换</a></span>' . "\n";
    $output .= '	<span id="wpcs_cn_link" class="' . ($wpcs_target_lang == 'zh-cn' ? 'wpcs_current_lang' : 'wpcs_lang') . '" ><a class="wpcs_link" rel="nofollow" href="' . esc_url($wpcs_langs_urls['zh-cn']) . '" title="大陆简体" >大陆简体</a></span>' . "\n";
    $output .= '	<span id="wpcs_tw_link" class="' . ($wpcs_target_lang == 'zh-tw' ? 'wpcs_current_lang' : 'wpcs_lang') . '"><a class="wpcs_link" rel="nofollow" href="' . esc_url($wpcs_langs_urls['zh-tw']) . '" title="台湾正体" >台湾正体</a></span>' . "\n";
    /*$output .= '	<span id="wpcs_more_links" class="wpcs_lang" >
      <span id="wpcs_more_links_inner_more" class="'. ( ( $wpcs_target_lang == false || $wpcs_target_lang == 'zh-cn' || $wpcs_target_lang == 'zh-tw' ) ? 'wpcs_lang' : 'wpcs_current_lang' ) . '"><a class="wpcs_link" href="#" onclick="return false;" >其它中文</a></span>
          <span id="wpcs_more_links_inner" >
              <span id="wpcs_hans_link" class="' . ( $wpcs_target_lang == 'zh-hans' ? 'wpcs_current_lang' : 'wpcs_lang' ) . '" ><a class="wpcs_link" rel="nofollow" href="' . esc_url($wpcs_langs_urls['zh-hans']) . '" title="简体中文" >简体中文' . '</a></span>
              <span id="wpcs_hant_link" class="' . ( $wpcs_target_lang == 'zh-hant' ? 'wpcs_current_lang' : 'wpcs_lang' ) . '" ><a class="wpcs_link" rel="nofollow" href="' . esc_url($wpcs_langs_urls['zh-hant']) . '" title="繁体中文" >繁体中文' . '</a></span>
              <span id="wpcs_hk_link" class="' . ( $wpcs_target_lang == 'zh-hk' ? 'wpcs_current_lang' : 'wpcs_lang' ) . '"><a class="wpcs_link" rel="nofollow" href="' . esc_url($wpcs_langs_urls['zh-hk']) . '" title="港澳繁体" >港澳繁体</a></span>
              <span id="wpcs_sg_link" class="' . ( $wpcs_target_lang == 'zh-sg' ? 'wpcs_current_lang' : 'wpcs_lang' ) . '" ><a class="wpcs_link" rel="nofollow" href="' . esc_url($wpcs_langs_urls['zh-sg']) . '" title="马新简体" >马新简体</a></span>
          </span>
      </span>';*/

    $output .= '<!--wpcs_NC_END--></div>' . "\n";
    echo $output;
}

/**
 * 从给定的语言列表中, 解析出浏览器客户端首选语言, 返回解析出的语言字符串或false
 *
 * @param string $accept_languages the languages sting, should set to $_SERVER['HTTP_ACCEPT_LANGUAGE']
 * @param array $target_langs given languages array
 * @param int|bool $flag Optional, default to 0 ( mean false ), description missing.
 *
 * @return string|bool the parsed lang or false if it doesn't exists
 *
 * 使用举例: 调用形式 wpcs_get_prefered_language($_SERVER['HTTP_ACCEPT_LANGUAGE'], $target_langs)
 *
 * $_SERVER['HTTP_ACCEPT_LANGUAGE']: ja,zh-hk;q=0.8,fr;q=0.5,en;q=0.3
 * $target_langs: array('zh-hk', 'en')
 * 返回值: zh-hk
 *
 * $_SERVER['HTTP_ACCEPT_LANGUAGE']: fr;q=0.5,en;q=0.3
 * $target_langs: array('zh-hk', 'en')
 * 返回值: en
 *
 * $_SERVER['HTTP_ACCEPT_LANGUAGE']: ja,zh-hk;q=0.8,fr;q=0.5,en;q=0.3
 * $target_langs: array('zh-tw', 'zh-cn')
 * 返回值: false
 *
 */
function wpcs_get_prefered_language($accept_languages, $target_langs, $flag = 0) {
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
                    $a = each($a);

                    return $a[1];
                }
            }

            $array = array('zh-hant', 'zh-tw', 'zh-hk', 'zh-mo');
            $a     = array_intersect($array, $target_langs);
            if ( ! empty($a)) {
                $b = array_intersect($array, $langs);
                if ( ! empty($b)) {
                    $a = each($a);

                    return $a[1];
                }
            }
        }

        return false;
    }

    return false;
}

/**
 * 判断当前请求是否为搜索引擎访问.
 * 使用的算法极为保守, 只要不是几个主要的浏览器,就判定为Robots
 *
 * @return bool
 * @uses $_SERVER['HTTP_USER_AGENT']
 */
function wpcs_is_robot() {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return true;
    }
    $ua = strtoupper($_SERVER['HTTP_USER_AGENT']);

    $robots = array(
        'bot',
        'spider',
        'crawler',
        'dig',
        'search',
        'find'
    );

    foreach ($robots as $key => $val) {
        if (strstr($ua, strtoupper($val))) {
            return true;
        }
    }

    $browsers = array(
        "compatible; MSIE",
        "UP.Browser",
        "Mozilla",
        "Opera",
        "NSPlayer",
        "Avant Browser",
        "Chrome",
        "Gecko",
        "Safari",
        "Lynx",
    );

    foreach ($browsers as $key => $val) {
        if (strstr($ua, strtoupper($val))) {
            return false;
        }
    }

    return true;
}

/**
 * fix a relative bug
 * @since 1.1.14
 *
 */
function wpcs_apply_filter_search_rule() {
    add_filter('posts_where', 'wpcs_filter_search_rule', 100);
    function search_distinct() {
        return "DISTINCT";
    }

    add_filter('posts_distinct', 'search_distinct');
}

/**
 * 对Wordpress搜索时生成sql语句的 where 条件部分进行处理, 使其同时在数据库中搜索关键词的中文简繁体形式.
 *
 * @param string $where 'post_where' filter's argument, 'WHERE...' part of the wordpesss query sql sentence
 *
 * @return string WHERE sentence have been processed
 *
 * 使用方法: add_filter('posts_where', 'wpcs_filter_search_rule');
 * 原理说明: 假设访客通过表单搜索 "简体 繁体 中文", Wordpress生成的sql语句条件$where中一部分是这样的:
 *
 * ((wp_posts.post_title LIKE '%简体%') OR (wp_posts.post_content LIKE '%简体%')) AND ((wp_posts.post_title LIKE '%繁体%') OR (wp_posts.post_content LIKE '%繁体%')) AND ((wp_posts.post_title LIKE '%中文%') OR (wp_posts.post_content LIKE '%中文%')) OR (wp_posts.post_title LIKE '%简体 繁体 中文%') OR (wp_posts.post_content LIKE '%简体 繁体 中文%')
 *
 * 本函数把$where中的上面这部分替换为:
 *
 * ( ( wp_posts.post_title LIKE '%简体%') OR ( wp_posts.post_content LIKE '%简体%') OR ( wp_posts.post_title LIKE '%简体%') OR ( wp_posts.post_content LIKE '%简体%') ) AND ( ( wp_posts.post_title LIKE '%繁体%') OR ( wp_posts.post_content LIKE '%繁体%') OR ( wp_posts.post_title LIKE '%繁体%') OR ( wp_posts.post_content LIKE '%繁体%') ) AND ( ( wp_posts.post_title LIKE '%中文%') OR ( wp_posts.post_content LIKE '%中文%') ) OR ( wp_posts.post_title LIKE '%简体 繁体 中文%') OR ( wp_posts.post_content LIKE '%简体 繁体 中文%') OR ( wp_posts.post_title LIKE '%简体 繁体 中文%') OR ( wp_posts.post_content LIKE '%简体 繁体 中文%') OR ( wp_posts.post_title LIKE '%简体 繁体 中文%') OR ( wp_posts.post_content LIKE '%简体 繁体 中文%')
 *
 */
function wpcs_filter_search_rule($where) {
    global $wp_query, $wpdb;
    if (empty($wp_query->query_vars['s'])) {
        return $where;
    }
    if ( ! preg_match("/^([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}/", $wp_query->query_vars['s']) && ! preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}$/", $wp_query->query_vars['s']) && ! preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){2,}/", $wp_query->query_vars['s'])) {
        return $where;
    }//如果搜索关键字中不含中文本符, 直接返回

    wpcs_load_conversion_table();

    $placeholder = '%';
    if (method_exists($wpdb, 'placeholder_escape')) {
        $placeholder = $wpdb->placeholder_escape("%");
        // echo("pe exists: " . $placeholder . '<br />');
    }
    $sql      = '';
    $and1     = '';
    $original = '';//Wordpress原始搜索sql代码中 post_title和post_content like '%keyword%'的部分,本函数最后需要找出原始sql代码中这部分并予以替换, 所以必须在过程中重新生成一遍,
    foreach ($wp_query->query_vars['search_terms'] as $value) {
        $value    = addslashes_gpc($value);
        $original .= "{$and1}(($wpdb->posts.post_title LIKE '{$placeholder}{$value}{$placeholder}') OR ($wpdb->posts.post_excerpt LIKE '{$placeholder}{$value}{$placeholder}') OR ($wpdb->posts.post_content LIKE '{$placeholder}{$value}{$placeholder}'))";
        $valuea   = zhconversion_all($value);
        $valuea[] = $value;
        $sql      .= "{$and1}( ";
        $or2      = '';
        foreach ($valuea as $v) {
            $sql .= "{$or2}( " . $wpdb->prefix . "posts.post_title LIKE '{$placeholder}" . $v . "{$placeholder}') ";
            $sql .= " OR ( " . $wpdb->prefix . "posts.post_content LIKE '{$placeholder}" . $v . "{$placeholder}') ";
            $sql .= " OR ( " . $wpdb->prefix . "posts.post_excerpt LIKE '{$placeholder}" . $v . "{$placeholder}') ";
            $or2 = ' OR ';
        }
        $sql  .= ' ) ';
        $and1 = ' AND ';
    }

    // debug
    // echo("Where: ". $where . "<br /><br />Search: " . $original . "<br /><br />Replace with: $sql");die();

    if (empty($sql)) {
        return $where;
    }
    $where = preg_replace('/' . preg_quote($original, '/') . '/', $sql, $where, 1);

    return $where;
}

/**
 * ob_start Callback function
 *
 */
function wpcs_ob_callback($buffer) {
    global $wpcs_target_lang, $wpcs_direct_conversion_flag;
    if ($wpcs_target_lang && ! $wpcs_direct_conversion_flag) {
        $wpcs_home_url = wpcs_link_conversion_auto(home_url('/'));
        $buffer        = preg_replace('|(<a\s(?!class="wpcs_link")[^<>]*?href=([\'"]))' . preg_quote(esc_url(home_url('')), '|') . '/?(\2[^<>]*?>)|', '\\1' . esc_url($wpcs_home_url) . '\\3', $buffer);
    }

    return zhconversion2($buffer) . "\n" . '<!-- WP Chinese Switcher Full Page Converted. Target Lang: ' . $wpcs_target_lang . ' -->';
}

/**
 * Debug Function
 *
 * 要开启本插件Debug, 去掉第一行 defined('wpcs_DEBUG', true')...注释.
 * Debug信息将输出在页面footer位置( wp_footer action)
 *
 */
function wpcs_debug() {
    global $wpcs_noconversion_url, $wpcs_target_lang, $wpcs_langs_urls, $wpcs_deubg_data, $wpcs_langs, $wpcs_options, $wp_rewrite;
    echo '<!--';
    echo '<p style="font-size:20px;color:red;">';
    echo 'WP Chinese Switcher Plugin Debug Output:<br />';
    echo '默认URL: <a href="' . $wpcs_noconversion_url . '">' . $wpcs_noconversion_url . '</a><br />';
    echo '当前语言(空则是不转换): ' . $wpcs_target_lang . "<br />";
    echo 'Query String: ' . $_SERVER['QUERY_STRING'] . '<br />';
    echo 'Request URI: ' . $_SERVER['REQUEST_URI'] . '<br />';
    foreach ($wpcs_langs_urls as $key => $value) {
        echo $key . ' URL: <a href="' . $value . '">' . $value . '</a><br />';
    }
    echo 'Category feed link: ' . get_category_feed_link(1) . '<br />';
    echo 'Search feed link: ' . get_search_feed_link('test');
    echo 'Rewrite Rules: <br />';
    echo nl2br(htmlspecialchars(var_export($wp_rewrite->rewrite_rules(), true))) . '<br />';
    echo 'Debug Data: <br />';
    echo nl2br(htmlspecialchars(var_export($wpcs_deubg_data, true)));
    echo '</p>';
    echo '-->';
}

/**
 * Admin管理后台初始化
 *
 */
function wpcs_admin_init() {
    global $wpcs_admin;
    require_once(dirname(__FILE__) . '/wpchinese-switcher-admin.php');
    $wpcs_admin = new wpcs_Admin();
}

/**
 * Parse current request
 *
 * @param object $query 'parse_request' filter' argument, the 'WP' object
 *
 * @todo 彻底重写本函数（目前是一团浆糊）。使用Wordpress原生的query var系统读/写variant参数, 1.2版本实现.
 * Core codes of this plugin
 * 本函数获取当前请求中文语言并保存到 $wpcs_target_lang全局变量里.
 * 并且还做其它一些事情.
 *
 */
function wpcs_parse_query($query) {
    if (is_robots()) {
        return;
    }
    global $wpcs_target_lang, $wpcs_redirect_to, $wpcs_noconversion_url, $wpcs_options, $wpcs_direct_conversion_flag;

    if ( ! is_404()) {
        $wpcs_noconversion_url = wpcs_get_noconversion_url();
    } else {
        $wpcs_noconversion_url = get_option('home') . '/';
        $wpcs_target_lang      = false;

        return;
    }

    $request_lang = isset($query->query_vars['variant']) ? $query->query_vars['variant'] : '';
    $cookie_lang  = isset($_COOKIE['wpcs_variant_' . COOKIEHASH]) ? $_COOKIE['wpcs_variant_' . COOKIEHASH] : '';

    if ($request_lang && in_array($request_lang, $wpcs_options['wpcs_used_langs'])) {
        $wpcs_target_lang = $request_lang;
    } else {
        $wpcs_target_lang = false;
    }

    if ( ! $wpcs_target_lang) {
        if ($request_lang == 'zh') {
            if ($wpcs_options['wpcs_use_cookie_variant'] != 0) {
                setcookie('wpcs_variant_' . COOKIEHASH, 'zh', time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
            } else {
                setcookie('wpcs_is_redirect_' . COOKIEHASH, '1', 0, COOKIEPATH, COOKIE_DOMAIN);
            }
            header('Location: ' . $wpcs_noconversion_url);
            die();
        }
        if ($request_lang == 'zh-reset') {
            setcookie('wpcs_variant_' . COOKIEHASH, '', time() - 30000000, COOKIEPATH, COOKIE_DOMAIN);
            setcookie('wpcs_is_redirect_' . COOKIEHASH, '', time() - 30000000, COOKIEPATH, COOKIE_DOMAIN);
            header('Location: ' . $wpcs_noconversion_url);
            die();
        }

        if ($cookie_lang == 'zh') {
            if ($wpcs_options['wpcs_use_cookie_variant'] != 0) {
                if ($wpcs_options['wpcs_search_conversion'] == 2) {
                    wpcs_apply_filter_search_rule();
                }

                return;
            } else {
                setcookie('wpcs_variant_' . COOKIEHASH, '', time() - 30000000, COOKIEPATH, COOKIE_DOMAIN);
            }
        }

        if ( ! $request_lang && ! empty($_COOKIE['wpcs_is_redirect_' . COOKIEHASH])) {
            if ($wpcs_options['wpcs_use_cookie_variant'] != 0) {
                setcookie('wpcs_variant_' . COOKIEHASH, 'zh', time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
                setcookie('wpcs_is_redirect_' . COOKIEHASH, '', time() - 30000000, COOKIEPATH, COOKIE_DOMAIN);
            } else if ($cookie_lang) {
                setcookie('wpcs_variant_' . COOKIEHASH, '', time() - 30000000, COOKIEPATH, COOKIE_DOMAIN);
            }
            if ($wpcs_options['wpcs_search_conversion'] == 2) {
                wpcs_apply_filter_search_rule();
            }

            return;
        }
        $is_robot = wpcs_is_robot();
        if ($wpcs_options['wpcs_use_cookie_variant'] != 0 && ! $is_robot && $cookie_lang) {
            if (in_array($cookie_lang, $wpcs_options['wpcs_used_langs'])) {
                if ($wpcs_options['wpcs_use_cookie_variant'] == 2) {
                    $wpcs_target_lang            = $cookie_lang;
                    $wpcs_direct_conversion_flag = true;
                } else {
                    $wpcs_redirect_to = $cookie_lang;
                }
            } else {
                setcookie('wpcs_variant_' . COOKIEHASH, '', time() - 30000000, COOKIEPATH, COOKIE_DOMAIN);
            }
        } else {
            if ($cookie_lang) {
                setcookie('wpcs_variant_' . COOKIEHASH, '', time() - 30000000, COOKIEPATH, COOKIE_DOMAIN);
            }
            if (
                $wpcs_options['wpcs_browser_redirect'] != 0 &&
                ! $is_robot &&
                ! empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) &&
                $wpcs_browser_lang = wpcs_get_prefered_language($_SERVER['HTTP_ACCEPT_LANGUAGE'], $wpcs_options['wpcs_used_langs'], $wpcs_options['wpcs_auto_language_recong'])
            ) {
                if ($wpcs_options['wpcs_browser_redirect'] == 2) {
                    $wpcs_target_lang            = $wpcs_browser_lang;
                    $wpcs_direct_conversion_flag = true;
                } else {
                    $wpcs_redirect_to = $wpcs_browser_lang;
                }
            }
        }
    }

    if ($wpcs_options['wpcs_search_conversion'] == 2 ||
        ($wpcs_target_lang && $wpcs_options['wpcs_search_conversion'] == 1)
    ) {
        wpcs_apply_filter_search_rule();
    }

    if ($wpcs_target_lang && $wpcs_options['wpcs_use_cookie_variant'] != 0 && $cookie_lang != $wpcs_target_lang) {
        setcookie('wpcs_variant_' . COOKIEHASH, $wpcs_target_lang, time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
    }

}

/**
 * 载入繁简转换表.
 *
 * 出于节省内存考虑, 本插件并不总是载入繁简转换表. 而仅在繁简转换页面才这样做.
 */
function wpcs_load_conversion_table() {
    global $wpcs_options;
    if ( ! empty($wpcs_options['wpcs_no_conversion_ja']) || ! empty($wpcs_options['wpcs_no_conversion_tag'])) {
        if ( ! function_exists('str_get_html')) {
            require_once(__DIR__ . '/simple_html_dom.php');
        }
    }

    global $zh2Hans;
    if ($zh2Hans == false) {
        global $zh2Hant, $zh2TW, $zh2CN, $zh2SG, $zh2HK;
        require_once(dirname(__FILE__) . '/ZhConversion.php');
        if (file_exists(WP_CONTENT_DIR . '/extra_zhconversion.php')) {
            require_once(WP_CONTENT_DIR . '/extra_zhconversion.php');
        }
    }
}

/**
 * 进行繁简转换. 加载若干filter转换页面内容和内部链接
 *
 */
function wpcs_do_conversion() {
    global $wpcs_direct_conversion_flag, $wpcs_options;
    wpcs_load_conversion_table();

    add_action('wp_head', 'wpcs_header');

    if ( ! $wpcs_direct_conversion_flag) {
        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', 'wpcs_rel_canonical');

        //add_filter('the_permalink', 'wpcs_link_conversion');
        add_filter('post_link', 'wpcs_link_conversion');
        add_filter('month_link', 'wpcs_link_conversion');
        add_filter('day_link', 'wpcs_link_conversion');
        add_filter('year_link', 'wpcs_link_conversion');
        add_filter('page_link', 'wpcs_link_conversion');
        add_filter('tag_link', 'wpcs_link_conversion');
        add_filter('author_link', 'wpcs_link_conversion');
        add_filter('category_link', 'wpcs_link_conversion');
        add_filter('feed_link', 'wpcs_link_conversion');
        add_filter('attachment_link', 'wpcs_link_conversion');
        add_filter('search_feed_link', 'wpcs_link_conversion');

        add_filter('category_feed_link', 'wpcs_fix_link_conversion');
        add_filter('tag_feed_link', 'wpcs_fix_link_conversion');
        add_filter('author_feed_link', 'wpcs_fix_link_conversion');
        add_filter('post_comments_feed_link', 'wpcs_fix_link_conversion');
        add_filter('get_comments_pagenum_link', 'wpcs_fix_link_conversion');
        add_filter('get_comment_link', 'wpcs_fix_link_conversion');

        add_filter('attachment_link', 'wpcs_cancel_link_conversion');
        add_filter('trackback_url', 'wpcs_cancel_link_conversion');

        add_filter('get_pagenum_link', 'wpcs_pagenum_link_fix');
        add_filter('redirect_canonical', 'wpcs_cancel_incorrect_redirect', 10, 2);
    }

    if ( ! empty($wpcs_options['wpcs_no_conversion_ja']) || ! empty($wpcs_options['wpcs_no_conversion_tag'])) {
        add_filter('the_content', 'wpcs_no_conversion_filter', 15);
        add_filter('the_content_rss', 'wpcs_no_conversion_filter', 15);
    }

    if ($wpcs_options['wpcs_use_fullpage_conversion'] == 1) {
        @ob_start('wpcs_ob_callback');
        /*
            function wpcs_ob_end() {
                while( @ob_end_flush() );
            }
            add_action('shutdown', 'wpcs_ob_end');
            */

        //一般不需要这段代码, Wordpress默认在shutdown时循环调用ob_end_flush关闭所有缓存.

        return;
    }

    add_filter('the_content', 'zhconversion2', 20);
    add_filter('the_content_rss', 'zhconversion2', 20);
    add_filter('the_excerpt', 'zhconversion2', 20);
    add_filter('the_excerpt_rss', 'zhconversion2', 20);

    add_filter('the_title', 'zhconversion');
    add_filter('comment_text', 'zhconversion');
    add_filter('bloginfo', 'zhconversion');
    add_filter('the_tags', 'zhconversion_deep');
    add_filter('term_links-post_tag', 'zhconversion_deep');
    add_filter('wp_tag_cloud', 'zhconversion');
    add_filter('the_category', 'zhconversion');
    add_filter('list_cats', 'zhconversion');
    add_filter('category_description', 'zhconversion');
    add_filter('single_cat_title', 'zhconversion');
    add_filter('single_post_title', 'zhconversion');
    add_filter('bloginfo_rss', 'zhconversion');
    add_filter('the_title_rss', 'zhconversion');
    add_filter('comment_text_rss', 'zhconversion');
}

/**
 * 在html的body标签class属性里添加当前中文语言代码
 * thanks to chad luo.
 * @since 1.1.13
 *
 */
function wpcs_body_class($classes) {
    global $wpcs_target_lang;
    $classes[] = $wpcs_target_lang ? $wpcs_target_lang : "zh";

    return $classes;
}

add_filter("body_class", "wpcs_body_class");

/**
 * 自动修改html tag 的 lang=""标签为当前中文语言
 * @since 1.0
 *
 */
function wpcs_locale($output, $doctype = 'html') {
    global $wpcs_target_lang, $wpcs_langs;
    $lang = get_bloginfo('language');
    if ($wpcs_target_lang && strpos($lang, 'zh-') === 0) {
        $lang   = $wpcs_langs[$wpcs_target_lang][3];
        $output = preg_replace('/lang="[^"]+"/', "lang=\"{$lang}\"", $output);
    }

    return $output;
}

add_filter('language_attributes', 'wpcs_locale');

/**
 * add a wpcs_NC button to html editor toolbar.
 * @since 1.1.14
 */
function wpcs_appthemes_add_quicktags() {
    global $wpcs_options;
    if ( ! empty($wpcs_options) && ! empty($wpcs_options['wpcs_no_conversion_qtag']) && wp_script_is('quicktags')) {
        ?>
      <script type="text/javascript">
        //<![CDATA[
        QTags.addButton('eg_wpcs_nc', 'wpcs_NC', '<!--wpcs_NC_START-->', '<!--wpcs_NC_END-->', null, 'WP Chinese Switcher DO-NOT Convert Tag', 120);
        //]]>
      </script>
        <?php
    }
}

add_action('admin_print_footer_scripts', 'wpcs_appthemes_add_quicktags');

/**
 * Function executed when plugin is activated
 *
 * add or update 'wpcs_option' in wp_option table of the wordpress database
 * your current settings will be reserved if you have installed this plugin before.
 *
 */
function wpcs_activate() {
    $current_options = (array) get_option('wpcs_options');
    $wpcs_options    = array(
        'wpcs_search_conversion'       => 1,
        'wpcs_used_langs'              => array('zh-hans', 'zh-hant', 'zh-cn', 'zh-hk', 'zh-sg', 'zh-tw'),
        'wpcs_browser_redirect'        => 0,
        'wpcs_auto_language_recong'    => 0,
        'wpcs_flag_option'             => 1,
        'wpcs_use_cookie_variant'      => 0,
        'wpcs_use_fullpage_conversion' => 1,
        'wpcs_trackback_plugin_author' => 0,
        'wpcs_add_author_link'         => 0,
        'wpcs_use_permalink'           => 0,
        'wpcs_no_conversion_tag'       => '',
        'wpcs_no_conversion_ja'        => 0,
        'wpcs_no_conversion_qtag'      => 0,
        'wpcs_engine'                  => 'mediawiki', // alternative: opencc
        'nctip'                        => '',
    );

    foreach ($current_options as $key => $value) {
        if (isset($wpcs_options[$key])) {
            $wpcs_options[$key] = $value;
        }
    }

    foreach (
        array(
            'zh-hans' => "hanstip",
            'zh-hant' => "hanttip",
            'zh-cn'   => "cntip",
            'zh-hk'   => "hktip",
            'zh-sg'   => "sgtip",
            'zh-tw'   => "twtip",
            'zh-my'   => "mytip",
            'zh-mo'   => "motip"
        ) as $lang => $tip
    ) {
        if ( ! empty($current_options[$tip])) {
            $wpcs_options[$tip] = $current_options[$tip];
        }
    }

    //WP will automaticlly add a option if it doesn't exists( when this plugin is firstly being installed).
    update_option('wpcs_options', $wpcs_options);
}

register_activation_hook(__FILE__, 'wpcs_activate');
