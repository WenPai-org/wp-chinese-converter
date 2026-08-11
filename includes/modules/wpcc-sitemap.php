<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
add_action( 'init', 'wpcc_sitemap_init', 20 );

function wpcc_sitemap_init() {
    global $wpcc_options;
    
    if ( isset($wpcc_options) && is_array($wpcc_options) && isset($wpcc_options['wpcco_use_sitemap']) && $wpcc_options['wpcco_use_sitemap'] == 1 ) {
        wpcc_sitemap_setup();
    }
}

function wpcc_sitemap_setup() {
    add_filter( 'rewrite_rules_array', 'wpcc_sitemap_rewrite_rules' );
    add_filter( 'query_vars', 'custom_sitemap_query_vars' );
    
    if ( ! get_option( 'wpcc_sitemap_rewrite_rules_flushed' ) ) {
        flush_rewrite_rules();
        update_option( 'wpcc_sitemap_rewrite_rules_flushed', '1' );
    }
    
    add_action( 'template_redirect', 'custom_sitemap_template_redirect', 9999 );
}



function wpcc_sitemap_rewrite_rules( $rules ) {
    global $wpcc_options;
    $langs = isset($wpcc_options['wpcc_used_langs']) && is_array($wpcc_options['wpcc_used_langs']) ? $wpcc_options['wpcc_used_langs'] : array('zh-cn','zh-tw','zh-hk');
    $langs = array_values(array_unique(array_filter($langs)));
    $pattern = implode('|', array_map('preg_quote', $langs));

    $new_rules = array();
    if ( $pattern !== '' ) {
        // 主网站地图索引
        $new_rules['^(' . $pattern . ')/wp-sitemap\.xml/?$'] = 'index.php?wpcc_sitemap_lang=$matches[1]';
        $new_rules['^(' . $pattern . ')/sitemap\.xml/?$'] = 'index.php?wpcc_sitemap_lang=$matches[1]';
        
        // 文章网站地图
        $new_rules['^sitemap-(' . $pattern . ')-(\d+)\.xml/?$'] = 'index.php?wpcc_sitemap_lang=$matches[1]&wpcc_sitemap_page=$matches[2]';
        
        // 分类网站地图
        $new_rules['^sitemap-taxonomy-([a-zA-Z0-9_]+)-(' . $pattern . ')-(\d+)\.xml/?$'] = 'index.php?wpcc_sitemap_lang=$matches[2]&wpcc_sitemap_taxonomy=$matches[1]&wpcc_sitemap_page=$matches[3]';
    }
    return array_merge( $new_rules, $rules );
}

function custom_sitemap_query_vars( $vars ) {
    $vars[] = 'wpcc_sitemap_lang';
    $vars[] = 'wpcc_sitemap_page';
    $vars[] = 'wpcc_sitemap_taxonomy';
    return $vars;
}

/**
 * 专门用于站点地图的URL转换函数
 * 与wpcc_link_conversion不同，这个函数会强制转换URL到指定语言，即使原URL已包含语言变体
 */
function wpcc_sitemap_link_conversion( $link, $variant ) {
    global $wpcc_options;
    
    if ( empty( $variant ) || empty( $link ) ) {
        return $link;
    }
    
    // 获取原始URL（去除所有语言变体）
    $original_link = wpcc_remove_language_from_url( $link );
    
    $style = (int) ( $wpcc_options['wpcc_use_permalink'] ?? 0 );
    $permalinks_enabled = (string) get_option( 'permalink_structure' ) !== '';
    
    // 当 WP 未启用固定链接时，使用查询参数
    if ( ! $permalinks_enabled || $style === 0 ) {
        return add_query_arg( 'variant', $variant, $original_link );
    }
    
    // Split path and query
    $qpos = strpos( $original_link, '?' );
    $path = $qpos !== false ? substr( $original_link, 0, $qpos ) : $original_link;
    $qs   = $qpos !== false ? substr( $original_link, $qpos ) : '';
    
    if ( $style === 1 ) {
        // suffix style: /postname/zh-xx/
        return user_trailingslashit( trailingslashit( $path ) . $variant ) . $qs;
    }
    
    // prefix style (2): /zh-xx/postname/
    if ( is_multisite() && wpcc_mobile_exist( 'network' ) ) {
        $sites = get_sites();
        foreach ( $sites as $site ) {
            if ( '/' == $site->path ) {
                continue;
            }
            $path_seg = str_replace( '/', '', $site->path );
            $sub_url = "$site->domain/$path_seg";
            if ( false !== strpos( $path, $sub_url ) ) {
                return str_replace( $sub_url, "$sub_url/$variant", $path ) . $qs;
            }
        }
    }
    
    // 默认前缀样式
    $home_url = home_url();
    return str_replace( $home_url, $home_url . '/' . $variant, $original_link );
}

/**
 * 从URL中移除语言变体
 */
function wpcc_remove_language_from_url( $url ) {
    global $wpcc_options;
    
    if ( empty( $url ) ) {
        return $url;
    }
    
    // 获取启用的语言列表
    $enabled = isset( $wpcc_options['wpcc_used_langs'] ) && is_array( $wpcc_options['wpcc_used_langs'] ) ? $wpcc_options['wpcc_used_langs'] : [];
    if ( empty( $enabled ) ) {
        return $url;
    }
    
    // 移除查询参数中的variant
    $url = remove_query_arg( 'variant', $url );
    
    // 创建语言变体的正则表达式
    $reg = implode( '|', array_map( 'preg_quote', $enabled ) );
    $variant_regex = '/\/(' . $reg . '|zh|zh-reset)(\/|$)/i';
    
    // 移除路径中的语言变体
    $parsed = wp_parse_url( $url );
    if ( isset( $parsed['path'] ) ) {
        $path = $parsed['path'];
        $path = preg_replace( $variant_regex, '/', $path );
        $path = rtrim( $path, '/' );
        if ( empty( $path ) ) {
            $path = '/';
        }
        
        // 重建URL
        $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
        $query = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
        $fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
        
        $url = $scheme . $host . $port . $path . $query . $fragment;
    }
    
    return $url;
}

function custom_sitemap_template_redirect() {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

    // 动态语言模式：仅允许已启用语言
    $enabled = isset($GLOBALS['wpcc_options']['wpcc_used_langs']) && is_array($GLOBALS['wpcc_options']['wpcc_used_langs']) ? $GLOBALS['wpcc_options']['wpcc_used_langs'] : array('zh-cn','zh-tw','zh-hk');
    $enabled = array_values(array_unique(array_filter($enabled)));
    $pat = implode('|', array_map('preg_quote', $enabled));

    $lang = '';
    if ( $pat !== '' && preg_match( '#/(' . $pat . ')/sitemap\.xml/?$#i', $uri, $matches ) ) {
        $lang = strtolower($matches[1]);
    } elseif ( $pat !== '' && preg_match( '#/(' . $pat . ')/wp-sitemap\.xml/?$#i', $uri, $matches ) ) {
        $lang = strtolower($matches[1]);
    }

    if ( $lang !== '' ) {
        if ( ! in_array( $lang, $enabled, true ) ) {
            status_header( 404 );
            exit;
        }
        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'HTTP/1.1 200 OK' );

        $content = generate_sitemap_index( $lang );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete XML document; dynamic nodes are escaped while it is assembled.
        echo $content !== '' ? $content : '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap not found</error>';
        exit;
    }

    // 处理文章网站地图
    if ( $pat !== '' && preg_match( '#/sitemap-(' . $pat . ')-(\d+)\.xml/?$#i', $uri, $matches ) ) {
        $lang = strtolower($matches[1]);
        $page = (int) $matches[2];
        if ( ! in_array( $lang, $enabled, true ) ) {
            status_header( 404 );
            exit;
        }
        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'HTTP/1.1 200 OK' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete XML document; dynamic nodes are escaped while it is assembled.
        echo generate_paged_sitemap_content( $lang, $page );
        exit;
    }

    // 处理分类网站地图
    if ( $pat !== '' && preg_match( '#/sitemap-taxonomy-([a-zA-Z0-9_]+)-(' . $pat . ')-(\d+)\.xml/?$#i', $uri, $matches ) ) {
        $taxonomy = sanitize_key($matches[1]);
        $lang = strtolower($matches[2]);
        $page = (int) $matches[3];
        
        if ( ! in_array( $lang, $enabled, true ) ) {
            status_header( 404 );
            exit;
        }
        
        // 验证分类法是否存在且公开
        if ( ! taxonomy_exists( $taxonomy ) || ! is_taxonomy_viewable( $taxonomy ) ) {
            status_header( 404 );
            exit;
        }
        
        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'HTTP/1.1 200 OK' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete XML document; dynamic nodes are escaped while it is assembled.
        echo generate_taxonomy_sitemap_content( $taxonomy, $lang, $page );
        exit;
    }

    if ( preg_match( '#/sitemap-style\.xsl/?$#i', $uri ) ) {
        header( 'Content-Type: text/xsl; charset=utf-8' );
        header( 'HTTP/1.1 200 OK' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete static XSL document with no dynamic values.
        echo generate_sitemap_styles();
        exit;
    }
}

/**
 * Return only public, registered post types selected for WPCC sitemaps.
 */
function wpcc_get_sitemap_post_types() {
    global $wpcc_options;

    $configured = isset( $wpcc_options['wpcco_sitemap_post_type'] )
        ? explode( ',', (string) $wpcc_options['wpcco_sitemap_post_type'] )
        : array( 'post', 'page' );
    $configured = array_values( array_unique( array_filter( array_map( 'sanitize_key', $configured ) ) ) );
    $public = get_post_types( array( 'public' => true ), 'names' );
    $post_types = array_values( array_intersect( $configured, $public ) );

    return $post_types ?: array( 'post', 'page' );
}

function generate_sitemap_index( string $lang ) {
    global $wpcc_options;

    // 仅允许启用语言
    $enabled = isset($wpcc_options['wpcc_used_langs']) && is_array($wpcc_options['wpcc_used_langs']) ? $wpcc_options['wpcc_used_langs'] : array('zh-cn','zh-tw');
    if ( ! in_array( $lang, $enabled, true ) ) {
        return '';
    }

    $max_urls_per_sitemap = 1000;

    $post_types = wpcc_get_sitemap_post_types();
    $total_posts = 0;
    foreach ( $post_types as $post_type ) {
        $counts = wp_count_posts( $post_type );
        $total_posts += isset( $counts->publish ) ? (int) $counts->publish : 0;
    }
    
    $total_sitemaps = ceil( $total_posts / $max_urls_per_sitemap );
    
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
    $sitemap .= '<?xml-stylesheet type="text/xsl" href="' . esc_xml( site_url( '/sitemap-style.xsl' ) ) . '"?>';
    $sitemap .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    
    // 添加文章网站地图
    for ( $i = 1; $i <= $total_sitemaps; $i ++ ) {
        $sitemap .= '<sitemap>';
        $sitemap .= '<loc>' . esc_xml( site_url( "/sitemap-{$lang}-{$i}.xml" ) ) . '</loc>';
        $sitemap .= '<lastmod>' . wp_date( 'Y-m-d' ) . '</lastmod>';
        $sitemap .= '</sitemap>';
    }
    
    // 添加分类网站地图
    $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
    foreach ( $taxonomies as $taxonomy ) {
        // 跳过不需要的分类法
        if ( in_array( $taxonomy->name, array( 'post_format', 'nav_menu' ), true ) ) {
            continue;
        }
        
        // 获取该分类法的条目数量
        $term_count = wp_count_terms( array(
            'taxonomy' => $taxonomy->name,
            'hide_empty' => true,
        ) );
        
        if ( is_wp_error( $term_count ) || $term_count === 0 ) {
            continue;
        }
        
        // 计算需要的分页数
        $taxonomy_sitemaps = ceil( $term_count / $max_urls_per_sitemap );
        
        for ( $i = 1; $i <= $taxonomy_sitemaps; $i ++ ) {
            $sitemap .= '<sitemap>';
            $sitemap .= '<loc>' . esc_xml( site_url( "/sitemap-taxonomy-{$taxonomy->name}-{$lang}-{$i}.xml" ) ) . '</loc>';
            $sitemap .= '<lastmod>' . wp_date( 'Y-m-d' ) . '</lastmod>';
            $sitemap .= '</sitemap>';
        }
    }
    
    $sitemap .= '</sitemapindex>';
    
    return $sitemap;
}



function generate_paged_sitemap_content( string $lang, int $page ) {
    global $wpcc_options;

    // 仅允许启用语言
    $enabled = isset($wpcc_options['wpcc_used_langs']) && is_array($wpcc_options['wpcc_used_langs']) ? $wpcc_options['wpcc_used_langs'] : array('zh-cn','zh-tw');
    if ( ! in_array( $lang, $enabled, true ) ) {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />';
    }

    $max_urls_per_sitemap = 1000;

    $post_types = wpcc_get_sitemap_post_types();
    $page = max( 1, $page );
    $offset = ( $page - 1 ) * $max_urls_per_sitemap;
    
    $postsForSitemap = get_posts( array(
        'numberposts' => $max_urls_per_sitemap,
        'orderby'     => 'modified',
        'post_type'   => $post_types,
        'order'       => 'DESC',
        'offset'      => $offset,
    ) );
    
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
    $sitemap .= '<?xml-stylesheet type="text/xsl" href="' . esc_xml( site_url( '/sitemap-style.xsl' ) ) . '"?>';
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    
    foreach ( $postsForSitemap as $post ) {
        setup_postdata( $post );
        
        $postdate = explode( " ", $post->post_modified );
        
        $sitemap .= '<url>';
        $sitemap .= '<loc>' . esc_xml( wpcc_sitemap_link_conversion( get_permalink( $post->ID ), $lang ) ) . '</loc>';
        $sitemap .= '<lastmod>' . $postdate[0] . '</lastmod>';
        $sitemap .= '<changefreq>weekly</changefreq>';
        $sitemap .= '<priority>0.6</priority>';
        $sitemap .= '</url>';
    }
    
    $sitemap .= '</urlset>';
    
    return $sitemap;
}

function generate_sitemap_styles() {
    return '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9">
<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>XML Sitemap</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style type="text/css">
            body {
                font-family: Arial, sans-serif;
                margin: 40px;
                background-color: #f5f5f5;
            }
            .header {
                background-color: #fff;
                border: 1px solid #ddd;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            h1 {
                color: #333;
                margin: 0 0 10px 0;
            }
            .description {
                color: #666;
                font-size: 14px;
            }
            .sitemap-content {
                background-color: #fff;
                border: 1px solid #ddd;
                border-radius: 5px;
                overflow: hidden;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th {
                background-color: #f8f9fa;
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
                font-weight: bold;
                color: #333;
            }
            td {
                padding: 12px;
                border-bottom: 1px solid #eee;
            }
            tr:hover {
                background-color: #f8f9fa;
            }
            .url {
                color: #1a73e8;
                text-decoration: none;
                word-break: break-all;
            }
            .url:hover {
                text-decoration: underline;
            }
            .lastmod {
                color: #666;
                font-size: 13px;
            }
            .priority {
                color: #666;
                font-size: 13px;
            }
            .changefreq {
                color: #666;
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>XML Sitemap</h1>
            <div class="description">
                This is a XML Sitemap which is supposed to be processed by search engines like Google, MSN Search and Yahoo.
            </div>
        </div>
        
        <div class="sitemap-content">
            <xsl:choose>
                <xsl:when test="sitemap:sitemapindex">
                    <table>
                        <tr>
                            <th>Sitemap</th>
                            <th>Last Modified</th>
                        </tr>
                        <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
                            <tr>
                                <td>
                                    <a href="{sitemap:loc}" class="url">
                                        <xsl:value-of select="sitemap:loc"/>
                                    </a>
                                </td>
                                <td class="lastmod">
                                    <xsl:value-of select="sitemap:lastmod"/>
                                </td>
                            </tr>
                        </xsl:for-each>
                    </table>
                </xsl:when>
                <xsl:otherwise>
                    <table>
                        <tr>
                            <th>URL</th>
                            <th>Last Modified</th>
                            <th>Change Frequency</th>
                            <th>Priority</th>
                        </tr>
                        <xsl:for-each select="sitemap:urlset/sitemap:url">
                            <tr>
                                <td>
                                    <a href="{sitemap:loc}" class="url">
                                        <xsl:value-of select="sitemap:loc"/>
                                    </a>
                                </td>
                                <td class="lastmod">
                                    <xsl:value-of select="sitemap:lastmod"/>
                                </td>
                                <td class="changefreq">
                                    <xsl:value-of select="sitemap:changefreq"/>
                                </td>
                                <td class="priority">
                                    <xsl:value-of select="sitemap:priority"/>
                                </td>
                            </tr>
                        </xsl:for-each>
                    </table>
                </xsl:otherwise>
            </xsl:choose>
        </div>
    </body>
    </html>
</xsl:template>
</xsl:stylesheet>';
}

/**
 * 生成分类网站地图内容
 *
 * @param string $taxonomy 分类法名称
 * @param string $lang 语言代码
 * @param int $page 页码
 * @return string XML内容
 */
function generate_taxonomy_sitemap_content( string $taxonomy, string $lang, int $page ) {
    global $wpcc_options;

    // 仅允许启用语言
    $enabled = isset($wpcc_options['wpcc_used_langs']) && is_array($wpcc_options['wpcc_used_langs']) ? $wpcc_options['wpcc_used_langs'] : array('zh-cn','zh-tw');
    if ( ! in_array( $lang, $enabled, true ) ) {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />';
    }

    // 验证分类法
    if ( ! taxonomy_exists( $taxonomy ) || ! is_taxonomy_viewable( $taxonomy ) ) {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />';
    }

    $max_urls_per_sitemap = 1000;
    $page = max( 1, $page );
    $offset = ( $page - 1 ) * $max_urls_per_sitemap;
    
    // 获取分类条目
    $terms = get_terms( array(
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
        'number' => $max_urls_per_sitemap,
        'offset' => $offset,
        'orderby' => 'count',
        'order' => 'DESC',
    ) );
    
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />';
    }
    
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
    $sitemap .= '<?xml-stylesheet type="text/xsl" href="' . esc_xml( site_url( '/sitemap-style.xsl' ) ) . '"?>';
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    
    foreach ( $terms as $term ) {
        // 检查是否应该索引此条目
        if ( ! wpcc_should_index_term( $term ) ) {
            continue;
        }
        
        $term_link = get_term_link( $term, $taxonomy );
        if ( is_wp_error( $term_link ) ) {
            continue;
        }
        
        // 转换链接到指定语言
        $converted_link = wpcc_sitemap_link_conversion( $term_link, $lang );
        
        $sitemap .= '<url>';
        $sitemap .= '<loc>' . esc_xml( $converted_link ) . '</loc>';
        $sitemap .= '<lastmod>' . wp_date( 'Y-m-d' ) . '</lastmod>';
        $sitemap .= '<changefreq>weekly</changefreq>';
        $sitemap .= '<priority>0.5</priority>';
        $sitemap .= '</url>';
    }
    
    $sitemap .= '</urlset>';
    
    return $sitemap;
}

/**
 * 检查是否应该索引分类条目
 *
 * @param WP_Term $term 分类条目
 * @return bool
 */
function wpcc_should_index_term( $term ) {
    // 可以在这里添加更多的检查逻辑
    // 例如检查条目的元数据中是否有 noindex 标记
    
    // 基本检查：确保条目有内容
    if ( $term->count === 0 ) {
        return false;
    }
    
    // 可以添加自定义过滤器让其他插件或主题控制
    return apply_filters( 'wpcc_should_index_term', true, $term );
}

?>
