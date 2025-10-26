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
        // 在 WordPress 基于重写规则生成 query_vars 之后，优先级0先行修正 pagename=zh-xx 的场景
        add_filter( 'request', [ $this, 'filter_request_vars' ], 0 );
        add_action( 'parse_request', [ $this, 'parse_request' ] );
        add_action( 'template_redirect', [ $this, 'template_redirect' ], -100 );
        
        // 进一步兜底：在主查询生成前修正“纯语言前缀”为首页查询
        add_action( 'pre_get_posts', [ $this, 'pre_get_posts_fix' ], 0 );
        
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
    
        // 标记 AJAX/REST/wc-ajax 请求为“直接输出”，跳过页面级转换
        $is_ajax = function_exists('wp_doing_ajax') ? wp_doing_ajax() : ( defined('DOING_AJAX') && DOING_AJAX );
        $is_rest = defined('REST_REQUEST') && REST_REQUEST;
        $is_wc_ajax = isset($_REQUEST['wc-ajax']) && is_string($_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] !== '';
        $this->config->set_direct_conversion_flag( (bool) ( $is_ajax || $is_rest || $is_wc_ajax ) );
    
        // 初始化模块
        $this->init_modules();
    
        // 设置重写规则
        if ( $this->config->is_feature_enabled( 'wpcc_use_permalink' ) ) {
            $this->setup_rewrite_rules();
            // 自愈：在启用固定链接模式但尚未生成 WPCC 语言规则时，尝试一次性刷新重写规则，避免 /zh-xx/ 访问 404
            $this->maybe_autoflush_rewrite_rules();
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
        // $this->module_manager->register_module( 'WPCC_Network', $modules_dir . 'wpcc-network.php' ); // 已被新的网络设置模块替代
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

        // 兜底：若 rewrite 未捕获到 variant，但路径以语言前缀开头，则从路径中注入 variant
        $path = isset( $wp->request ) ? trim( (string) $wp->request, "\r\n\t " ) : '';
        if ( empty( $wp->query_vars['variant'] ) && is_string( $path ) && $path !== '' ) {
            $enabled = $this->config->get_enabled_languages();
            if ( ! empty( $enabled ) && is_array( $enabled ) ) {
                $reg = implode( '|', array_map( 'preg_quote', $enabled ) );
                if ( preg_match( '/^(' . $reg . '|zh|zh-reset)(?:\/)?$/i', $path, $m ) ) {
                    // 仅当是“纯前缀”（如 zh-tw/ 或 zh/）时兜底注入；
                    $wp->query_vars['variant'] = strtolower( $m[1] );
                }
            }
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
        
        // 若首页为静态页面且请求为根级变体路径，则直接映射到首页，避免 404
        if ( get_option( 'show_on_front' ) === 'page' && get_option( 'page_on_front' ) ) {
            $req_path = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
            if ( $req_path !== '' ) {
                $enabled = $this->config->get_enabled_languages();
                if ( ! empty( $enabled ) ) {
                    $pattern = '/^(?:' . implode( '|', array_map( 'preg_quote', $enabled ) ) . '|zh|zh-reset)$/i';
                    if ( preg_match( $pattern, $req_path ) ) {
                        $wp->query_vars['page_id'] = (int) get_option( 'page_on_front' );
                        unset( $wp->query_vars['pagename'], $wp->query_vars['name'] );
                    }
                }
            }
        }

        // 处理搜索转换
        $this->handle_search_conversion();
        
        // 设置Cookie
        $this->handle_language_cookie( $target_lang );
    }
    
    /**
     * 确定目标语言
     */
    private function determine_target_language( $wp ): string {
        $request_lang = isset( $wp->query_vars['variant'] ) ? sanitize_text_field( $wp->query_vars['variant'] ) : '';
        $cookie_lang = isset( $_COOKIE[ 'wpcc_variant_' . COOKIEHASH ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ 'wpcc_variant_' . COOKIEHASH ] ) ) : '';
        
        // 检查URL参数中的语言
        if ( $request_lang && $this->config->is_language_enabled( $request_lang ) ) {
            return $request_lang;
        }
        
        // 处理特殊的'zh'重定向（用户明确选择“原始/不转换”）
        if ( $request_lang === 'zh' && ! is_admin() ) {
            $this->handle_zh_redirect();
            return '';
        }
        
        // 如果Cookie中记录了'zh'哨兵，则优先恢复为“不转换”
        if ( $cookie_lang === 'zh' ) {
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
            setcookie( $cookie_key, 'zh', [
                'expires'  => time() + 30000000,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
        } else {
            setcookie( 'wpcc_is_redirect_' . COOKIEHASH, '1', [
                'expires'  => 0,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
        }
        
        wp_redirect( $this->config->get_noconversion_url() );
        exit;
    }
    
    /**
     * 检测浏览器语言
     */
    private function detect_browser_language(): string {
        $accept_language = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
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
            setcookie( $cookie_key, $target_lang, [
                'expires'  => time() + 30000000,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
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
        
        // 处理“根级语言前缀”访问：/zh-xx/ 或 /zh/
        if ( ! is_admin() ) {
            global $wp;
            $req = isset( $wp->request ) ? trim( (string) $wp->request, "/" ) : '';
            if ( $req !== '' ) {
                $enabled = $this->config->get_enabled_languages();
                $pattern = '/^(?:' . implode( '|', array_map( 'preg_quote', $enabled ) ) . '|zh|zh-reset)$/i';
                if ( preg_match( $pattern, $req ) ) {
                    // 禁用本次自动重定向（避免将根级变体再次重定向到其它 URL）
                    if ( method_exists( $this->config, 'set_redirect_to' ) ) {
                        $this->config->set_redirect_to( false );
                    }
                    $v = strtolower( $req );

                    // 仅 zh/zh-reset 使用哨兵重定向到不转换首页；其它根级变体在当前 URL 下渲染（避免缓存都命中 /）
                    if ( $v === 'zh' || $v === 'zh-reset' ) {
                        $this->handle_zh_redirect();
                        return;
                    }

                    // 在根级变体 URL 下渲染首页：由下游 pre_get_posts/filter_request_vars 修正查询为首页
                    // 注入头部脚本供前端使用
                    add_action( 'wp_head', [ $this, 'output_header' ] );
                }
            }
        }
        
        // 处理重定向
        if ( ! is_404() && $this->config->get_redirect_to() && ! is_admin() ) {
            $redirect_url = $this->config->get_langs_urls()[ $this->config->get_redirect_to() ];
            setcookie( 'wpcc_is_redirect_' . COOKIEHASH, '1', [
                'expires'  => 0,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
            wp_redirect( $redirect_url, 302 );
            exit;
        }
        
        $target_lang = $this->config->get_target_lang();
        if ( ! $target_lang ) {
            // 即使是“未选择语言（不转换）”也输出头部数据，供前端开关生成漂亮链接
            add_action( 'wp_head', [ $this, 'output_header' ] );
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
        $style = (int) $this->config->get_option( 'wpcc_use_permalink', 0 );
        $style_effective = $this->is_permalinks_enabled() ? $style : 0;
        
        // 语言映射
        foreach ( $this->config->get_enabled_languages() as $lang ) {
            if ( $style_effective !== 0 && $noconversion_url === home_url( '/' ) ) {
                $langs_urls[$lang] = $noconversion_url . $lang . '/';
            } else {
                $langs_urls[$lang] = $this->convert_link( $noconversion_url, $lang );
            }
        }
        
        // 增加 zh 哨兵映射，便于前端/菜单快速回到“不转换”状态
        // 在前缀/后缀模式下输出漂亮链接；在查询参数模式下回退为 ?variant=zh
        $langs_urls['zh'] = $this->convert_link( $noconversion_url, 'zh' );
        
        $this->config->set_langs_urls( $langs_urls );
        // 向后兼容：同步全局变量
        $GLOBALS['wpcc_langs_urls'] = $langs_urls;
    }
    
    /**
     * 执行转换
     */
    private function do_conversion(): void {
        // 若是 AJAX/REST/wc-ajax 等非 HTML 响应，直接跳过所有转换，避免破坏 JSON/片段
        if ( $this->config->get_direct_conversion_flag() ) {
            return;
        }
        
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

            // 导航与 Logo 链接也加入变体（兼容经典菜单与站点 Logo）
            // 注意：不在 home_url 上挂钩，避免 convert_link 内部调用 home_url 时产生递归
            add_filter( 'get_custom_logo', [ $this, 'filter_custom_logo' ], 10, 1 );
            add_filter( 'wp_nav_menu', [ $this, 'filter_wp_nav_menu' ], 20, 2 );

            // 取消错误的 canonical 跳转，避免语言前缀被 WP 错误重定向
            add_filter( 'redirect_canonical', [ $this, 'cancel_incorrect_redirect' ], 10, 2 );
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
     * 添加评论表单隐藏字段：variant
     */
    public function add_comment_form_variant(): void {
        $target_lang = $this->config->get_target_lang();
        if ( ! $target_lang ) {
            return;
        }
        echo '<input type="hidden" name="variant" value="' . esc_attr( $target_lang ) . '" />';
    }
    
    /**
     * 加载转换表
     */
    private function load_conversion_table(): void {
        if ( function_exists( 'wpcc_load_conversion_table' ) ) {
            wpcc_load_conversion_table();
        }
    }
    
    /**
     * 添加链接转换过滤器
     */
    private function add_link_conversion_filters(): void {
        add_filter( 'home_url', [ $this, 'filter_home_url' ], 10, 4 );
        add_filter( 'post_link', [ $this, 'filter_link_conversion' ], 10, 3 );
        add_filter( 'post_type_link', [ $this, 'filter_link_conversion' ], 10, 3 );
        add_filter( 'page_link', [ $this, 'filter_link_conversion' ], 10, 3 );
        add_filter( 'term_link', [ $this, 'filter_link_conversion' ], 10, 3 );
        add_filter( 'attachment_link', [ $this, 'filter_link_conversion' ], 10, 3 );
        add_filter( 'category_link', [ $this, 'filter_link_conversion' ], 10, 2 );
        add_filter( 'tag_link', [ $this, 'filter_link_conversion' ], 10, 2 );
        add_filter( 'author_link', [ $this, 'filter_link_conversion' ], 10, 2 );
        add_filter( 'day_link', [ $this, 'filter_link_conversion' ], 10, 2 );
        add_filter( 'month_link', [ $this, 'filter_link_conversion' ], 10, 2 );
        add_filter( 'year_link', [ $this, 'filter_link_conversion' ], 10, 2 );
        add_filter( 'get_pagenum_link', [ $this, 'filter_link_conversion' ], 10, 1 );
    }
    
    /**
     * 过滤链接转换
     */
    public function filter_link_conversion( string $link ): string {
        $target_lang = $this->config->get_target_lang();
        if ( ! $target_lang || $this->config->get_direct_conversion_flag() ) {
            return $link;
        }
        return $this->convert_link( $link, $target_lang );
    }
    
    /**
     * 过滤home_url
     */
    public function filter_home_url( string $url, string $path, $orig_scheme, $blog_id ): string {
        $target_lang = $this->config->get_target_lang();
        if ( ! $target_lang || $this->config->get_direct_conversion_flag() ) {
            return $url;
        }
        return $this->convert_link( $url, $target_lang );
    }
    
    /**
     * 过滤自定义Logo HTML，加入语言变体链接
     */
    public function filter_custom_logo( string $html ): string {
        $target_lang = $this->config->get_target_lang();
        if ( ! $target_lang || $this->config->get_direct_conversion_flag() ) {
            return $html;
        }
        $home = esc_url( $this->convert_link( home_url( '/' ), $target_lang ) );
        return preg_replace( '/(<a\s[^>]*?href=[\"\'])([^\"\']+)([\"\'][^>]*?>)/i', '$1' . $home . '$3', $html );
    }
    
    /**
     * 过滤菜单HTML，加入语言变体链接
     */
    public function filter_wp_nav_menu( string $nav_menu, $args ): string {
        $target_lang = $this->config->get_target_lang();
        if ( ! $target_lang || $this->config->get_direct_conversion_flag() ) {
            return $nav_menu;
        }
        $home = esc_url( $this->convert_link( home_url( '/' ), $target_lang ) );
        return preg_replace( '/(<a\s[^>]*?href=[\"\'])(?:' . preg_quote( esc_url( home_url( '' ) ), '/' ) . '\/)?([\"\'][^>]*?>)/i', '$1' . $home . '$2', $nav_menu );
    }
    
    /**
     * 过滤请求变量
     */
    public function filter_request_vars( array $request ): array {
        // 当访问根级语言前缀时将查询修正为首页
        $enabled = $this->config->get_enabled_languages();
        $reg = implode( '|', array_map( 'preg_quote', $enabled ) );
        if ( isset( $request['pagename'] ) && preg_match( '/^(' . $reg . '|zh|zh-reset)$/i', (string) $request['pagename'] ) ) {
            unset( $request['pagename'], $request['name'] );
            $request['page_id'] = (int) get_option( 'page_on_front' );
        }
        return $request;
    }
    
    /**
     * 添加内容转换过滤器
     */
    private function add_content_conversion_filters(): void {
        add_filter( 'the_content', 'zhconversion2' );
        add_filter( 'the_excerpt', 'zhconversion2' );
        add_filter( 'the_title', 'zhconversion' );
        add_filter( 'list_cats', 'zhconversion' );
        add_filter( 'widget_title', 'zhconversion' );
        add_filter( 'bloginfo', 'zhconversion' );
        add_filter( 'wp_title', 'zhconversion' );
        add_filter( 'category_description', 'zhconversion' );
        add_filter( 'comment_author', 'zhconversion' );
        add_filter( 'comment_text', 'zhconversion2' );
        add_filter( 'link_name', 'zhconversion' );
        add_filter( 'link_description', 'zhconversion' );
        add_filter( 'link_notes', 'zhconversion' );
        add_filter( 'post_type_archive_title', 'zhconversion' );
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
        
        // 当启用了“浏览器语言”或“Cookie偏好”的显示模式时，为“不转换”链接注入 zh 哨兵，确保能覆盖浏览器/Cookie策略
        $browser_pref = (int) $this->config->get_option( 'wpcc_browser_redirect', 0 );
        $cookie_pref  = (int) $this->config->get_option( 'wpcc_use_cookie_variant', 0 );
        $noconv_forced = $noconversion_url;
        if ( ($browser_pref === 2 || $cookie_pref === 2) && $target_lang ) {
            $noconv_forced = $this->convert_link( $noconversion_url, 'zh' );
        }
        
        echo "\n" . '<!-- WP Chinese Converter Plugin Version ' . esc_html( wpcc_VERSION ) . ' -->';
        
        $script_data = [
            'wpcc_target_lang' => $target_lang ? esc_js( $target_lang ) : '',
            'wpcc_noconversion_url' => $noconv_forced ? esc_url( $noconv_forced ) : '',
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
        // 若已标记为直接输出（AJAX/REST等），不做任何处理，避免破坏响应
        if ( $this->config->get_direct_conversion_flag() ) {
            return $buffer;
        }
        $target_lang = $this->config->get_target_lang();
        if ( $target_lang && ! $this->config->get_direct_conversion_flag() ) {
            $home_url = $this->convert_link( home_url( '/' ), $target_lang );
            $home_pattern = preg_quote( esc_url( home_url( '' ) ), '|' );
            $buffer = preg_replace( '|(<a\s(?!class=\"wpcc_link\")[^<>]*?href=([\'\"]))' . $home_pattern . '/?(\2[^<>]*?>)|', '${1}' . esc_url( $home_url ) . '${3}', $buffer );
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

    // ====== 新增：核心支撑方法 ======

    private function is_permalinks_enabled(): bool {
        global $wp_rewrite;
        return ! empty( $wp_rewrite ) && ! empty( $wp_rewrite->permalink_structure );
    }

    private function get_noconversion_url(): string {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $parsed = wp_parse_url( home_url( $uri ) );
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        $query = isset($parsed['query']) ? $parsed['query'] : '';

        // 去掉前缀中的语言变体，例如 /zh-tw/xxx 或 /zh/
        $enabled = $this->config->get_enabled_languages();
        $reg = implode('|', array_map('preg_quote', (array) $enabled));
        $path = preg_replace('#^/(?:' . $reg . '|zh|zh-reset)(?:/|$)#i', '/', $path);
        if ($path === '') { $path = '/'; }

        // 去除 ?variant=xxx 查询参数
        $query_args = [];
        if ($query !== '') {
            parse_str($query, $query_args);
            unset($query_args['variant']);
        }
        $base = home_url( untrailingslashit( $path ) . '/' );
        return ! empty($query_args) ? add_query_arg( $query_args, $base ) : $base;
    }

    private function setup_rewrite_rules(): void {
        global $wp_rewrite;
        if ( ! $this->is_permalinks_enabled() ) {
            return;
        }

        // 给变体添加rewrite tag
        add_rewrite_tag( '%variant%', '([a-z\-]+)' );

        $enabled = $this->config->get_enabled_languages();
        if ( empty( $enabled ) || ! is_array( $enabled ) ) {
            return;
        }
        $reg = implode( '|', array_map( 'preg_quote', $enabled ) );

        // 仅语言前缀访问（根级），映射为首页并注入 variant
        add_rewrite_rule( '^(' . $reg . '|zh|zh-reset)/?$', 'index.php?variant=$matches[1]', 'top' );

        // 常规：语言前缀 + 任何路径，注入 variant 并交给 WP 匹配 pagename
        add_rewrite_rule( '^(' . $reg . '|zh|zh-reset)/(.*)$', 'index.php?variant=$matches[1]&pagename=$matches[2]', 'top' );
    }

    private function maybe_autoflush_rewrite_rules(): void {
        // 限流：12小时最多一次自动刷新
        $last = (int) get_option( 'wpcc_rewrite_autoflush_ts', 0 );
        if ( time() - $last < 12 * HOUR_IN_SECONDS ) {
            return;
        }
        flush_rewrite_rules( false );
        update_option( 'wpcc_rewrite_autoflush_ts', time() );
    }

    public function flush_rewrite_rules(): void {
        // 在调试模式下立即刷新重写规则，确保语言前缀规则生效
        $this->setup_rewrite_rules();
        flush_rewrite_rules( false );
        update_option( 'wpcc_rewrite_autoflush_ts', time() );
    }

    public function pre_get_posts_fix( $query ): void {
        if ( ! is_admin() && $query && $query->is_main_query() ) {
            $enabled = $this->config->get_enabled_languages();
            $reg = implode( '|', array_map( 'preg_quote', (array) $enabled ) );
            $pagename = isset($query->query_vars['pagename']) ? (string) $query->query_vars['pagename'] : '';
            if ( $pagename !== '' && preg_match( '/^(' . $reg . '|zh|zh-reset)$/i', $pagename ) ) {
                $front_id = (int) get_option( 'page_on_front' );
                if ( $front_id ) {
                    $query->set( 'page_id', $front_id );
                    $query->set( 'pagename', '' );
                    $query->set( 'name', '' );
                }
            }
        }
    }

    public function fix_homepage_query( $query ): void {
        if ( ! is_admin() && $query && $query->is_main_query() ) {
            $variant = get_query_var( 'variant' );
            if ( $variant ) {
                $enabled = $this->config->get_enabled_languages();
                $reg = implode( '|', array_map( 'preg_quote', (array) $enabled ) );
                $req = isset( $query->query['pagename'] ) ? (string) $query->query['pagename'] : '';
                if ( $req !== '' && preg_match( '/^(' . $reg . '|zh|zh-reset)$/i', $req ) ) {
                    $front_id = (int) get_option( 'page_on_front' );
                    if ( $front_id ) {
                        $query->set( 'page_id', $front_id );
                        $query->set( 'pagename', '' );
                        $query->set( 'name', '' );
                    }
                }
            }
        }
    }

    public function render_no_conversion_block( string $block_content, array $block ) : string {
        if ( isset( $block['blockName'] ) && $block['blockName'] === 'wpcc/no-conversion' ) {
            return '<!--wpcc_NC_START-->' . $block_content . '<!--wpcc_NC_END-->';
        }
        return $block_content;
    }

    private function handle_comment_submission(): void {
        if ( isset($_POST['variant']) ) {
            $v = sanitize_text_field( (string) $_POST['variant'] );
            if ( $this->config->is_language_enabled( $v ) ) {
                $this->config->set_target_lang( $v );
            }
        }
    }

    public function cancel_incorrect_redirect( $redirect_url, $requested_url ) {
        // 如果请求路径含有语言前缀，则取消 canonical 重定向，防止丢失语言前缀
        $enabled = $this->config->get_enabled_languages();
        $reg = implode( '|', array_map( 'preg_quote', (array) $enabled ) );
        $path = is_string( $requested_url ) ? (string) $requested_url : '';
        if ( $path !== '' && preg_match( '#/(?:' . $reg . '|zh|zh-reset)(?:/|$)#i', $path ) ) {
            return false;
        }
        return $redirect_url;
    }

    private function convert_link( string $url, string $variant ): string {
        $style = (int) $this->config->get_option( 'wpcc_use_permalink', 0 );
        $style_effective = $this->is_permalinks_enabled() ? $style : 0;

        // 解析URL
        $parts = wp_parse_url( $url );
        if ( ! $parts || ! isset( $parts['scheme'], $parts['host'] ) ) {
            return $url;
        }
        $path = isset( $parts['path'] ) ? $parts['path'] : '/';
        $query = isset( $parts['query'] ) ? $parts['query'] : '';
        $frag  = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

        // 先移除现有语言前缀
        $enabled = $this->config->get_enabled_languages();
        $reg = implode( '|', array_map( 'preg_quote', (array) $enabled ) );
        $path = preg_replace( '#^/(?:' . $reg . '|zh|zh-reset)(?:/|$)#i', '/', $path );
        if ( $path === '' ) { $path = '/'; }

        if ( $style_effective !== 0 ) {
            // 前缀模式：插入 /{variant}/
            $path = '/' . trim( $variant, '/' ) . rtrim( $path, '/' ) . '/';
            $query_args = [];
            if ( $query !== '' ) {
                parse_str( $query, $query_args );
                unset( $query_args['variant'] );
            }
            $base = $parts['scheme'] . '://' . $parts['host'] . ( isset($parts['port']) ? ':' . $parts['port'] : '' ) . rtrim( $path, '/' ) . '/';
            $url2 = ! empty( $query_args ) ? add_query_arg( $query_args, $base ) : $base;
            return $url2 . $frag;
        }

        // 查询参数模式：附加 ?variant=xxx
        $base = $parts['scheme'] . '://' . $parts['host'] . ( isset($parts['port']) ? ':' . $parts['port'] : '' ) . rtrim( $path, '/' ) . '/';
        $query_args = [];
        if ( $query !== '' ) {
            parse_str( $query, $query_args );
        }
        $query_args['variant'] = $variant;
        $url2 = add_query_arg( $query_args, $base );
        return $url2 . $frag;
    }
}
