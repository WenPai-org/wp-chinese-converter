<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPCC插件主类
 * 
 * 使用单例模式管理插件的核心功能，减少全局变量依赖
 */
class WPCC_Main {
    
    private static ?self $instance = null;
    private WPCC_Config $config;
    private WPCC_Converter_Factory $converter_factory;
    private WPCC_Module_Manager $module_manager;
    private WPCC_Conversion_Cache $cache;
    
    private function __construct() {
        $this->config = WPCC_Config::get_instance();
        $this->converter_factory = new WPCC_Converter_Factory();
        $this->module_manager = WPCC_Module_Manager::get_instance();
        $this->cache = new WPCC_Conversion_Cache();
        
        $this->init_hooks();
    }
    
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 初始化WordPress钩子
     */
    private function init_hooks(): void {
        add_action( 'init', [ $this, 'init' ], 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'parse_request', [ $this, 'parse_request' ] );
        add_action( 'template_redirect', [ $this, 'template_redirect' ], -100 );
        
        // 调试模式钩子
        if ( WP_DEBUG || ( defined( 'wpcc_DEBUG' ) && wpcc_DEBUG ) ) {
            add_action( 'init', [ $this, 'flush_rewrite_rules' ] );
            add_action( 'wp_footer', [ $this, 'debug_output' ] );
        }
    }
    
    /**
     * 插件初始化
     */
    public function init(): void {
        // 验证配置
        $config_errors = $this->config->validate_config();
        if ( ! empty( $config_errors ) ) {
            $this->handle_config_errors( $config_errors );
            return;
        }
        
        // 初始化模块
        $this->init_modules();
        
        // 设置重写规则
        if ( $this->config->is_feature_enabled( 'wpcc_use_permalink' ) ) {
            $this->setup_rewrite_rules();
        }
        
        // 处理评论提交
        $this->handle_comment_submission();
        
        // 修复首页显示问题
        if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
            add_action( 'parse_query', [ $this, 'fix_homepage_query' ] );
        }
        
        // 注册区块渲染过滤器
        add_filter( 'render_block', [ $this, 'render_no_conversion_block' ], 5, 2 );
    }
    
    /**
     * 处理配置错误
     */
    private function handle_config_errors( array $errors ): void {
        if ( is_admin() ) {
            foreach ( $errors as $error ) {
                add_action( 'admin_notices', function() use ( $error ) {
                    printf(
                        '<div class="notice notice-error"><p>WP Chinese Converter: %s</p></div>',
                        esc_html( $error )
                    );
                });
            }
        }
        
        // 记录错误日志
        error_log( 'WPCC Config Errors: ' . implode( ', ', $errors ) );
    }
    
    /**
     * 初始化模块
     */
    private function init_modules(): void {
        $modules_dir = dirname( dirname( __FILE__ ) ) . '/modules/';
        
        // 注册核心模块
        $this->module_manager->register_module( 'WPCC_Cache_Addon', $modules_dir . 'wpcc-cache-addon.php' );
        $this->module_manager->register_module( 'WPCC_Network', $modules_dir . 'wpcc-network.php' );
        $this->module_manager->register_module( 'WPCC_Rest_Api', $modules_dir . 'wpcc-rest-api.php' );
        $this->module_manager->register_module( 'WPCC_Modern_Cache', $modules_dir . 'wpcc-modern-cache.php' );
        $this->module_manager->register_module( 'WPCC_SEO_Enhancement', $modules_dir . 'wpcc-seo-enhancement.php' );
        
        // 自动发现并加载模块
        $this->module_manager->auto_discover_modules();
    }
    
    /**
     * 加载脚本和样式
     */
    public function enqueue_scripts(): void {
        wp_register_script(
            'wpcc-variant',
            plugins_url( '/assets/dist/wpcc-variant.umd.js', dirname( dirname( __FILE__ ) ) ),
            [],
            wpcc_VERSION
        );
        
        wp_register_script(
            'wpcc-block-script',
            plugins_url( '/assets/js/wpcc-block-script-ok.js', dirname( dirname( __FILE__ ) ) ),
            [ 'wp-blocks', 'wp-element', 'wpcc-variant' ],
            wpcc_VERSION
        );
        
        wp_enqueue_script( [ 'wpcc-variant', 'wpcc-block-script' ] );
        
        wp_localize_script( 'wpcc-block-script', 'wpcc_config', [
            'use_permalink' => $this->config->get_option( 'wpcc_use_permalink', 0 ),
            'target_lang' => $this->config->get_target_lang(),
            'enabled_languages' => $this->config->get_enabled_languages(),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wpcc_nonce' )
        ]);
    }
    
    /**
     * 添加查询变量
     */
    public function add_query_vars( array $vars ): array {
        $vars[] = 'variant';
        return $vars;
    }
    
    /**
     * 解析请求
     */
    public function parse_request( $wp ): void {
        if ( is_robots() ) {
            return;
        }
        
        if ( is_404() ) {
            $this->config->set_noconversion_url( home_url( '/' ) );
            $this->config->set_target_lang( '' );
            return;
        }
        
        // 设置无转换URL
        $noconversion_url = $this->get_noconversion_url();
        $this->config->set_noconversion_url( $noconversion_url );
        // 向后兼容：同步全局变量
        $GLOBALS['wpcc_noconversion_url'] = $noconversion_url;
        
        // 获取目标语言
        $target_lang = $this->determine_target_language( $wp );
        $this->config->set_target_lang( $target_lang );
        // 向后兼容：同步全局变量
        $GLOBALS['wpcc_target_lang'] = $target_lang;
        
        // 处理搜索转换
        $this->handle_search_conversion();
        
        // 设置Cookie
        $this->handle_language_cookie( $target_lang );
    }
    
    /**
     * 确定目标语言
     */
    private function determine_target_language( $wp ): string {
        $request_lang = $wp->query_vars['variant'] ?? '';
        $cookie_lang = $_COOKIE[ 'wpcc_variant_' . COOKIEHASH ] ?? '';
        
        // 检查URL参数中的语言
        if ( $request_lang && $this->config->is_language_enabled( $request_lang ) ) {
            return $request_lang;
        }
        
        // 处理特殊的'zh'重定向
        if ( $request_lang === 'zh' && ! is_admin() ) {
            $this->handle_zh_redirect();
            return '';
        }
        
        // 浏览器语言检测
        if ( $this->config->is_feature_enabled( 'wpcc_browser_redirect' ) ) {
            $browser_lang = $this->detect_browser_language();
            if ( $browser_lang ) {
                return $browser_lang;
            }
        }
        
        // Cookie语言偏好
        if ( $this->config->is_feature_enabled( 'wpcc_use_cookie_variant' ) && $cookie_lang ) {
            if ( $this->config->is_language_enabled( $cookie_lang ) ) {
                return $cookie_lang;
            }
        }
        
        return '';
    }
    
    /**
     * 处理'zh'重定向
     */
    private function handle_zh_redirect(): void {
        $cookie_key = 'wpcc_variant_' . COOKIEHASH;
        
        if ( $this->config->is_feature_enabled( 'wpcc_use_cookie_variant' ) ) {
            setcookie( $cookie_key, 'zh', time() + 30000000, COOKIEPATH, COOKIE_DOMAIN );
        } else {
            setcookie( 'wpcc_is_redirect_' . COOKIEHASH, '1', 0, COOKIEPATH, COOKIE_DOMAIN );
        }
        
        wp_redirect( $this->config->get_noconversion_url() );
        exit;
    }
    
    /**
     * 检测浏览器语言
     */
    private function detect_browser_language(): string {
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ( empty( $accept_language ) ) {
            return '';
        }
        
        return wpcc_get_prefered_language(
            $accept_language,
            $this->config->get_enabled_languages(),
            $this->config->get_option( 'wpcc_auto_language_recong', 0 )
        ) ?: '';
    }
    
    /**
     * 处理语言Cookie
     */
    private function handle_language_cookie( string $target_lang ): void {
        if ( ! $target_lang || ! $this->config->is_feature_enabled( 'wpcc_use_cookie_variant' ) ) {
            return;
        }
        
        $cookie_key = 'wpcc_variant_' . COOKIEHASH;
        $current_cookie = $_COOKIE[$cookie_key] ?? '';
        
        if ( $current_cookie !== $target_lang ) {
            setcookie( $cookie_key, $target_lang, time() + 30000000, COOKIEPATH, COOKIE_DOMAIN );
        }
    }
    
    /**
     * 处理搜索转换
     */
    private function handle_search_conversion(): void {
        $search_option = $this->config->get_option( 'wpcc_search_conversion', 0 );
        $target_lang = $this->config->get_target_lang();
        
        if ( $search_option === 2 || ( $target_lang && $search_option === 1 ) ) {
            add_filter( 'posts_where', [ $this, 'filter_search_query' ], 100 );
            add_filter( 'posts_distinct', function() { return 'DISTINCT'; } );
        }
    }
    
    /**
     * 模板重定向处理
     */
    public function template_redirect(): void {
        $this->set_language_urls();
        
        // 处理重定向
        if ( ! is_404() && $this->config->get_redirect_to() && ! is_admin() ) {
            $redirect_url = $this->config->get_langs_urls()[ $this->config->get_redirect_to() ];
            setcookie( 'wpcc_is_redirect_' . COOKIEHASH, '1', 0, COOKIEPATH, COOKIE_DOMAIN );
            wp_redirect( $redirect_url, 302 );
            exit;
        }
        
        $target_lang = $this->config->get_target_lang();
        if ( ! $target_lang ) {
            return;
        }
        
        // 添加评论表单语言参数
        add_action( 'comment_form', [ $this, 'add_comment_form_variant' ] );
        
        // 执行转换
        $this->do_conversion();
    }
    
    /**
     * 设置各语言版本的URL
     */
    private function set_language_urls(): void {
        $langs_urls = [];
        $noconversion_url = $this->config->get_noconversion_url();
        $use_permalink = $this->config->is_feature_enabled( 'wpcc_use_permalink' );
        
        foreach ( $this->config->get_enabled_languages() as $lang ) {
            if ( $noconversion_url === home_url( '/' ) && $use_permalink ) {
                $langs_urls[$lang] = $noconversion_url . $lang . '/';
            } else {
                $langs_urls[$lang] = $this->convert_link( $noconversion_url, $lang );
            }
        }
        
        $this->config->set_langs_urls( $langs_urls );
        // 向后兼容：同步全局变量
        $GLOBALS['wpcc_langs_urls'] = $langs_urls;
    }
    
    /**
     * 执行转换
     */
    private function do_conversion(): void {
        // 加载转换表
        $this->load_conversion_table();
        
        // 输出头部信息
        add_action( 'wp_head', [ $this, 'output_header' ] );
        
        if ( ! $this->config->get_direct_conversion_flag() ) {
            // 移除默认canonical并添加自定义的
            remove_action( 'wp_head', 'rel_canonical' );
            add_action( 'wp_head', [ $this, 'output_canonical' ] );
            
            // 添加链接转换过滤器
            $this->add_link_conversion_filters();
        }
        
        // 内容转换过滤器
        $this->add_content_conversion_filters();
        
        // 全页面转换模式
        if ( $this->config->is_feature_enabled( 'wpcc_use_fullpage_conversion' ) ) {
            ob_start( [ $this, 'full_page_conversion_callback' ] );
        }
    }
    
    /**
     * 获取配置实例
     */
    public function get_config(): WPCC_Config {
        return $this->config;
    }
    
    /**
     * 获取转换器工厂
     */
    public function get_converter_factory(): WPCC_Converter_Factory {
        return $this->converter_factory;
    }
    
    /**
     * 获取模块管理器
     */
    public function get_module_manager(): WPCC_Module_Manager {
        return $this->module_manager;
    }
    
    /**
     * 获取缓存实例
     */
    public function get_cache(): WPCC_Conversion_Cache {
        return $this->cache;
    }
    
    /**
     * 调试输出
     */
    public function debug_output(): void {
        if ( ! ( WP_DEBUG || ( defined( 'wpcc_DEBUG' ) && wpcc_DEBUG ) ) ) {
            return;
        }
        
        $debug_data = $this->config->get_debug_data();
        if ( empty( $debug_data ) ) {
            return;
        }
        
        echo '<!-- WPCC Debug Data -->';
        echo '<script type="text/javascript">';
        echo 'console.log("WPCC Debug:", ' . wp_json_encode( $debug_data ) . ');';
        echo '</script>';
    }
    
    /**
     * 刷新重写规则（调试模式）
     */
    public function flush_rewrite_rules(): void {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
    
    // 这里添加其他必要的私有方法...
    
    /**
     * 获取无转换URL
     */
    private function get_noconversion_url(): string {
        $enabled_langs = $this->config->get_enabled_languages();
        $reg = implode( '|', $enabled_langs );
        
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        $tmp = trim( strtolower( remove_query_arg( 'variant', $protocol . $host . $uri ) ) );
        
        if ( preg_match( '/^(.*)\\/(' . $reg . '|zh|zh-reset)(\\/.*)?$/', $tmp, $matches ) ) {
            $tmp = user_trailingslashit( trailingslashit( $matches[1] ) . ltrim( $matches[3] ?? '', '/' ) );
            if ( $tmp === home_url() ) {
                $tmp .= '/';
            }
        }
        
        return $tmp;
    }
    
    /**
     * 设置重写规则
     */
    private function setup_rewrite_rules(): void {
        add_filter( 'rewrite_rules_array', [ $this, 'modify_rewrite_rules' ] );
    }
    
    /**
     * 修改重写规则
     */
    public function modify_rewrite_rules( array $rules ): array {
        $enabled_langs = $this->config->get_enabled_languages();
        $reg = implode( '|', $enabled_langs );
        $rules2 = [];
        
        $use_permalink = $this->config->get_option( 'wpcc_use_permalink', 0 );
        
        if ( $use_permalink == 1 ) {
            foreach ( $rules as $key => $value ) {
                if ( strpos( $key, 'trackback' ) !== false || strpos( $key, 'print' ) !== false || strpos( $value, 'lang=' ) !== false ) {
                    continue;
                }
                if ( substr( $key, -3 ) == '/?$' ) {
                    if ( ! preg_match_all( '/\\$matches\\[(\\d+)\\]/', $value, $matches, PREG_PATTERN_ORDER ) ) {
                        continue;
                    }
                    $number = count( $matches[0] ) + 1;
                    $rules2[ substr( $key, 0, -3 ) . '/(' . $reg . '|zh|zh-reset)/?$' ] = $value . '&variant=$matches[' . $number . ']';
                }
            }
        } else {
            foreach ( $rules as $key => $value ) {
                if ( strpos( $key, 'trackback' ) !== false || strpos( $key, 'print' ) !== false || strpos( $value, 'lang=' ) !== false ) {
                    continue;
                }
                if ( substr( $key, -3 ) == '/?$' ) {
                    $rules2[ '(' . $reg . '|zh|zh-reset)/' . $key ] = preg_replace_callback( '/\\$matches\\[(\\d+)\\]/', [ $this, 'permalink_preg_callback' ], $value ) . '&variant=$matches[1]';
                }
            }
        }
        
        $rules2[ '^(' . $reg . '|zh|zh-reset)/?$' ] = 'index.php?variant=$matches[1]';
        return array_merge( $rules2, $rules );
    }
    
    /**
     * URL重写回调函数
     */
    private function permalink_preg_callback( array $matches ): string {
        return '$matches[' . ( intval( $matches[1] ) + 1 ) . ']';
    }
    
    /**
     * 处理评论提交
     */
    private function handle_comment_submission(): void {
        if ( ( isset( $_SERVER['PHP_SELF'] ) && ( strpos( $_SERVER['PHP_SELF'], 'wp-comments-post.php' ) !== false
           || strpos( $_SERVER['PHP_SELF'], 'ajax-comments.php' ) !== false
           || strpos( $_SERVER['PHP_SELF'], 'comments-ajax.php' ) !== false )
         ) &&
         isset( $_SERVER["REQUEST_METHOD"] ) && $_SERVER["REQUEST_METHOD"] == "POST" &&
         isset( $_POST['variant'] ) && ! empty( $_POST['variant'] ) && $this->config->is_language_enabled( $_POST['variant'] )
        ) {
            $this->config->set_target_lang( sanitize_text_field( $_POST['variant'] ) );
            $this->do_conversion();
        }
    }
    
    /**
     * 修复首页查询
     */
    public function fix_homepage_query( $wp_query ) {
        $qv = &$wp_query->query_vars;
        
        if ( $wp_query->is_home && 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
            $_query = wp_parse_args( $wp_query->query );
            if ( isset( $_query['pagename'] ) && '' == $_query['pagename'] ) {
                unset( $_query['pagename'] );
            }
            if ( empty( $_query ) || ! array_diff( array_keys( $_query ), [
                    'preview', 'page', 'paged', 'cpage', 'variant'
                ] ) ) {
                $wp_query->is_page = true;
                $wp_query->is_home = false;
                $qv['page_id'] = get_option( 'page_on_front' );
                if ( ! empty( $qv['paged'] ) ) {
                    $qv['page'] = $qv['paged'];
                    unset( $qv['paged'] );
                }
            }
        }
        
        return $wp_query;
    }
    
    /**
     * 渲染不转换区块
     */
    public function render_no_conversion_block( string $block_content, array $block ): string {
        if ( isset( $block['blockName'] ) && $block['blockName'] === 'wpcc/no-conversion' ) {
            $unique_id = uniqid();
            
            $pattern = '/<div[^>]*class="[^"]*wpcc-no-conversion-content[^"]*"[^>]*>(.*?)<\\/div>/s';
            
            $replacement = function ( $matches ) use ( $unique_id ) {
                $content = $matches[1];
                return '<div class="wpcc-no-conversion-content"><!--wpcc_NC' . $unique_id . '_START-->' . $content . '<!--wpcc_NC' . $unique_id . '_END--></div>';
            };
            
            $block_content = preg_replace_callback( $pattern, $replacement, $block_content );
        }
        
        return $block_content;
    }
    
    /**
     * 添加评论表单语言参数
     */
    public function add_comment_form_variant(): void {
        $target_lang = $this->config->get_target_lang();
        if ( $target_lang ) {
            echo '<input type="hidden" name="variant" value="' . esc_attr( $target_lang ) . '" />';
        }
    }
    
    /**
     * 转换链接
     */
    private function convert_link( string $link, string $variant ): string {
        static $wp_home;
        if ( empty( $wp_home ) ) {
            $wp_home = home_url();
        }
        
        if ( str_contains( $link, $variant ) ) {
            return $link;
        }
        
        if ( str_contains( $link, '?' ) || ! $this->config->is_feature_enabled( 'wpcc_use_permalink' ) ) {
            return add_query_arg( 'variant', $variant, $link );
        }
        
        if ( $this->config->get_option( 'wpcc_use_permalink' ) == 1 ) {
            return user_trailingslashit( trailingslashit( $link ) . $variant );
        }
        
        return str_replace( $wp_home, "$wp_home/$variant", $link );
    }
    
    /**
     * 加载转换表
     */
    private function load_conversion_table(): void {
        // 这个方法会调用原有的全局函数来保持兼容性
        if ( function_exists( 'wpcc_load_conversion_table' ) ) {
            wpcc_load_conversion_table();
        }
    }
    
    /**
     * 添加链接转换过滤器
     */
    private function add_link_conversion_filters(): void {
        add_filter( 'post_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'month_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'day_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'year_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'page_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'tag_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'author_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'category_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'feed_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'attachment_link', [ $this, 'filter_link_conversion' ] );
        add_filter( 'search_feed_link', [ $this, 'filter_link_conversion' ] );
    }
    
    /**
     * 过滤链接转换
     */
    public function filter_link_conversion( string $link ): string {
        return $this->convert_link( $link, $this->config->get_target_lang() );
    }
    
    /**
     * 添加内容转换过滤器
     */
    private function add_content_conversion_filters(): void {
        add_filter( 'the_content', 'zhconversion2', 20 );
        add_filter( 'the_content_rss', 'zhconversion2', 20 );
        add_filter( 'the_excerpt', 'zhconversion2', 20 );
        add_filter( 'the_excerpt_rss', 'zhconversion2', 20 );
        add_filter( 'the_title', 'zhconversion' );
        add_filter( 'comment_text', 'zhconversion' );
        add_filter( 'bloginfo', 'zhconversion' );
        add_filter( 'the_tags', 'zhconversion_deep' );
        add_filter( 'term_links-post_tag', 'zhconversion_deep' );
        add_filter( 'wp_tag_cloud', 'zhconversion' );
        add_filter( 'the_category', 'zhconversion' );
        add_filter( 'list_cats', 'zhconversion' );
        add_filter( 'category_description', 'zhconversion' );
        add_filter( 'single_cat_title', 'zhconversion' );
        add_filter( 'single_post_title', 'zhconversion' );
        add_filter( 'bloginfo_rss', 'zhconversion' );
        add_filter( 'the_title_rss', 'zhconversion' );
        add_filter( 'comment_text_rss', 'zhconversion' );
    }
    
    /**
     * 输出头部信息
     */
    public function output_header(): void {
        $target_lang = $this->config->get_target_lang();
        $noconversion_url = $this->config->get_noconversion_url();
        $langs_urls = $this->config->get_langs_urls();
        
        echo "\n" . '<!-- WP Chinese Converter Plugin Version ' . esc_html( wpcc_VERSION ) . ' -->';
        
        $script_data = [
            'wpcc_target_lang' => $target_lang ? esc_js( $target_lang ) : '',
            'wpcc_noconversion_url' => $noconversion_url ? esc_url( $noconversion_url ) : '',
            'wpcc_langs_urls' => []
        ];
        
        if ( is_array( $langs_urls ) ) {
            foreach ( $langs_urls as $key => $value ) {
                $safe_key = preg_match( '/^[a-z-]+$/', $key ) ? $key : '';
                if ( $safe_key && filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    $script_data['wpcc_langs_urls'][ $safe_key ] = esc_url( $value );
                }
            }
        }
        
        echo '<script type="text/javascript">';
        echo '/* <![CDATA[ */';
        echo 'var wpcc_target_lang="' . $script_data['wpcc_target_lang'] . '";';
        echo 'var wpcc_noconversion_url="' . $script_data['wpcc_noconversion_url'] . '";';
        echo 'var wpcc_langs_urls=' . wp_json_encode( $script_data['wpcc_langs_urls'] ) . ';';
        echo '/* ]]> */';
        echo '</script>';
    }
    
    /**
     * 输出canonical链接
     */
    public function output_canonical(): void {
        if ( ! is_singular() ) {
            return;
        }
        global $wp_the_query;
        if ( ! $id = $wp_the_query->get_queried_object_id() ) {
            return;
        }
        $link = get_permalink( $id );
        // 移除语言参数
        $link = remove_query_arg( 'variant', $link );
        echo "<link rel='canonical' href='$link' />\n";
    }
    
    /**
     * 全页面转换回调
     */
    public function full_page_conversion_callback( string $buffer ): string {
        $target_lang = $this->config->get_target_lang();
        if ( $target_lang && ! $this->config->get_direct_conversion_flag() ) {
            $home_url = $this->convert_link( home_url( '/' ), $target_lang );
            $home_pattern = preg_quote( esc_url( home_url( '' ) ), '|' );
            $buffer = preg_replace( '|(<a\s(?!class="wpcc_link")[^<>]*?href=([\'"]]))' . $home_pattern . '/?(\2[^<>]*?>)|', '${1}' . esc_url( $home_url ) . '${3}', $buffer );
        }
        return zhconversion2( $buffer ) . "\n" . '<!-- WP Chinese Converter Full Page Converted. Target Lang: ' . $target_lang . ' -->';
    }
    
    /**
     * 过滤搜索查询
     */
    public function filter_search_query( string $where ): string {
        // 调用原有的全局函数来保持兼容性
        if ( function_exists( 'wpcc_filter_search_rule' ) ) {
            return wpcc_filter_search_rule( $where );
        }
        return $where;
    }
}
