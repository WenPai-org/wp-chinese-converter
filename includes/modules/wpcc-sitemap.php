<?php
global $wpcc_options;

// 检查 $wpcc_options 是否存在且不为空，并检查相关键是否存在
if ( isset($wpcc_options) && is_array($wpcc_options) && isset($wpcc_options['wpcco_use_sitemap']) && $wpcc_options['wpcco_use_sitemap'] == 1 ) {
    // 添加自定义重写规则
    add_action( 'init', 'custom_sitemap_rewrite_rules' );
    function custom_sitemap_rewrite_rules() {
        add_rewrite_rule( '^(zh-tw|zh-cn|zh-hk|zh-sg|zh-hans|zh-hant)/sitemap\.xml$', 'index.php?lang=$matches[1]', 'top' );
    }

    // 添加自定义查询变量
    add_filter( 'query_vars', 'custom_sitemap_query_vars' );
    function custom_sitemap_query_vars( $vars ) {
        $vars[] = 'lang';
        return $vars;
    }

    // 拦截请求并生成自定义网站地图
    add_action( 'template_redirect', 'custom_sitemap_template_redirect', 9999 );
    function custom_sitemap_template_redirect() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $lang = match ( $uri ) {
            '/zh-tw/wp-sitemap.xml' => 'zh-tw',
            '/zh-hk/wp-sitemap.xml' => 'zh-hk',
            '/zh-sg/wp-sitemap.xml' => 'zh-sg',
            '/zh-hans/wp-sitemap.xml' => 'zh-hans',
            '/zh-hant/wp-sitemap.xml' => 'zh-hant',
            '/zh-cn/wp-sitemap.xml' => 'zh-cn',
            default => '',
        };
        if ( in_array( $lang, [ 'zh-tw', 'zh-hk', 'zh-sg', 'zh-hans', 'zh-hant', 'zh-cn' ] ) ) {
            header( 'Content-Type: application/xml; charset=utf-8' );
            header( 'HTTP/1.1 200 OK' );
            echo generate_sitemap_index( $lang );
            exit;
        }
    }

    // 生成网站地图索引
    function generate_sitemap_index( string $lang ) {
        global $wpdb, $wpcc_options;

        // 每个分页网站地图包含的最大URL数量
        $max_urls_per_sitemap = 1000;

        // 获取所有文章的数量
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

        // 计算需要多少个分页网站地图
        $total_sitemaps = ceil( $total_posts / $max_urls_per_sitemap );

        // 正确的XML头和命名空间
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
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

    // 拦截分页网站地图请求
    add_action( 'template_redirect', 'custom_paged_sitemap_template_redirect', 9998 );
    function custom_paged_sitemap_template_redirect() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

        if ( preg_match( '/\/sitemap-(zh-tw|zh-hk|zh-sg|zh-hans|zh-hant|zh-cn)-(\d+)\.xml$/', $uri, $matches ) ) {
            $lang = $matches[1];
            $page = (int) $matches[2];

            header( 'Content-Type: application/xml; charset=utf-8' );
            header( 'HTTP/1.1 200 OK' );

            echo generate_paged_sitemap_content( $lang, $page );
            exit;
        }
    }

    // 生成分页网站地图内容
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

    // 生成网站地图样式
    function generate_sitemap_styles() {
        // 样式定义可以根据需要进行自定义
        return <<<XSL
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>XML Sitemap</title>
    </head>
    <body>
        <h2>XML Sitemap</h2>
        <xsl:for-each select="urlset/url">
            <div>
                <a href="{loc}"><xsl:value-of select="loc"/></a>
                <span>Last modified: <xsl:value-of select="lastmod"/></span>
            </div>
        </xsl:for-each>
    </body>
    </html>
</xsl:template>
</xsl:stylesheet>
XSL;
    }
}
?>
