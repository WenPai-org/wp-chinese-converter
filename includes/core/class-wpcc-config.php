<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPCC配置管理器
 * 
 * 统一管理插件配置，减少全局变量的使用
 */
class WPCC_Config {
    
    private static ?self $instance = null;
    private array $options = [];
    private array $languages = [];
    private string $target_lang = '';
    private string $noconversion_url = '';
    private bool $redirect_to = false;
    private bool $direct_conversion_flag = false;
    private array $langs_urls = [];
    private array $debug_data = [];
    
    private function __construct() {
        $this->load_options();
        $this->init_languages();
    }
    
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 加载插件选项
     */
    private function load_options(): void {
        $this->options = get_wpcc_option( 'wpcc_options' );
        if ( empty( $this->options ) ) {
            $this->options = $this->get_default_options();
        }
    }
    
    /**
     * 获取默认配置
     */
    private function get_default_options(): array {
        return [
            // 语言与标签
            'wpcc_used_langs' => ['zh-cn','zh-tw'],
            'cntip' => '简体',
            'twtip' => '繁体',
            'hktip' => '港澳',
            'hanstip' => '简体',
            'hanttip' => '繁体',
            'sgtip' => '马新',
            'jptip' => '日式',

            // 引擎与转换
            'wpcc_engine' => 'mediawiki',
            'wpcc_search_conversion' => 1,
            'wpcc_use_fullpage_conversion' => 0,

            // 浏览器与 Cookie
            'wpcc_browser_redirect' => 0,
            'wpcc_auto_language_recong' => 0,
            'wpcc_use_cookie_variant' => 1,

            // 不转换
'wpcc_no_conversion_tag' => 'pre,code,pre.wp-block-code,pre.wp-block-preformatted,script,noscript,style,kbd,samp',
            'wpcc_no_conversion_ja' => 0,
            'wpcc_no_conversion_qtag' => 0,

            // 发表时转换
            'wpcc_enable_post_conversion' => 0,
            'wpcc_post_conversion_target' => 'zh-cn',

            // URL 与站点地图
            'wpcc_use_permalink' => 0,
            'wpcco_use_sitemap' => 0,
            'wpcco_sitemap_post_type' => 'post,page',

            // SEO
            'wpcc_enable_hreflang_tags' => 1,
            'wpcc_hreflang_x_default' => 'zh-cn',
            'wpcc_enable_schema_conversion' => 1,
            'wpcc_enable_meta_conversion' => 1,

            // 其他
            'wpcc_flag_option' => 1,
            'wpcc_trackback_plugin_author' => 0,
            'wpcc_add_author_link' => 0,
            'wpcc_translate_type' => 0,
            'nctip' => '',
            'wpcc_enable_cache_addon' => 1,
            'wpcc_enable_network_module' => 0,
        ];
    }
    
    /**
     * 初始化语言配置
     */
    private function init_languages(): void {
        $this->languages = [
            'zh-cn' => [ 'zhconversion_cn', 'cntip', __( '简体中文', 'wp-chinese-converter' ), 'zh-CN' ],
            'zh-tw' => [ 'zhconversion_tw', 'twtip', __( '台灣正體', 'wp-chinese-converter' ), 'zh-TW' ],
            'zh-hk' => [ 'zhconversion_hk', 'hktip', __( '港澳繁體', 'wp-chinese-converter' ), 'zh-HK' ],
            'zh-hans' => [ 'zhconversion_hans', 'hanstip', __( '简体中文', 'wp-chinese-converter' ), 'zh-Hans' ],
            'zh-hant' => [ 'zhconversion_hant', 'hanttip', __( '繁体中文', 'wp-chinese-converter' ), 'zh-Hant' ],
            'zh-sg' => [ 'zhconversion_sg', 'sgtip', __( '马新简体', 'wp-chinese-converter' ), 'zh-SG' ],
            'zh-jp' => [ 'zhconversion_jp', 'jptip', __( '日式汉字', 'wp-chinese-converter' ), 'zh-JP' ],
        ];
    }
    
    /**
     * 获取配置选项
     */
    public function get_option( string $key, $default = null ) {
        return $this->options[$key] ?? $default;
    }
    
    /**
     * 设置配置选项
     */
    public function set_option( string $key, $value ): void {
        $this->options[$key] = $value;
    }
    
    /**
     * 更新配置选项到数据库
     */
    public function save_options(): bool {
        return update_wpcc_option( 'wpcc_options', $this->options );
    }
    
    /**
     * 获取所有配置选项
     */
    public function get_all_options(): array {
        return $this->options;
    }
    
    /**
     * 获取语言配置
     */
    public function get_languages(): array {
        return $this->languages;
    }
    
    /**
     * 获取特定语言配置
     */
    public function get_language( string $lang_code ): ?array {
        return $this->languages[$lang_code] ?? null;
    }
    
    /**
     * 设置目标语言
     */
    public function set_target_lang( string $lang ): void {
        $this->target_lang = $lang;
    }
    
    /**
     * 获取目标语言
     */
    public function get_target_lang(): string {
        return $this->target_lang;
    }
    
    /**
     * 设置无转换URL
     */
    public function set_noconversion_url( string $url ): void {
        $this->noconversion_url = $url;
    }
    
    /**
     * 获取无转换URL
     */
    public function get_noconversion_url(): string {
        return $this->noconversion_url;
    }
    
    /**
     * 设置语言URL映射
     */
    public function set_langs_urls( array $urls ): void {
        $this->langs_urls = $urls;
    }
    
    /**
     * 获取语言URL映射
     */
    public function get_langs_urls(): array {
        return $this->langs_urls;
    }
    
    /**
     * 设置重定向标志
     */
    public function set_redirect_to( bool $redirect ): void {
        $this->redirect_to = $redirect;
    }
    
    /**
     * 获取重定向标志
     */
    public function get_redirect_to(): bool {
        return $this->redirect_to;
    }
    
    /**
     * 设置直接转换标志
     */
    public function set_direct_conversion_flag( bool $flag ): void {
        $this->direct_conversion_flag = $flag;
    }
    
    /**
     * 获取直接转换标志
     */
    public function get_direct_conversion_flag(): bool {
        return $this->direct_conversion_flag;
    }
    
    /**
     * 添加调试数据
     */
    public function add_debug_data( string $key, $data ): void {
        if ( defined( 'wpcc_DEBUG' ) && wpcc_DEBUG ) {
            $this->debug_data[$key] = $data;
        }
    }
    
    /**
     * 获取调试数据
     */
    public function get_debug_data(): array {
        return $this->debug_data;
    }
    
    /**
     * 检查是否启用了指定功能
     */
    public function is_feature_enabled( string $feature ): bool {
        return (bool) $this->get_option( $feature, false );
    }
    
    /**
     * 获取启用的语言列表
     */
    public function get_enabled_languages(): array {
        return $this->get_option( 'wpcc_used_langs', [] );
    }
    
    /**
     * 检查语言是否启用
     */
    public function is_language_enabled( string $lang_code ): bool {
        return in_array( $lang_code, $this->get_enabled_languages(), true );
    }
    
    /**
     * 获取转换引擎
     */
    public function get_conversion_engine(): string {
        return $this->get_option( 'wpcc_engine', 'opencc' );
    }
    
    /**
     * 验证配置完整性
     */
    public function validate_config(): array {
        $errors = [];
        
        if ( empty( $this->get_enabled_languages() ) ) {
            $errors[] = 'No languages enabled';
        }
        
        $engine = $this->get_conversion_engine();
        if ( ! in_array( $engine, ['opencc', 'mediawiki'] ) ) {
            $errors[] = 'Invalid conversion engine: ' . $engine;
        }
        
        return $errors;
    }
}