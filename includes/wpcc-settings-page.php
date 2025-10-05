<?php
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'basic';
?>

<div class="wrap wpcc-settings">
    <h1>
        <?php esc_html_e('文派译词设置', 'wp-chinese-converter'); ?>
        <span style="font-size: 13px; padding-left: 10px;">
            <?php printf(esc_html__('版本: %s', 'wp-chinese-converter'), esc_html(wpcc_VERSION)); ?>
        </span>
        <a href="https://wpcc.net/document" target="_blank" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e('文档', 'wp-chinese-converter'); ?>
        </a>
        <a href="https://wpcc.net/support" target="_blank" class="button button-secondary">
            <?php esc_html_e('支持', 'wp-chinese-converter'); ?>
        </a>
    </h1>

    <?php if ($this->is_submitted && $this->is_success): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($this->message); ?></p>
        </div>
    <?php elseif ($this->is_submitted && $this->is_error): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($this->message); ?></p>
        </div>
    <?php endif; ?>

    <div class="wpcc-card">
        <div class="wpcc-tabs-wrapper">
            <div class="wpcc-sync-tabs">
                <button type="button" class="wpcc-tab <?php echo $active_tab === 'basic' ? 'active' : ''; ?>" data-tab="basic">
                    <?php _e('基础设置', 'wp-chinese-converter'); ?>
                </button>
                <button type="button" class="wpcc-tab <?php echo $active_tab === 'tools' ? 'active' : ''; ?>" data-tab="tools">
                    <?php _e('工具维护', 'wp-chinese-converter'); ?>
                </button>
            </div>
        </div>

        <!-- 基础设置 -->
        <div class="wpcc-section" id="wpcc-section-basic" style="<?php echo $active_tab !== 'basic' ? 'display: none;' : 'display: block;'; ?>">
            <h2><?php _e('基础设置', 'wp-chinese-converter'); ?></h2>
            <p class="wpcc-section-desc"><?php _e('配置中文转换的基本选项和语言设置。', 'wp-chinese-converter'); ?></p>

            <form method="post" action="">
                <input type="hidden" name="wpcco_submitted" value="1" />

                <h3><?php _e('语言和界面设置', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('语言模块', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/language-modules" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <div class="wpcc-language-section">
                                    <div style="margin-bottom: 10px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_zh-cn"
                                                   <?php echo in_array('zh-cn', $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php _e('中国大陆 (zh-cn)', 'wp-chinese-converter'); ?></span>
                                        </label>
                                        <input type="text" name="cntip" 
                                               value="<?php echo esc_attr($this->options['cntip'] ?? '简体'); ?>" 
                                               placeholder="<?php _e('中国大陆', 'wp-chinese-converter'); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_zh-tw"
                                                   <?php echo in_array('zh-tw', $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php _e('台灣正體 (zh-tw)', 'wp-chinese-converter'); ?></span>
                                        </label>
                                        <input type="text" name="twtip" 
                                               value="<?php echo esc_attr($this->options['twtip'] ?? '繁体'); ?>" 
                                               placeholder="<?php _e('台灣正體', 'wp-chinese-converter'); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_zh-hk"
                                                   <?php echo in_array('zh-hk', $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php _e('港澳繁體 (zh-hk)', 'wp-chinese-converter'); ?></span>
                                        </label>
                                        <input type="text" name="hktip" 
                                               value="<?php echo esc_attr($this->options['hktip'] ?? '港澳'); ?>" 
                                               placeholder="<?php _e('港澳繁體', 'wp-chinese-converter'); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                </div>
                                
                                <div class="wpcc-extended-languages" style="margin-top: 15px; display: none;">
                                    <h4><?php _e('扩展语言模块（国际）', 'wp-chinese-converter'); ?></h4>
                                    <div style="margin-bottom: 10px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_zh-hans"
                                                   <?php echo in_array('zh-hans', $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php _e('简体中文 (zh-hans)', 'wp-chinese-converter'); ?></span>
                                        </label>
                                        <input type="text" name="hanstip" 
                                               value="<?php echo esc_attr($this->options['hanstip'] ?? '简体'); ?>" 
                                               placeholder="<?php _e('简体中文', 'wp-chinese-converter'); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_zh-hant"
                                                   <?php echo in_array('zh-hant', $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php _e('繁体中文 (zh-hant)', 'wp-chinese-converter'); ?></span>
                                        </label>
                                        <input type="text" name="hanttip" 
                                               value="<?php echo esc_attr($this->options['hanttip'] ?? '繁体'); ?>" 
                                               placeholder="<?php _e('繁体中文', 'wp-chinese-converter'); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_zh-sg"
                                                   <?php echo in_array('zh-sg', $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php _e('马新简体 (zh-sg)', 'wp-chinese-converter'); ?></span>
                                        </label>
                                        <input type="text" name="sgtip" 
                                               value="<?php echo esc_attr($this->options['sgtip'] ?? '马新'); ?>" 
                                               placeholder="<?php _e('马新简体', 'wp-chinese-converter'); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                    <div style="margin-bottom: 10px;" id="zh-jp-option">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_zh-jp"
                                                   <?php echo in_array('zh-jp', $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php _e('日式汉字 (zh-jp)', 'wp-chinese-converter'); ?></span>
                                            <span class="wpcc-engine-note" style="color: #666; font-size: 12px;"><?php _e('(仅 OpenCC 引擎)', 'wp-chinese-converter'); ?></span>
                                        </label>
                                        <input type="text" name="jptip" 
                                               value="<?php echo esc_attr($this->options['jptip'] ?? '日式'); ?>" 
                                               placeholder="<?php _e('日式汉字', 'wp-chinese-converter'); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                </div>
                                <p class="description"><?php _e('请至少勾选一种中文语言，否则插件无法正常运行。支持自定义名称，留空为默认值。', 'wp-chinese-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('"不转换"标签', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/no-conversion-label" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <input type="text" name="wpcco_no_conversion_tip" 
                                       value="<?php echo esc_attr($this->options['nctip'] ?? ''); ?>" 
                                       class="regular-text wpcc-input"
                                       placeholder="<?php esc_attr_e('请输入显示名（默认值如左）', 'wp-chinese-converter'); ?>" />
                                <p class="description"><?php _e('自定义小工具中"不转换"链接的显示名称。', 'wp-chinese-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('展示形式', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/display-format" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-radio">
                                    <input type="radio" name="wpcc_translate_type" value="0" 
                                           <?php echo ($this->options['wpcc_translate_type'] ?? 0) == 0 ? 'checked="checked"' : ''; ?> />
                                    <span class="wpcc-radio-label"><?php _e('平铺', 'wp-chinese-converter'); ?></span>
                                </label><br>
                                <label class="wpcc-radio">
                                    <input type="radio" name="wpcc_translate_type" value="1" 
                                           <?php echo ($this->options['wpcc_translate_type'] ?? 0) == 1 ? 'checked="checked"' : ''; ?> />
                                    <span class="wpcc-radio-label"><?php _e('下拉列表', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description"><?php _e('选择语言切换按钮的展现方式。', 'wp-chinese-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('显示更多语言', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/more-language-links" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_show_more_langs" id="wpcc_show_more_langs"
                                           <?php checked(isset($this->options['wpcc_show_more_langs']) ? $this->options['wpcc_show_more_langs'] : 0, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用扩展语言模块', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('启用后将显示更多中文语言变体选项，包括 zh-hans、zh-hant、zh-sg、zh-jp 等。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                </table>

                <h3><?php _e('转换引擎和核心功能', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('转换引擎', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/conversion-engine" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <select name="wpcc_engine" class="wpcc-select">
                                    <?php 
                                    $available_engines = WPCC_Converter_Factory::get_available_engines();
                                    $current_engine = $this->options['wpcc_engine'] ?? 'mediawiki';
                                    foreach ($available_engines as $engine_key => $engine_info): 
                                    ?>
                                    <option value="<?php echo esc_attr($engine_key); ?>" <?php echo $current_engine === $engine_key ? 'selected="selected"' : ''; ?>>
                                        <?php echo esc_html($engine_info['name'] . ' - ' . $engine_info['description']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="wpcc-engine-info" style="margin-top: 10px;">
                                    <?php foreach ($available_engines as $engine_key => $engine_info): ?>
                                    <div class="engine-details" data-engine="<?php echo esc_attr($engine_key); ?>" style="<?php echo $current_engine !== $engine_key ? 'display: none;' : ''; ?>">
                                        <p class="description"><?php echo esc_html(implode(', ', $engine_info['features'])); ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('搜索转换', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/search-conversion" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <select name="wpcco_search_conversion" class="wpcc-select">
                                    <option value="2" <?php echo ($this->options['wpcc_search_conversion'] ?? 1) == 2 ? 'selected="selected"' : ''; ?>>
                                        <?php echo __('开启', 'wp-chinese-converter'); ?>
                                    </option>
                                    <option value="0" <?php echo (($this->options['wpcc_search_conversion'] ?? 1) != 2 && ($this->options['wpcc_search_conversion'] ?? 1) != 1) ? 'selected="selected"' : ''; ?>>
                                        <?php echo __('关闭', 'wp-chinese-converter'); ?>
                                    </option>
                                    <option value="1" <?php echo ($this->options['wpcc_search_conversion'] ?? 1) == 1 ? 'selected="selected"' : ''; ?>>
                                        <?php echo __('仅语言非"不转换"时开启（默认）', 'wp-chinese-converter'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php _e('此功能将增加搜索时数据库负担，低配服务器建议关闭。', 'wp-chinese-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('全页面转换', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/fullpage-conversion" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcco_use_fullpage_conversion" 
                                           <?php checked(($this->options['wpcc_use_fullpage_conversion'] ?? 1), 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用全页面转换', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('如果遇到异常（包括中文转换错误，HTML页面错误或PHP错误等），请关闭此选项。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                </table>

                <h3><?php _e('编辑器增强', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('快速标签', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/quicktags-feature" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_no_conversion_qtag" 
                                           <?php checked(isset($this->options['wpcc_no_conversion_qtag']) ? $this->options['wpcc_no_conversion_qtag'] : 0, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用编辑器快速标签', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('在经典编辑器工具栏中添加"wpcc_NC"按钮，方便快速插入不转换标签。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('发表时转换', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/post-conversion-feature" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_enable_post_conversion" 
                                           <?php checked(isset($this->options['wpcc_enable_post_conversion']) ? $this->options['wpcc_enable_post_conversion'] : 0, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用发表时自动转换', 'wp-chinese-converter'); ?></span>
                                </label>
                                <div style="margin-top: 10px; <?php echo (isset($this->options['wpcc_enable_post_conversion']) && $this->options['wpcc_enable_post_conversion']) ? '' : 'display: none;'; ?>" id="post-conversion-options">
                                    <label for="wpcc_post_conversion_target"><?php _e('转换目标语言:', 'wp-chinese-converter'); ?></label>
                                    <select name="wpcc_post_conversion_target" id="wpcc_post_conversion_target" class="wpcc-select" style="margin-left: 10px;">
                                        <?php
                                        $enabled_langs = $this->options['wpcc_used_langs'] ?? array();
                                        $current_target = $this->options['wpcc_post_conversion_target'] ?? 'zh-cn';
                                        
                                        $lang_options = array(
                                            'zh-cn' => array('name' => $this->options['cntip'] ?? '中国大陆', 'code' => 'zh-cn'),
                                            'zh-tw' => array('name' => $this->options['twtip'] ?? '台湾正体', 'code' => 'zh-tw'),
                                            'zh-hk' => array('name' => $this->options['hktip'] ?? '港澳繁体', 'code' => 'zh-hk'),
                                            'zh-hans' => array('name' => $this->options['hanstip'] ?? '简体中文', 'code' => 'zh-hans'),
                                            'zh-hant' => array('name' => $this->options['hanttip'] ?? '繁体中文', 'code' => 'zh-hant'),
                                            'zh-sg' => array('name' => $this->options['sgtip'] ?? '马新简体', 'code' => 'zh-sg'),
                                            'zh-jp' => array('name' => $this->options['jptip'] ?? '日式汉字', 'code' => 'zh-jp')
                                        );
                                        
                                        foreach ($enabled_langs as $lang_code) {
                                            if (isset($lang_options[$lang_code])) {
                                                $lang = $lang_options[$lang_code];
                                                $selected = selected($current_target, $lang_code, false);
                                                echo "<option value=\"{$lang_code}\" {$selected}>{$lang['name']} ({$lang_code})</option>";
                                            }
                                        }
                                        
                                        if (empty($enabled_langs)) {
                                            echo '<option value="">' . __('请先启用语言模块', 'wp-chinese-converter') . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <p class="description">
                                    <?php _e('启用后，发表文章时会自动将标题和内容转换为指定的中文语言版本，并在编辑页面添加转换控制面板。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                </table>

                <h3><?php _e('URL 和站点地图设置', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('链接格式', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/permalink-format" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <?php 
                                global $wp_rewrite; 
                                $site_domain = parse_url(home_url(), PHP_URL_HOST);
                                ?>
                                <label class="wpcc-radio">
                                    <input type="radio" name="wpcco_use_permalink" value="0" 
                                           <?php echo ($this->options['wpcc_use_permalink'] ?? 0) == 0 ? 'checked="checked"' : ''; ?> />
                                    <code><?php echo home_url() . ( empty( $wp_rewrite->permalink_structure ) ? '/?p=123&variant=zh-tw' : $wp_rewrite->permalink_structure . '?variant=zh-tw' ); ?></code>
                                    (默认)
                                </label><br>
                                <label class="wpcc-radio">
                                    <input type="radio" name="wpcco_use_permalink" value="1" 
                                           <?php echo empty($wp_rewrite->permalink_structure) ? 'disabled="disabled"' : ''; ?>
                                           <?php echo ($this->options['wpcc_use_permalink'] ?? 0) == 1 ? 'checked="checked"' : ''; ?> />
                                    <code><?php echo home_url() . user_trailingslashit( trailingslashit( $wp_rewrite->permalink_structure ) . 'zh-tw' ) . ( empty( $wp_rewrite->permalink_structure ) ? '/' : '' ); ?></code>
                                </label><br>
                                <label class="wpcc-radio">
                                    <input type="radio" name="wpcco_use_permalink" value="2" 
                                           <?php echo empty($wp_rewrite->permalink_structure) ? 'disabled="disabled"' : ''; ?>
                                           <?php echo ($this->options['wpcc_use_permalink'] ?? 0) == 2 ? 'checked="checked"' : ''; ?> />
                                    <code><?php echo home_url() . '/zh-tw' . $wp_rewrite->permalink_structure . ( empty( $wp_rewrite->permalink_structure ) ? '/' : '' ); ?></code>
                                </label>
                                <p class="description">
                                    <?php _e('注意：此选项影响插件生成的转换页面链接。提示：若未开启固定链接，则只能选第一种默认URL形式。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('地图内容类型', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/sitemap-post-types" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <input type="text" name="wpcco_sitemap_post_type" 
                                       value="<?php echo esc_attr($this->options['wpcco_sitemap_post_type'] ?? 'post,page'); ?>" 
                                       class="regular-text wpcc-input"
                                       placeholder="<?php _e('默认为:post,page', 'wp-chinese-converter'); ?>" />
                                <p class="description">
                                    <?php _e('默认为 post 和 page 生成地图，如需添加自定义 post_type 请用逗号分隔。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php global $wpcc_modules; if (wpcc_mobile_exist('sitemap')): ?>
                        <tr>
                            <th>
                                <?php _e('多语言地图', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/multilingual-sitemap" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcco_use_sitemap" 
                                       <?php checked(isset($this->options['wpcco_use_sitemap']) ? $this->options['wpcco_use_sitemap'] : 0, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用多语言网站地图', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php printf(__('网站地图访问地址：%s/zh-tw/sitemap.xml/', 'wp-chinese-converter'), esc_html(home_url())); ?>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                </table>

                <h3><?php _e('智能检测功能', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('浏览器语言', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/browser-language-detection" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <select name="wpcco_browser_redirect" class="wpcc-select">
                                    <option value="2" <?php echo ($this->options['wpcc_browser_redirect'] ?? 0) == 2 ? 'selected="selected"' : ''; ?>>
                                        <?php _e('显示为对应繁简版本', 'wp-chinese-converter'); ?>
                                    </option>
                                    <option value="1" <?php echo ($this->options['wpcc_browser_redirect'] ?? 0) == 1 ? 'selected="selected"' : ''; ?>>
                                        <?php _e('跳转至对应繁简页面', 'wp-chinese-converter'); ?>
                                    </option>
                                    <option value="0" <?php echo ($this->options['wpcc_browser_redirect'] ?? 0) == 0 ? 'selected="selected"' : ''; ?>>
                                        <?php _e('关闭（默认值）', 'wp-chinese-converter'); ?>
                                    </option>
                                </select>
                                <div class="browser-redirect-dependent" style="margin-top: 10px; <?php echo ($this->options['wpcc_browser_redirect'] ?? 0) == 0 ? 'display: none;' : ''; ?>">
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcco_auto_language_recong" 
                                               <?php echo ($this->options['wpcc_auto_language_recong'] ?? 0) == 1 ? 'checked="checked"' : ''; ?> />
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label"><?php _e('允许不同语系内通用', 'wp-chinese-converter'); ?></span>
                                    </label>
                                </div>
                                <p class="description">
                                    <strong><?php _e('注意：此项设置不会应用于搜索引擎。', 'wp-chinese-converter'); ?></strong>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('Cookie偏好', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/cookie-language-preference" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <select name="wpcco_use_cookie_variant" class="wpcc-select">
                                    <option value="2" <?php echo ($this->options['wpcc_use_cookie_variant'] ?? 0) == 2 ? 'selected="selected"' : ''; ?>>
                                        <?php _e('显示为对应繁简版本', 'wp-chinese-converter'); ?>
                                    </option>
                                    <option value="1" <?php echo ($this->options['wpcc_use_cookie_variant'] ?? 0) == 1 ? 'selected="selected"' : ''; ?>>
                                        <?php _e('跳转至对应繁简页面', 'wp-chinese-converter'); ?>
                                    </option>
                                    <option value="0" <?php echo ($this->options['wpcc_use_cookie_variant'] ?? 0) == 0 ? 'selected="selected"' : ''; ?>>
                                        <?php _e('关闭（默认值）', 'wp-chinese-converter'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('记住用户的语言选择偏好，下次访问时自动应用。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                </table>

                <h3><?php _e('内容过滤设置', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('标签排除', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/html-tag-exclusion" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <textarea name="wpcco_no_conversion_tag" class="large-text wpcc-textarea" rows="3"
placeholder="<?php _e('推荐默认值: pre,code,pre.wp-block-code,pre.wp-block-preformatted', 'wp-chinese-converter'); ?>"><?php echo esc_textarea($this->options['wpcc_no_conversion_tag'] ?? 'pre,code,pre.wp-block-code,pre.wp-block-preformatted'); ?></textarea>
                                <p class="description">
<?php _e('这里输入的HTML标签或选择器内内容将不进行中文繁简转换，推荐设置', 'wp-chinese-converter'); ?> <code>pre,code,pre.wp-block-code,pre.wp-block-preformatted</code> <?php _e('来排除区块编辑器的代码块与预格式化内容。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('日语内容排除', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/japanese-content-exclusion" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_no_conversion_ja" 
                                           <?php checked(isset($this->options['wpcc_no_conversion_ja']) ? $this->options['wpcc_no_conversion_ja'] : 0, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('排除日语内容转换', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('启用后，标记为日语（lang="ja"）的内容将不进行中文繁简转换，避免日语汉字被错误转换。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                </table>

                <h3><?php _e('SEO 优化功能', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('hreflang 标签', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/hreflang-tags" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_enable_hreflang_tags" 
                                           <?php checked(isset($this->options['wpcc_enable_hreflang_tags']) ? $this->options['wpcc_enable_hreflang_tags'] : 1, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用 hreflang 标签生成', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('自动为每个语言版本生成 hreflang 标签，提升多语言 SEO 效果。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('默认语言', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/default-language" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label for="wpcc_hreflang_x_default"><?php _e('x-default 语言:', 'wp-chinese-converter'); ?></label>
                                <select name="wpcc_hreflang_x_default" id="wpcc_hreflang_x_default" class="wpcc-select" style="margin-left: 10px;">
                                    <?php
                                    $all_languages = array(
                                        'zh-cn' => '简体中文 (zh-cn)',
                                        'zh-tw' => '台湾正体 (zh-tw)',
                                        'zh-hk' => '香港繁体 (zh-hk)',
                                        'zh-hans' => '简体中文 (zh-hans)',
                                        'zh-hant' => '繁体中文 (zh-hant)',
                                        'zh-sg' => '马新简体 (zh-sg)',
                                        'zh-jp' => '日式汉字 (zh-jp)'
                                    );
                                    
                                    $current_value = $this->options['wpcc_hreflang_x_default'] ?? 'zh-cn';
                                    
                                    foreach ($all_languages as $code => $name) {
                                        echo '<option value="' . esc_attr($code) . '"' . selected($current_value, $code, false) . '>' . esc_html($name) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php _e('设置 x-default hreflang 标签指向的默认语言版本。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('Schema 数据', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/schema-conversion" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_enable_schema_conversion" 
                                           <?php checked(isset($this->options['wpcc_enable_schema_conversion']) ? $this->options['wpcc_enable_schema_conversion'] : 1, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用 Schema 结构化数据转换', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('自动转换 JSON-LD 结构化数据中的文本内容，支持 Article、Product、Organization 等类型。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('元数据转换', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/meta-conversion" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_enable_meta_conversion" 
                                           <?php checked(isset($this->options['wpcc_enable_meta_conversion']) ? $this->options['wpcc_enable_meta_conversion'] : 1, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用 SEO 元数据转换', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('转换页面标题、描述、Open Graph 和 Twitter Card 等元数据。', 'wp-chinese-converter'); ?>
                                </p>
                            </td>
                        </tr>
                </table>

                <h3><?php _e('缓存和兼容性', 'wp-chinese-converter'); ?></h3>
                <table class="form-table">
                        <tr>
                            <th>
                                <?php _e('缓存兼容', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/cache-compatibility" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_enable_cache_addon" 
                                           <?php checked(isset($this->options['wpcc_enable_cache_addon']) ? $this->options['wpcc_enable_cache_addon'] : 1, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用缓存插件兼容', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('支持 WP Super Cache、WP Rocket、LiteSpeed Cache、W3 Total Cache 等流行的缓存插件。', 'wp-chinese-converter'); ?>
                                </p>
                                <?php
                                // 显示检测到的缓存插件状态
                                if (class_exists('WPCC_Cache_Addon')) {
                                    $cache_addon = new WPCC_Cache_Addon();
                                    $cache_status = $cache_addon->get_cache_status();
                                    if (!empty($cache_status)) {
                                        echo '<div style="margin-top: 10px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa;">';
                                        echo '<strong>' . __('检测到的缓存插件:', 'wp-chinese-converter') . '</strong><br>';
                                        $plugin_names = array(
                                            'wp_super_cache' => 'WP Super Cache',
                                            'wp_rocket' => 'WP Rocket',
                                            'litespeed_cache' => 'LiteSpeed Cache',
                                            'w3_total_cache' => 'W3 Total Cache',
                                            'wp_fastest_cache' => 'WP Fastest Cache',
                                            'autoptimize' => 'Autoptimize',
                                            'jetpack_boost' => 'Jetpack Boost'
                                        );
                                        foreach ($cache_status as $plugin => $status) {
                                            $name = isset($plugin_names[$plugin]) ? $plugin_names[$plugin] : $plugin;
                                            echo '<span style="color: #00a32a;">✓ ' . esc_html($name) . '</span><br>';
                                        }
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                        <?php if (is_multisite()): ?>
                        <tr>
                            <th>
                                <?php _e('多站点管理', 'wp-chinese-converter'); ?>
                                <a href="https://wpcc.net/document/multisite-management" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                            </th>
                            <td>
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcc_enable_network_module" 
                                           <?php checked(isset($this->options['wpcc_enable_network_module']) ? $this->options['wpcc_enable_network_module'] : 1, 1); ?> />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('启用多站点网络管理', 'wp-chinese-converter'); ?></span>
                                </label>
                                <p class="description">
                                    <?php _e('为多站点网络提供统一的设置管理。', 'wp-chinese-converter'); ?>
                                    <?php if (current_user_can('manage_network')): ?>
                                        <a href="<?php echo network_admin_url('settings.php?page=wpcc-network-settings'); ?>" target="_blank">
                                            <?php _e('访问网络设置', 'wp-chinese-converter'); ?>
                                        </a>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                </table>

                <div class="wpcc-submit-wrapper">
                    <button type="submit" class="button button-primary"><?php esc_attr_e('保存设置', 'wp-chinese-converter'); ?></button>
                    <?php wp_nonce_field('wpcc_reset_defaults', 'wpcc_reset_nonce'); ?>
                    <button type="submit" class="button button-secondary" name="wpcc_reset_defaults" value="1" onclick="return confirm('<?php echo esc_js(__('确定要将所有设置重置为默认吗？此操作将覆盖当前配置。', 'wp-chinese-converter')); ?>');"><?php _e('重置选项', 'wp-chinese-converter'); ?></button>
                </div>
            </form>
        </div>

        <!-- 工具维护 -->
        <div class="wpcc-section" id="wpcc-section-tools" style="<?php echo $active_tab !== 'tools' ? 'display: none;' : ''; ?>">
            <h2><?php _e('工具维护', 'wp-chinese-converter'); ?></h2>
            <p class="wpcc-section-desc"><?php _e('插件管理和维护工具。', 'wp-chinese-converter'); ?></p>

            <h3><?php _e('缓存兼容性和插件管理', 'wp-chinese-converter'); ?></h3>
            <table class="form-table">
                    <tr>
                        <th>
                            <?php _e('Super Cache状态', 'wp-chinese-converter'); ?>
                            <a href="https://wpcc.net/document/wp-super-cache-status" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                        </th>
                        <td>
                            <?php
                            $cache_status = $this->get_cache_status();
                            if ($cache_status == 0) {
                                echo '<p style="color: #d63638;">' . __("未使用WP Super Cache插件", "wp-chinese-converter") . '</p>';
                            } elseif ($cache_status == 1) {
                                echo '<p style="color: #dba617;">' . __("未安装", "wp-chinese-converter") . '</p>';
                            } elseif ($cache_status == 2) {
                                echo '<p style="color: #00a32a;">' . __("已安装", "wp-chinese-converter") . '</p>';
                            }
                            ?>
                            
                            <?php if ($cache_status > 0): ?>
                                <div class="wpcc-action-buttons" style="margin-top: 10px;">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="toggle_cache" value="1" />
                                        <?php if ($cache_status == 1): ?>
                                            <button type="submit" class="button button-secondary"><?php echo __("安装兼容", "wp-chinese-converter"); ?></button>
                                        <?php else: ?>
                                            <button type="submit" class="button button-secondary"><?php echo __("卸载兼容", "wp-chinese-converter"); ?></button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <p class="description"><?php _e('默认情况下，"识别浏览器中文语言动作"和"Cookie识别用户语言偏好"功能与缓存插件不兼容。', 'wp-chinese-converter'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <?php _e('缓存插件状态', 'wp-chinese-converter'); ?>
                            <a href="https://wpcc.net/document/cache-compatibility" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                        </th>
                        <td>
                            <?php
                            $plugin_names = array(
                                'wp_super_cache' => 'WP Super Cache',
                                'wp_rocket' => 'WP Rocket',
                                'litespeed_cache' => 'LiteSpeed Cache',
                                'w3_total_cache' => 'W3 Total Cache',
                                'wp_fastest_cache' => 'WP Fastest Cache',
                                'autoptimize' => 'Autoptimize',
                                'jetpack_boost' => 'Jetpack Boost'
                            );
                            
                            $active_cache_plugins = array();
                            
                            if (function_exists('wp_cache_is_enabled') || function_exists('wp_super_cache_init')) {
                                $active_cache_plugins['wp_super_cache'] = 'WP Super Cache';
                            }
                            if (function_exists('rocket_clean_domain')) {
                                $active_cache_plugins['wp_rocket'] = 'WP Rocket';
                            }
                            if (class_exists('LiteSpeed\Core')) {
                                $active_cache_plugins['litespeed_cache'] = 'LiteSpeed Cache';
                            }
                            if (function_exists('w3tc_flush_all')) {
                                $active_cache_plugins['w3_total_cache'] = 'W3 Total Cache';
                            }
                            if (class_exists('WpFastestCache')) {
                                $active_cache_plugins['wp_fastest_cache'] = 'WP Fastest Cache';
                            }
                            if (class_exists('autoptimizeMain') || function_exists('autoptimize_autoload')) {
                                $active_cache_plugins['autoptimize'] = 'Autoptimize';
                            }
                            if (defined('JETPACK_BOOST_VERSION') || class_exists('Automattic\Jetpack_Boost\Jetpack_Boost')) {
                                $active_cache_plugins['jetpack_boost'] = 'Jetpack Boost';
                            }
                            
                            if (!empty($active_cache_plugins)) {
                                echo '<div style="margin-bottom: 15px;">';
                                echo '<strong style="color: #00a32a;">' . __('检测到的活跃缓存插件:', 'wp-chinese-converter') . '</strong><br>';
                                foreach ($active_cache_plugins as $plugin => $name) {
                                    echo '<span style="display: inline-block; margin: 5px 10px 5px 0; padding: 3px 8px; background: #e7f3ff; border-left: 3px solid #0073aa; font-size: 12px;">✓ ' . esc_html($name) . '</span>';
                                }
                                echo '</div>';
                                
                                $cache_addon_enabled = isset($this->options['wpcc_enable_cache_addon']) ? $this->options['wpcc_enable_cache_addon'] : 1;
                                if ($cache_addon_enabled) {
                                    echo '<p style="color: #00a32a; font-weight: 500;">✓ 缓存兼容模块已启用，语言切换时将自动清除相关缓存。</p>';
                                } else {
                                    echo '<p style="color: #d63638; font-weight: 500;">⚠ 缓存兼容模块已禁用，请在基础设置中启用"缓存插件兼容"。</p>';
                                }
                            } else {
                                echo '<p style="color: #666;">' . __('未检测到活跃的缓存插件。', 'wp-chinese-converter') . '</p>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
                
                <table class="form-table">
                    <tr>
                        <th style="color: #d63638;">
                            <?php _e('插件卸载', 'wp-chinese-converter'); ?>
                            <a href="https://wpcc.net/document/plugin-uninstall" target="_blank" class="wpcc-doc-link" title="<?php _e('查看详细说明', 'wp-chinese-converter'); ?>">↗</a>
                        </th>
                        <td>
                            <p class="description" style="color: #d63638; margin-bottom: 15px;">
                                <?php _e('这将清除数据库中本插件的所有设置项，提交后请到插件管理中禁用本插件。', 'wp-chinese-converter'); ?>
                            </p>
                            
                            <form method="post" action="">
                                <label class="wpcc-switch">
                                    <input type="checkbox" name="wpcco_uninstall_nonce" />
                                    <span class="wpcc-slider"></span>
                                    <span class="wpcc-switch-label"><?php _e('确认卸载 (此操作不可逆)', 'wp-chinese-converter'); ?></span>
                                </label>
                                
                                <div style="margin-top: 15px;">
                                    <button type="submit" class="button button-secondary" 
                                           onclick="return confirm('<?php _e('确定要卸载插件吗？此操作不可逆！', 'wp-chinese-converter'); ?>')"><?php _e('卸载插件', 'wp-chinese-converter'); ?></button>
                                </div>
                            </form>
                        </td>
                    </tr>
                </table>
        </div>

    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // 控制发表时转换选项的显示/隐藏
    $('input[name="wpcc_enable_post_conversion"]').change(function() {
        if ($(this).is(':checked')) {
            $('#post-conversion-options').show();
        } else {
            $('#post-conversion-options').hide();
        }
    });
    
    // 控制浏览器重定向相关选项的显示/隐藏
    $('select[name="wpcco_browser_redirect"]').change(function() {
        if ($(this).val() == '0') {
            $('.browser-redirect-dependent').hide();
        } else {
            $('.browser-redirect-dependent').show();
        }
    });
    
    // 控制扩展语言模块的显示/隐藏
    function toggleExtendedLanguages() {
        var showMoreLangs = $('#wpcc_show_more_langs').is(':checked');
        var extendedSection = $('.wpcc-extended-languages');
        
        if (showMoreLangs) {
            extendedSection.show();
        } else {
            extendedSection.hide();
        }
    }
    
    // 检测是否使用Gutenberg编辑器
    function isGutenbergActive() {
        // 检查WordPress是否启用了Gutenberg编辑器
        // 通过PHP传递的全局变量检查
        if (typeof wpccAdminData !== 'undefined' && wpccAdminData.isGutenbergActive !== undefined) {
            return wpccAdminData.isGutenbergActive;
        }
        
        // 备用检测方法：检查是否存在Gutenberg相关的全局变量
        return (typeof wp !== 'undefined' && wp.blocks) || 
               document.body.classList.contains('block-editor-page') ||
               document.querySelector('.block-editor') !== null ||
               (typeof window.wp !== 'undefined' && window.wp.blockEditor);
    }
    
    // 控制编辑器增强选项的启用/禁用
    function toggleEditorEnhancementOptions() {
        var isGutenberg = isGutenbergActive();
        var quicktagsOption = $('input[name="wpcc_no_conversion_qtag"]').closest('tr');
        var postConversionOption = $('input[name="wpcc_enable_post_conversion"]').closest('tr');
        
        if (isGutenberg) {
            // Gutenberg环境下只禁用快速标签功能，保留发表时转换功能
            quicktagsOption.find('.wpcc-switch').addClass('wpcc-disabled');
            quicktagsOption.find('input[type="checkbox"]').prop('disabled', true);
            quicktagsOption.find('.description').html('<?php _e("在经典编辑器工具栏中添加\"wpcc_NC\"按钮，方便快速插入不转换标签。", "wp-chinese-converter"); ?><br><span class="wpcc-disabled-text"><?php _e("(区块编辑器环境下不可用)", "wp-chinese-converter"); ?></span>');
            
            // 发表时转换功能在区块编辑器下保持可用
            postConversionOption.find('.wpcc-switch').removeClass('wpcc-disabled');
            postConversionOption.find('input[type="checkbox"]').prop('disabled', false);
            postConversionOption.find('.description').html('<?php _e("启用后，在发布或更新文章时自动转换内容。", "wp-chinese-converter"); ?>');
        } else {
            // 经典编辑器环境下启用所有编辑器增强功能
            quicktagsOption.find('.wpcc-switch').removeClass('wpcc-disabled');
            quicktagsOption.find('input[type="checkbox"]').prop('disabled', false);
            quicktagsOption.find('.description').html('<?php _e("在经典编辑器工具栏中添加\"wpcc_NC\"按钮，方便快速插入不转换标签。", "wp-chinese-converter"); ?>');
            
            postConversionOption.find('.wpcc-switch').removeClass('wpcc-disabled');
            postConversionOption.find('input[type="checkbox"]').prop('disabled', false);
            postConversionOption.find('.description').html('<?php _e("启用后，在发布或更新文章时自动转换内容。", "wp-chinese-converter"); ?>');
        }
    }
    
    // 控制引擎相关选项的启用/禁用
    function toggleEngineRelatedOptions() {
        var engine = $('select[name="wpcc_engine"]').val();
        var jpOption = $('#zh-jp-option');
        var jpCheckbox = jpOption.find('input[type="checkbox"]');
        var jpInput = jpOption.find('input[type="text"]');
        
        if (engine === 'opencc') {
            // OpenCC 引擎支持日式汉字
            jpCheckbox.prop('disabled', false);
            jpInput.prop('disabled', false);
            jpOption.find('.wpcc-engine-note').text('(仅 OpenCC 引擎)').css('color', '#0073aa');
        } else {
            // MediaWiki 引擎不支持日式汉字
            jpCheckbox.prop('disabled', true).prop('checked', false);
            jpInput.prop('disabled', true);
            jpOption.find('.wpcc-engine-note').text('(不支持此引擎)').css('color', '#d63638');
        }
    }
    
    // 页面加载时检查
    toggleExtendedLanguages();
    toggleEngineRelatedOptions();
    toggleEditorEnhancementOptions();
    
    // 显示更多语言选项改变时
    $('#wpcc_show_more_langs').change(function() {
        toggleExtendedLanguages();
    });
    
    // 转换引擎改变时检查
    $('select[name="wpcc_engine"]').change(function() {
        toggleEngineRelatedOptions();
    });
});
</script>