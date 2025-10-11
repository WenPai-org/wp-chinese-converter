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
        
        // 处理“根级语言前缀”访问：/zh-xx/ 或 /zh/
        if ( ! is_admin() ) {
            global $wp;
            $req = isset( $wp->request ) ? trim( (string) $wp->request, "/" ) : '';
            if ( $req !== '' ) {
                $enabled = $this->config->get_enabled_languages();
                $pattern = '/^(?:' . implode( '|', array_map( 'preg_quote', $enabled ) ) . '|zh|zh-reset)$/i';
                if ( preg_match( $pattern, $req ) ) {
                    $v = strtolower( $req );
                    // zh 哨兵：回到不转换首页（并设置 zh 偏好）
                    if ( $v === 'zh' || $v === 'zh-reset' ) {
                        $this->handle_zh_redirect();
                        return; // handle_zh_redirect 内部会 exit
                    }
                    // 其他语言：统一跳转到站点首页，避免首页 404 或重复内容
                    wp_redirect( home_url( '/' ), 302 );
                    exit;
                }
            }
        }
        
        // 处理重定向
        if ( ! is_404() && $this->config->get_redirect_to() && ! is_admin() ) {
            $redirect_url = $this->config->get_langs_urls()[ $this->config->get_redirect_to() ];
            setcookie( 'wpcc_is_redirect_' . COOKIEHASH, '1', 0, COOKIEPATH, COOKIE_DOMAIN );
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
     * 刷新重写规则（调试模式）
     */
    public function flush_rewrite_rules(): void {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
    
    // 这里添加其他必要的私有方法...
    
    /**
     * 检查 WordPress 是否启用了固定链接
     */
    private function is_permalinks_enabled(): bool {
        $structure = get_option( 'permalink_structure' );
        return is_string( $structure ) && $structure !== '';
    }
    
    /**
     * 获取无转换URL
     */
    private function get_noconversion_url(): string {
        $enabled_langs = $this->config->get_enabled_languages();
        $reg = implode( '|', array_map( 'preg_quote', $enabled_langs ) );
        
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        
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
        // 仅在 WP 启用了固定链接 且 插件启用了固定链接格式时设置重写规则
        if ( ! $this->is_permalinks_enabled() || ! $this->config->is_feature_enabled( 'wpcc_use_permalink' ) ) {
            return;
        }
        add_filter( 'rewrite_rules_array', [ $this, 'modify_rewrite_rules' ] );

        // 显式在规则顶端加入"根级变体"规则，确保 /zh-xx/ 能优先匹配到首页
        $enabled = $this->config->get_enabled_languages();
        if ( ! empty( $enabled ) && function_exists( 'add_rewrite_rule' ) ) {
            $reg = implode( '|', array_map( 'preg_quote', $enabled ) );
            add_rewrite_rule( '^(' . $reg . '|zh|zh-reset)/?$', 'index.php?variant=$matches[1]', 'top' );
            
            // 强制在init钩子中添加根级规则，确保优先级
            add_action( 'init', function() use ( $reg ) {
                add_rewrite_rule( '^(' . $reg . '|zh|zh-reset)/?$', 'index.php?variant=$matches[1]', 'top' );
            }, 5 ); // 优先级5，早于大部分其他规则
        }
    }

    /**
     * 如有必要，自动刷新一次重写规则，避免未刷新导致的 404
     */
    private function maybe_autoflush_rewrite_rules(): void {
        // 仅在启用了固定链接模式且 WP 启用了固定链接时尝试
        if ( ! $this->config->is_feature_enabled( 'wpcc_use_permalink' ) || ! $this->is_permalinks_enabled() ) {
            return;
        }
        
        // 延迟执行，确保所有规则都已加载
        add_action( 'wp_loaded', [ $this, 'check_and_flush_rules' ], 10 );
    }
    
    /**
     * 检查并刷新规则
     */
    public function check_and_flush_rules(): void {
        // 6 小时内最多尝试一次
        $last = (int) get_option( 'wpcc_rewrite_autoflush_ts', 0 );
        if ( $last && ( time() - $last ) < 6 * 3600 ) {
            return;
        }
        
        // 检查当前规则中是否已经包含根级的语言捕获规则
        $rules = get_option( 'rewrite_rules' );
        if ( ! is_array( $rules ) ) {
            $rules = [];
        }
        
        $enabled_langs = $this->config->get_enabled_languages();
        if ( empty( $enabled_langs ) ) {
            return;
        }
        
        $reg = implode( '|', $enabled_langs );
        $expected = '^(' . $reg . '|zh|zh-reset)/?$';
        $has_expected = false;
        
        foreach ( $rules as $regex => $query ) {
            if ( $regex === $expected && strpos( $query, 'variant=' ) !== false ) {
                $has_expected = true;
                break;
            }
        }
        
        if ( ! $has_expected && function_exists( 'flush_rewrite_rules' ) ) {
            // 刷新一次规则
            flush_rewrite_rules( false );
            update_option( 'wpcc_rewrite_autoflush_ts', time() );
            
            // 记录日志
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( 'WPCC: Auto-flushed rewrite rules for language variants' );
            }
        }
    }
    
    /**
     * 修改重写规则
     */
    public function modify_rewrite_rules( array $rules ): array {
        $enabled_langs = $this->config->get_enabled_languages();
        $reg = implode( '|', $enabled_langs );
        $rules2 = [];
        
        $use_permalink = $this->config->get_option( 'wpcc_use_permalink', 0 );
        
        // 首先添加根级语言规则，确保最高优先级
        $root_rule_key = '^(' . $reg . '|zh|zh-reset)/?$';
        $rules2[ $root_rule_key ] = 'index.php?variant=$matches[1]';
        
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
        $php_self = isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '';
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        
        if ( ( $php_self && ( strpos( $php_self, 'wp-comments-post.php' ) !== false
           || strpos( $php_self, 'ajax-comments.php' ) !== false
           || strpos( $php_self, 'comments-ajax.php' ) !== false )
         ) &&
         $request_method === 'POST' &&
         isset( $_POST['variant'] ) && ! empty( $_POST['variant'] )
        ) {
            $variant = sanitize_text_field( wp_unslash( $_POST['variant'] ) );
            if ( $this->config->is_language_enabled( $variant ) ) {
                $this->config->set_target_lang( $variant );
                $this->do_conversion();
            }
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
            return '<!--wpcc_NC' . $unique_id . '_START-->' . $block_content . '<!--wpcc_NC' . $unique_id . '_END-->';
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

        if ( empty( $variant ) ) {
            return $link;
        }

        // 仅当路径段或查询参数中已包含有效变体时，认为“已包含”
        $enabled = $this->config->get_enabled_languages();
        $enabled = is_array( $enabled ) ? $enabled : [];
        $variant_regex = '#/(?:' . implode( '|', array_map( 'preg_quote', $enabled ) ) . '|zh|zh-reset)(/|$)#i';

        $qpos = strpos( $link, '?' );
        $path = $qpos !== false ? substr( $link, 0, $qpos ) : $link;
        $qs   = $qpos !== false ? substr( $link, $qpos ) : '';
        $path_only = parse_url( $path, PHP_URL_PATH );
        if ( $path_only === null ) { $path_only = $path; }

        // 如路径中已有变体，仅清理冗余的 variant 查询参数然后返回
        if ( preg_match( $variant_regex, (string) $path_only ) ) {
            if ( $qpos !== false ) {
                $qs = preg_replace( '/([?&])variant=[^&]*(&|$)/', '$1', $qs );
                $qs = rtrim( $qs, '?&' );
                if ( $qs && $qs[0] !== '?' ) { $qs = '?' . ltrim( $qs, '?' ); }
            }
            return $path . $qs;
        }

        $style = (int) $this->config->get_option( 'wpcc_use_permalink', 0 );

        // 查询字符串模式或未启用固定链接（WP未启用固定链接时也回退到查询参数）
        if ( $style === 0 || ! $this->config->is_feature_enabled( 'wpcc_use_permalink' ) || ! $this->is_permalinks_enabled() ) {
            return add_query_arg( 'variant', $variant, $link );
        }

        if ( $style === 1 ) {
            // 后缀模式: .../permalink/.../zh-xx/
            return user_trailingslashit( trailingslashit( $path ) . $variant ) . $qs;
        }

        // 前缀模式 (style 2): .../zh-xx/permalink/...
        return str_replace( $wp_home, "$wp_home/$variant", $path ) . $qs;
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

        // 添加链接修复过滤器（重要！）
        if ( function_exists( 'wpcc_fix_link_conversion' ) ) {
            add_filter( 'category_feed_link', 'wpcc_fix_link_conversion' );
            add_filter( 'tag_feed_link', 'wpcc_fix_link_conversion' );
            add_filter( 'author_feed_link', 'wpcc_fix_link_conversion' );
            add_filter( 'post_comments_feed_link', 'wpcc_fix_link_conversion' );
            add_filter( 'get_comments_pagenum_link', 'wpcc_fix_link_conversion' );
            add_filter( 'get_comment_link', 'wpcc_fix_link_conversion' );
        }

        // 添加取消转换过滤器
        if ( function_exists( 'wpcc_cancel_link_conversion' ) ) {
            add_filter( 'attachment_link', 'wpcc_cancel_link_conversion' );
            add_filter( 'trackback_url', 'wpcc_cancel_link_conversion' );
        }

        // 添加分页链接修复
        if ( function_exists( 'wpcc_pagenum_link_fix' ) ) {
            add_filter( 'get_pagenum_link', 'wpcc_pagenum_link_fix' );
        }
    }
    
    /**
     * 过滤链接转换
     */
    public function filter_link_conversion( string $link ): string {
        // 使用全局函数以保持一致性
        if ( function_exists( 'wpcc_link_conversion' ) ) {
            return wpcc_link_conversion( $link, $this->config->get_target_lang() );
        }
        return $this->convert_link( $link, $this->config->get_target_lang() );
    }

    /**
     * 过滤 request 阶段的 query_vars：
     * - 当 pagename 等于语言前缀（zh-xx|zh|zh-reset）时，注入 variant 并指向首页，避免 404
     */
    public function filter_request_vars( array $qv ): array {
        if ( is_admin() ) { return $qv; }
        $enabled = $this->config->get_enabled_languages();
        if ( empty( $enabled ) ) { return $qv; }
        $candidate = '';
        if ( isset( $qv['pagename'] ) && is_string( $qv['pagename'] ) ) {
            $candidate = trim( $qv['pagename'], '/' );
        } elseif ( isset( $qv['name'] ) && is_string( $qv['name'] ) ) {
            $candidate = trim( $qv['name'], '/' );
        }
        if ( $candidate === '' ) { return $qv; }
        $langs = array_map( 'strtolower', $enabled );
        $candidate_l = strtolower( $candidate );
        if ( in_array( $candidate_l, $langs, true ) || $candidate_l === 'zh' || $candidate_l === 'zh-reset' ) {
            // 注入 variant
            $qv['variant'] = $candidate_l;
            // 指向首页
            if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
                $qv['page_id'] = (int) get_option( 'page_on_front' );
                if ( ! empty( $qv['paged'] ) ) { $qv['page'] = $qv['paged']; unset( $qv['paged'] ); }
            } else {
                // 文章作为首页：移除 pagename/name，交由 is_home 处理
                unset( $qv['pagename'], $qv['name'] );
            }
        }
        return $qv;
    }

    /**
     * 过滤 home_url，使首页链接带上变体（仅前台且存在目标语言时）
     */
    public function filter_home_url( string $url, string $path, ?string $orig_scheme, ?int $blog_id ): string {
        if ( is_admin() ) { return $url; }
        $target = $this->config->get_target_lang();
        if ( ! $target ) { return $url; }
        return $this->convert_link( $url, $target );
    }

    /**
     * 调整自定义 Logo 的链接 href（仅前台且存在目标语言时）
     */
    public function filter_custom_logo( string $html ): string {
        if ( is_admin() ) { return $html; }
        $target = $this->config->get_target_lang();
        if ( ! $target ) { return $html; }
        $home = home_url();
        $that = $this;
        $out = preg_replace_callback('/href=(\"|\')(.*?)(\1)/i', function($m) use ($home, $target, $that) {
            $href = $m[2];
            if ( strpos( $href, $home ) === 0 ) {
                $new = $that->convert_link( $href, $target );
                return 'href=' . $m[1] . esc_url( $new ) . $m[1];
            }
            return $m[0];
        }, $html );
        return $out ?: $html;
    }

    /**
     * 调整经典菜单（wp_nav_menu）输出中的 href（仅前台且存在目标语言时）
     */
    public function filter_wp_nav_menu( string $nav_menu, $args ): string {
        if ( is_admin() ) { return $nav_menu; }
        $target = $this->config->get_target_lang();
        if ( ! $target ) { return $nav_menu; }
        $home = home_url();
        $that = $this;
        $out = preg_replace_callback('/href=(\"|\')(.*?)(\1)/i', function($m) use ($home, $target, $that) {
            $href = html_entity_decode( $m[2] );
            // 仅转换本站链接
            if ( strpos( $href, $home ) === 0 ) {
                $new = $that->convert_link( $href, $target );
                return 'href=' . $m[1] . esc_url( $new ) . $m[1];
            }
            return $m[0];
        }, $nav_menu );
        return $out ?: $nav_menu;
    }

    /**
     * pre_get_posts 兜底：当请求仅为语言前缀时，强制首页查询，避免 404。
     */
    public function pre_get_posts_fix( $q ): void {
        if ( is_admin() || ! $q || ! method_exists( $q, 'is_main_query' ) || ! $q->is_main_query() ) {
            return;
        }
        $enabled = $this->config->get_enabled_languages();
        if ( empty( $enabled ) ) { return; }
        $path = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ( $path === '' ) { return; }
        $path = trim( $path, '/' );
        $candidate = $path;
        // 仅当整个路径就是语言前缀（忽略末尾斜杠和查询串）
        if ( strpos( $candidate, '/' ) !== false ) {
            // 含有更多段，忽略
            return;
        }
        $langs = array_map( 'strtolower', $enabled );
        $candidate_l = strtolower( $candidate );
        if ( in_array( $candidate_l, $langs, true ) || $candidate_l === 'zh' || $candidate_l === 'zh-reset' ) {
            // 注入 variant
            $q->set( 'variant', $candidate_l );
            // 修正首页查询
            if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
                $q->set( 'page_id', (int) get_option( 'page_on_front' ) );
                if ( $q->get( 'paged' ) ) { $q->set( 'page', $q->get( 'paged' ) ); $q->set( 'paged', 0 ); }
            } else {
                $q->set( 'pagename', '' );
                $q->set( 'name', '' );
            }
            // 避免被错误判为 404
            if ( method_exists( $q, 'is_404' ) ) {
                $q->is_404 = false;
            }
        }
    }

    /**
     * 取消错误 canonical 跳转，参考旧版逻辑
     */
    public function cancel_incorrect_redirect( $redirect_to, $redirect_from ) {
        if ( ! is_string( $redirect_to ) || ! is_string( $redirect_from ) ) { return $redirect_to; }
        if ( preg_match( '/^.*\/(zh-tw|zh-cn|zh-sg|zh-hant|zh-hans|zh-my|zh-mo|zh-hk|zh|zh-reset)\/?.+$/i', $redirect_to ) ) {
            global $wp_rewrite;
            if ( ( $wp_rewrite && $wp_rewrite->use_trailing_slashes && substr( $redirect_from, -1 ) != '/' ) ||
                 ( $wp_rewrite && ! $wp_rewrite->use_trailing_slashes && substr( $redirect_from, -1 ) == '/' ) ) {
                return user_trailingslashit( $redirect_from );
            }
            return false; // 阻止错误跳转
        }
        return $redirect_to;
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
