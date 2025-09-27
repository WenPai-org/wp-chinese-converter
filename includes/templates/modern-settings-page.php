<?php
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'basic';
?>

<div class="wrap wpcc-settings">
    <h1>
        <?php esc_html_e('WP Chinese Converter 设置', 'wp-chinese-converter'); ?>
        <span style="font-size: 13px; padding-left: 10px;">
            <?php printf(esc_html__('版本: %s', 'wp-chinese-converter'), esc_html(wpcc_VERSION)); ?>
        </span>
        <a href="https://wenpai.org/" target="_blank" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e('文派开源', 'wp-chinese-converter'); ?>
        </a>
        <a href="https://wpcc.net" target="_blank" class="button button-secondary">
            <?php esc_html_e('插件主页', 'wp-chinese-converter'); ?>
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
                <button type="button" class="wpcc-tab <?php echo $active_tab === 'advanced' ? 'active' : ''; ?>" data-tab="advanced">
                    <?php _e('高级设置', 'wp-chinese-converter'); ?>
                </button>
                <button type="button" class="wpcc-tab <?php echo $active_tab === 'tools' ? 'active' : ''; ?>" data-tab="tools">
                    <?php _e('工具与维护', 'wp-chinese-converter'); ?>
                </button>
            </div>
        </div>

        <!-- 基础设置 -->
        <div class="wpcc-section" id="wpcc-section-basic" style="<?php echo $active_tab !== 'basic' ? 'display: none;' : 'display: block;'; ?>">
            <h2><?php _e('基础设置', 'wp-chinese-converter'); ?></h2>
            <p class="wpcc-section-desc"><?php _e('配置中文转换的基本选项和语言设置。', 'wp-chinese-converter'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('wpcc_settings', 'wpcc_nonce'); ?>
                <input type="hidden" name="wpcco_submitted" value="1" />

                <table class="form-table">
                    <tr>
                        <th><?php _e('自定义"不转换"标签名称', 'wp-chinese-converter'); ?></th>
                        <td>
                            <input type="text" name="wpcco_no_conversion_tip" 
                                   value="<?php echo esc_attr($this->options['nctip'] ?? ''); ?>" 
                                   class="regular-text wpcc-input"
                                   placeholder="<?php esc_attr_e('请输入显示名（默认值如左）', 'wp-chinese-converter'); ?>" />
                            <p class="description"><?php _e('注意：本插件的自带小工具中将包含当前页面原始版本链接, 您可在此自定义其显示名称。留空则使用默认的"不转换"。', 'wp-chinese-converter'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('选择可用的中文语系模块', 'wp-chinese-converter'); ?></th>
                        <td>
                            <?php if (is_array($this->langs)): ?>
                                <?php foreach ($this->langs as $key => $value): ?>
                                    <div style="margin-bottom: 10px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcco_variant_<?php echo $key; ?>"
                                                   <?php echo in_array($key, $this->options['wpcc_used_langs'] ?? []) ? 'checked="checked"' : ''; ?> />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label"><?php echo $value[2] . ' (' . $key . ')'; ?></span>
                                        </label>
                                        <input type="text" name="<?php echo $value[1]; ?>" 
                                               value="<?php echo esc_attr($this->options[$value[1]] ?? ''); ?>" 
                                               placeholder="<?php echo esc_attr($value[2]); ?>"
                                               class="regular-text wpcc-input" style="margin-left: 20px;" />
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <p class="description"><?php _e('注意：此项为全局设置，请至少勾选一种中文语言，否则插件无法正常运行。支持自定义名称，留空为默认值。', 'wp-chinese-converter'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('简繁切换按钮的展示形式', 'wp-chinese-converter'); ?></th>
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
                            <p class="description"><?php _e('注意：插件内置了两种模式，您可以修改语言切换按钮的展现方式来满足个性化需求，或使用底部转换 URL 链接自行调用。', 'wp-chinese-converter'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('中文搜索关键词简繁转换', 'wp-chinese-converter'); ?></th>
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
                            <p class="description"><?php _e('提示：此功能将增加搜索时数据库负担，低配服务器建议关闭。', 'wp-chinese-converter'); ?></p>
                        </td>
                    </tr>
                </table>

                <div class="wpcc-submit-wrapper">
                    <?php wp_nonce_field('wpcc_basic_nonce', 'wpcc_basic_nonce'); ?>
                    <button type="submit" class="button button-primary"><?php esc_attr_e('保存设置', 'wp-chinese-converter'); ?></button>
                </div>
            </form>
        </div>

        <!-- 高级设置 -->
        <div class="wpcc-section" id="wpcc-section-advanced" style="<?php echo $active_tab !== 'advanced' ? 'display: none;' : ''; ?>">
            <h2><?php _e('高级设置', 'wp-chinese-converter'); ?></h2>
            <p class="wpcc-section-desc"><?php _e('配置浏览器检测、Cookie设置和内容过滤选项。', 'wp-chinese-converter'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('wpcc_settings', 'wpcc_nonce'); ?>
                <input type="hidden" name="wpcco_submitted" value="1" />

                <table class="form-table">
                    <tr>
                        <th><?php _e('识别浏览器中文语言动作', 'wp-chinese-converter'); ?></th>
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
                                <strong><?php _e('注意：此项设置不会应用于搜索引擎。', 'wp-chinese-converter'); ?></strong><br>
                                <?php _e('1、设置为非"关闭"，将自动识别访客浏览器首选中文语言。', 'wp-chinese-converter'); ?><br>
                                <?php _e('2、设置"跳转至…"，将302重定向到访客浏览器首选语言版本。', 'wp-chinese-converter'); ?><br>
                                <?php _e('3、设置"显示为…"，将直接显示中文转换版本而不重定向。', 'wp-chinese-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Cookie识别用户语言偏好', 'wp-chinese-converter'); ?></th>
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
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('排除某些HTML标签内中文', 'wp-chinese-converter'); ?></th>
                        <td>
                            <textarea name="wpcco_no_conversion_tag" class="large-text wpcc-textarea" rows="3"
                                      placeholder="<?php _e('默认为空', 'wp-chinese-converter'); ?>"><?php echo esc_textarea($this->options['wpcc_no_conversion_tag'] ?? ''); ?></textarea>
                            <p class="description">
                                <?php _e('注意：这里输入的HTML标签里内容将不进行中文繁简转换（仅适用文章内容）， 保持原样输出。请输入HTML标签名：', 'wp-chinese-converter'); ?>
                                <br><?php _e('如', 'wp-chinese-converter'); ?> <code>pre</code>;
                                <?php _e('多个HTML标签之间以', 'wp-chinese-converter'); ?> <code>,</code> <?php _e('分割，如', 'wp-chinese-converter'); ?> <code>pre,code</code>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('排除日语标签', 'wp-chinese-converter'); ?></th>
                        <td>
                            <label class="wpcc-switch">
                                <input type="checkbox" name="wpcco_no_conversion_ja" 
                                       <?php echo !empty($this->options['wpcc_no_conversion_ja']) ? 'checked="checked"' : ''; ?> />
                                <span class="wpcc-slider"></span>
                                <span class="wpcc-switch-label"><?php _e('排除日语(lang="ja")标签', 'wp-chinese-converter'); ?></span>
                            </label>
                            <p class="description">
                                <?php _e('注意：如果选中此选项, 文章内容中用 lang="ja" 标记为日语的 HTML 标签将不进行繁简转换, 保持原样输出。', 'wp-chinese-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('经典编辑器支持', 'wp-chinese-converter'); ?></th>
                        <td>
                            <label class="wpcc-switch">
                                <input type="checkbox" name="wpcco_no_conversion_qtag" 
                                       <?php checked(!empty($this->options['wpcc_no_conversion_qtag'])); ?> />
                                <span class="wpcc-slider"></span>
                                <span class="wpcc-switch-label"><?php _e('为经典编辑器添加此"不转换中文"的按钮标签', 'wp-chinese-converter'); ?></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('启用对页面内容的整体转换', 'wp-chinese-converter'); ?></th>
                        <td>
                            <label class="wpcc-switch">
                                <input type="checkbox" name="wpcco_use_fullpage_conversion" 
                                       <?php checked(($this->options['wpcc_use_fullpage_conversion'] ?? 1), 1); ?> />
                                <span class="wpcc-slider"></span>
                                <span class="wpcc-switch-label"><?php _e('启用全页面转换', 'wp-chinese-converter'); ?></span>
                            </label>
                            <p class="description">
                                <?php _e('注意：如果遇到异常（包括中文转换错误，HTML页面错误或PHP错误等），请关闭此选项。', 'wp-chinese-converter'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div class="wpcc-submit-wrapper">
                    <?php wp_nonce_field('wpcc_advanced_nonce', 'wpcc_advanced_nonce'); ?>
                    <button type="submit" class="button button-primary"><?php esc_attr_e('保存设置', 'wp-chinese-converter'); ?></button>
                </div>
            </form>
        </div>

        <!-- 工具与维护 -->
        <div class="wpcc-section" id="wpcc-section-tools" style="<?php echo $active_tab !== 'tools' ? 'display: none;' : ''; ?>">
            <h2><?php _e('工具与维护', 'wp-chinese-converter'); ?></h2>
            <p class="wpcc-section-desc"><?php _e('插件管理和维护工具。', 'wp-chinese-converter'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('wpcc_settings', 'wpcc_nonce'); ?>
                <input type="hidden" name="wpcco_submitted" value="1" />
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('繁简转换页面永久链接格式', 'wp-chinese-converter'); ?></th>
                        <td>
                            <?php global $wp_rewrite; ?>
                            <label class="wpcc-radio">
                                <input type="radio" name="wpcco_use_permalink" value="0" 
                                       <?php echo ($this->options['wpcc_use_permalink'] ?? 0) == 0 ? 'checked="checked"' : ''; ?> />
                                <span class="wpcc-radio-label">https://域名/文章链接/?variant=zh-tw</span>
                            </label><br>
                            <label class="wpcc-radio">
                                <input type="radio" name="wpcco_use_permalink" value="1" 
                                       <?php echo empty($wp_rewrite->permalink_structure) ? 'disabled="disabled"' : ''; ?>
                                       <?php echo ($this->options['wpcc_use_permalink'] ?? 0) == 1 ? 'checked="checked"' : ''; ?> />
                                <span class="wpcc-radio-label">https://域名/文章链接/zh-tw/</span>
                            </label><br>
                            <label class="wpcc-radio">
                                <input type="radio" name="wpcco_use_permalink" value="2" 
                                       <?php echo empty($wp_rewrite->permalink_structure) ? 'disabled="disabled"' : ''; ?>
                                       <?php echo ($this->options['wpcc_use_permalink'] ?? 0) == 2 ? 'checked="checked"' : ''; ?> />
                                <span class="wpcc-radio-label">https://域名/zh-tw/文章链接/</span>
                            </label>
                            <p class="description"><?php _e('提示：若未开启固定链接，则只能选第一种默认URL形式。', 'wp-chinese-converter'); ?></p>
                        </td>
                    </tr>

                    <?php global $wpcc_modules; if (wpcc_mobile_exist('sitemap')): ?>
                    <tr>
                        <th><?php _e('是否启用多语言网站地图', 'wp-chinese-converter'); ?></th>
                        <td>
                            <label class="wpcc-switch">
                                <input type="checkbox" name="wpcco_use_sitemap" 
                                       <?php checked(isset($this->options['wpcso_use_sitemap']) ? $this->options['wpcso_use_sitemap'] : 0, 1); ?> />
                                <span class="wpcc-slider"></span>
                                <span class="wpcc-switch-label"><?php _e('启用多语言网站地图', 'wp-chinese-converter'); ?></span>
                            </label>
                            <p class="description">
                                <?php _e('网站地图的访问地址为：https://域名/zh-tw/wp-sitemap.xml，其中zh-tw可替换成你想访问的语言', 'wp-chinese-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('网站地图支持的post_type', 'wp-chinese-converter'); ?></th>
                        <td>
                            <input type="text" name="wpcco_sitemap_post_type" 
                                   value="<?php echo esc_attr($this->options['wpcso_sitemap_post_type'] ?? 'post,page'); ?>" 
                                   class="regular-text wpcc-input"
                                   placeholder="<?php _e('默认为:post,page', 'wp-chinese-converter'); ?>" />
                            <p class="description">
                                <?php _e('默认为 post 和 page 生成地图，如果你需要添加自定义 post_type 请自行添加后用逗号分隔。', 'wp-chinese-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <div class="wpcc-submit-wrapper">
                    <?php wp_nonce_field('wpcc_tools_nonce', 'wpcc_tools_nonce'); ?>
                    <button type="submit" class="button button-primary"><?php esc_attr_e('保存设置', 'wp-chinese-converter'); ?></button>
                </div>
            </form>

            <div class="wpcc-stats-card">
                <h3><?php _e('WP Super Cache兼容状态', 'wp-chinese-converter'); ?></h3>
                <p class="description"><?php _e('注意：默认情况下， 本插件的"识别浏览器中文语言动作"和"Cookie识别用户语言偏好"这两个功能与缓存插件不兼容。', 'wp-chinese-converter'); ?></p>
                
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
                    <div class="wpcc-action-buttons">
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
            </div>

            <div class="wpcc-stats-card">
                <h3><?php _e('确定卸载本插件?', 'wp-chinese-converter'); ?></h3>
                <p class="description" style="color: #d63638;">
                    <?php _e('注意：这将清除数据库<code>wp_options</code>表中本插件的设置项（键值为 <code>wpcc_options</code>），提交后请到插件管理中禁用本插件。', 'wp-chinese-converter'); ?>
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
            </div>
        </div>
    </div>
</div>