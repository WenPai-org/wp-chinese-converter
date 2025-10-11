<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPCC语言配置中心
 * 
 * 统一管理所有语言相关配置，避免重复定义
 * 这是语言配置的唯一真实来源（Single Source of Truth）
 */
final class WPCC_Language_Config {
    
    /**
     * 语言配置数组
     * 格式: [语言代码 => [转换函数名, 选项键名, 默认显示名, BCP47代码]]
     */
    private static array $languages = [];
    
    /**
     * 默认语言显示名称
     */
    private static array $default_names = [
        'zh-cn' => '简体中文',
        'zh-tw' => '台灣正體',
        'zh-hk' => '港澳繁體',
        'zh-hans' => '简体中文',
        'zh-hant' => '繁体中文',
        'zh-sg' => '马新简体',
        'zh-jp' => '日式汉字',
    ];
    
    /**
     * 初始化语言配置
     */
    public static function init(): void {
        if ( ! empty( self::$languages ) ) {
            return;
        }
        
        self::$languages = [
            'zh-cn'   => [ 'zhconversion_cn',   'cntip',   self::get_translated_name( 'zh-cn' ),   'zh-CN'   ],
            'zh-tw'   => [ 'zhconversion_tw',   'twtip',   self::get_translated_name( 'zh-tw' ),   'zh-TW'   ],
            'zh-hk'   => [ 'zhconversion_hk',   'hktip',   self::get_translated_name( 'zh-hk' ),   'zh-HK'   ],
            'zh-hans' => [ 'zhconversion_hans', 'hanstip', self::get_translated_name( 'zh-hans' ), 'zh-Hans' ],
            'zh-hant' => [ 'zhconversion_hant', 'hanttip', self::get_translated_name( 'zh-hant' ), 'zh-Hant' ],
            'zh-sg'   => [ 'zhconversion_sg',   'sgtip',   self::get_translated_name( 'zh-sg' ),   'zh-SG'   ],
            'zh-jp'   => [ 'zhconversion_jp',   'jptip',   self::get_translated_name( 'zh-jp' ),   'zh-JP'   ],
        ];
    }
    
    /**
     * 获取翻译后的语言名称
     */
    private static function get_translated_name( string $lang_code ): string {
        // 只在WordPress完全初始化后才使用翻译函数
        if ( function_exists( 'did_action' ) && did_action( 'init' ) && function_exists( '__' ) ) {
            return __( self::$default_names[ $lang_code ] ?? $lang_code, 'wp-chinese-converter' );
        }
        return self::$default_names[ $lang_code ] ?? $lang_code;
    }
    
    /**
     * 获取所有语言配置
     */
    public static function get_all_languages(): array {
        self::init();
        return self::$languages;
    }
    
    /**
     * 获取特定语言配置
     */
    public static function get_language( string $lang_code ): ?array {
        self::init();
        return self::$languages[ $lang_code ] ?? null;
    }
    
    /**
     * 获取语言显示名称
     */
    public static function get_language_name( string $lang_code, ?array $custom_names = null ): string {
        self::init();
        
        // 优先使用自定义名称
        if ( $custom_names && isset( $custom_names[ $lang_code ] ) ) {
            return $custom_names[ $lang_code ];
        }
        
        // 使用配置中的名称
        if ( isset( self::$languages[ $lang_code ][2] ) ) {
            return self::$languages[ $lang_code ][2];
        }
        
        // 降级到默认名称
        return self::$default_names[ $lang_code ] ?? $lang_code;
    }
    
    /**
     * 获取语言的BCP47代码
     */
    public static function get_bcp47_code( string $lang_code ): string {
        self::init();
        return self::$languages[ $lang_code ][3] ?? $lang_code;
    }
    
    /**
     * 获取语言的转换函数名
     */
    public static function get_conversion_function( string $lang_code ): ?string {
        self::init();
        return self::$languages[ $lang_code ][0] ?? null;
    }
    
    /**
     * 获取语言的选项键名
     */
    public static function get_option_key( string $lang_code ): ?string {
        self::init();
        return self::$languages[ $lang_code ][1] ?? null;
    }
    
    /**
     * 检查语言代码是否有效
     */
    public static function is_valid_language( string $lang_code ): bool {
        self::init();
        return isset( self::$languages[ $lang_code ] );
    }
    
    /**
     * 获取所有有效的语言代码
     */
    public static function get_valid_language_codes(): array {
        self::init();
        return array_keys( self::$languages );
    }
    
    /**
     * 获取自定义语言名称配置
     * 
     * @param array $options 插件选项数组
     * @return array 自定义名称数组
     */
    public static function get_custom_names( array $options ): array {
        self::init();
        
        $custom_names = [];
        foreach ( self::$languages as $code => $config ) {
            $option_key = $config[1];
            $default_name = self::$default_names[ $code ];
            $custom_names[ $code ] = $options[ $option_key ] ?? $default_name;
        }
        
        return $custom_names;
    }
    
    /**
     * 获取默认语言名称
     */
    public static function get_default_names(): array {
        return self::$default_names;
    }
}
