<?php
add_action( 'init', 'wpcc_sitemap_init', 20 );

function wpcc_sitemap_init() {
    global $wpcc_options;
    
    if ( isset($wpcc_options) && is_array($wpcc_options) && isset($wpcc_options['wpcco_use_sitemap']) && $wpcc_options['wpcco_use_sitemap'] == 1 ) {
        wpcc_sitemap_setup();
    }
}

function wpcc_sitemap_setup() {
    remove_action( 'init', 'wp_sitemaps_get_server' );
    add_filter( 'wp_sitemaps_enabled', '__return_false' );
    
    add_filter( 'rewrite_rules_array', 'wpcc_sitemap_rewrite_rules' );
    add_filter( 'query_vars', 'custom_sitemap_query_vars' );
    
    if ( ! get_option( 'wpcc_sitemap_rewrite_rules_flushed' ) ) {
        flush_rewrite_rules();
        update_option( 'wpcc_sitemap_rewrite_rules_flushed', '1' );
    }
    
    add_action( 'template_redirect', 'custom_sitemap_template_redirect', 9999 );
}



function wpcc_sitemap_rewrite_rules( $rules ) {
    $new_rules = array();
    $new_rules['^(zh-tw|zh-cn|zh-hk|zh-sg|zh-hans|zh-hant)/wp-sitemap\.xml/?$'] = 'index.php?wpcc_sitemap_lang=$matches[1]';
    $new_rules['^(zh-tw|zh-cn|zh-hk|zh-sg|zh-hans|zh-hant)/sitemap\.xml/?$'] = 'index.php?wpcc_sitemap_lang=$matches[1]';
    $new_rules['^sitemap-(zh-tw|zh-cn|zh-hk|zh-sg|zh-hans|zh-hant)-(\d+)\.xml/?$'] = 'index.php?wpcc_sitemap_lang=$matches[1]&wpcc_sitemap_page=$matches[2]';
    return array_merge( $new_rules, $rules );
}

function custom_sitemap_query_vars( $vars ) {
    $vars[] = 'wpcc_sitemap_lang';
    $vars[] = 'wpcc_sitemap_page';
    return $vars;
}

function custom_sitemap_template_redirect() {
    $uri = $_SERVER['REQUEST_URI'];
    
    $lang = '';
    if ( preg_match( '/\/(zh-tw|zh-cn|zh-hk|zh-sg|zh-hans|zh-hant)\/sitemap\.xml\/?$/', $uri, $matches ) ) {
        $lang = $matches[1];
    } elseif ( preg_match( '/\/(zh-tw|zh-cn|zh-hk|zh-sg|zh-hans|zh-hant)\/wp-sitemap\.xml\/?$/', $uri, $matches ) ) {
        $lang = $matches[1];
    }
    
    if ( ! empty( $lang ) ) {
        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'HTTP/1.1 200 OK' );
        
        $content = generate_sitemap_index( $lang );
        
        if ( ! empty( $content ) ) {
            echo $content;
        } else {
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap not found</error>';
        }
        
        exit;
    }
    
    if ( preg_match( '/\/sitemap-(zh-tw|zh-cn|zh-hk|zh-sg|zh-hans|zh-hant)-(\d+)\.xml\/?$/', $uri, $matches ) ) {
        $lang = $matches[1];
        $page = (int) $matches[2];
        
        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'HTTP/1.1 200 OK' );
        
        echo generate_paged_sitemap_content( $lang, $page );
        exit;
    }
    
    if ( preg_match( '/\/sitemap-style\.xsl\/?$/', $uri ) ) {
        header( 'Content-Type: text/xsl; charset=utf-8' );
        header( 'HTTP/1.1 200 OK' );
        
        echo generate_sitemap_styles();
        exit;
    }
}

function generate_sitemap_index( string $lang ) {
    global $wpdb, $wpcc_options;
    
    $max_urls_per_sitemap = 1000;
    
    if ( empty( $wpcc_options['wpcco_sitemap_post_type'] ) ) {
        $post_type = 'post,page';
    } else {
        $post_type = isset($wpcc_options['wpcco_sitemap_post_type']) ? $wpcc_options['wpcco_sitemap_post_type'] : 'post,page';
    }
    
    $post_type = explode( ',', $post_type );
    $post_type_sql = '';
    foreach ( $post_type as $item ) {
        $post_type_sql .= "'$item',";
    }
    $post_type_sql = substr( $post_type_sql, 0, - 1 );
    $total_posts = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type in ($post_type_sql) AND post_status = 'publish'" );
    
    $total_sitemaps = ceil( $total_posts / $max_urls_per_sitemap );
    
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
    $sitemap .= '<?xml-stylesheet type="text/xsl" href="' . site_url( '/sitemap-style.xsl' ) . '"?>';
    $sitemap .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    
    for ( $i = 1; $i <= $total_sitemaps; $i ++ ) {
        $sitemap .= '<sitemap>';
        $sitemap .= '<loc>' . site_url( "/sitemap-{$lang}-{$i}.xml" ) . '</loc>';
        $sitemap .= '<lastmod>' . date( 'Y-m-d' ) . '</lastmod>';
        $sitemap .= '</sitemap>';
    }
    
    $sitemap .= '</sitemapindex>';
    
    return $sitemap;
}



function generate_paged_sitemap_content( string $lang, int $page ) {
    global $wpcc_options;
    
    $max_urls_per_sitemap = 1000;
    
    if ( empty( $wpcc_options['wpcco_sitemap_post_type'] ) ) {
        $post_type = 'post,page';
    } else {
        $post_type = isset($wpcc_options['wpcco_sitemap_post_type']) ? $wpcc_options['wpcco_sitemap_post_type'] : 'post,page';
    }
    
    $offset = ( $page - 1 ) * $max_urls_per_sitemap;
    
    $postsForSitemap = get_posts( array(
        'numberposts' => $max_urls_per_sitemap,
        'orderby'     => 'modified',
        'post_type'   => explode( ',', $post_type ),
        'order'       => 'DESC',
        'offset'      => $offset,
    ) );
    
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
    $sitemap .= '<?xml-stylesheet type="text/xsl" href="' . site_url( '/sitemap-style.xsl' ) . '"?>';
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    
    foreach ( $postsForSitemap as $post ) {
        setup_postdata( $post );
        
        $postdate = explode( " ", $post->post_modified );
        
        $sitemap .= '<url>';
        $sitemap .= '<loc>' . wpcc_link_conversion( get_permalink( $post->ID ), $lang ) . '</loc>';
        $sitemap .= '<lastmod>' . $postdate[0] . '</lastmod>';
        $sitemap .= '<changefreq>weekly</changefreq>';
        $sitemap .= '<priority>0.6</priority>';
        $sitemap .= '</url>';
    }
    
    $sitemap .= '</urlset>';
    
    return $sitemap;
}

function generate_sitemap_styles() {
    return <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
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
</xsl:stylesheet>
XSL;
}

?>
