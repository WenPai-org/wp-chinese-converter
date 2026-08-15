<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', sys_get_temp_dir());
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['wp_version'] = '6.8';
$GLOBALS['wpcc_test_cache'] = [];
$GLOBALS['wpcc_test_options'] = [];
$GLOBALS['wpcc_test_can_manage'] = false;
$GLOBALS['wpcc_test_can_edit'] = false;
$GLOBALS['wpcc_test_post_meta'] = [];
$GLOBALS['wpcc_test_posts'] = [];
$GLOBALS['wpcc_test_history_limit'] = null;
$GLOBALS['wpcc_test_update_throw'] = false;
$GLOBALS['wpcc_test_filters'] = [];

function add_action(...$args) {}
function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1) {
    $GLOBALS['wpcc_test_filters'][$hook][$priority][] = [$callback, $acceptedArgs];
    return true;
}
function remove_filter($hook, $callback, $priority = 10) {
    foreach ($GLOBALS['wpcc_test_filters'][$hook][$priority] ?? [] as $index => $registered) {
        if ($registered[0] === $callback) {
            unset($GLOBALS['wpcc_test_filters'][$hook][$priority][$index]);
        }
    }
    return true;
}
function has_filter($hook, $callback = false) {
    foreach ($GLOBALS['wpcc_test_filters'][$hook] ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $registered) {
            if ($callback === false || $registered[0] === $callback) {
                return $priority;
            }
        }
    }
    return false;
}
function do_action(...$args) {}
function apply_filters($hook, $value, ...$args) {
    if ($hook === 'wpcc_post_conversion_history_limit' && $GLOBALS['wpcc_test_history_limit'] !== null) {
        return $GLOBALS['wpcc_test_history_limit'];
    }
    $callbacks = $GLOBALS['wpcc_test_filters'][$hook] ?? [];
    ksort($callbacks);
    foreach ($callbacks as $registeredAtPriority) {
        foreach ($registeredAtPriority as [$callback, $acceptedArgs]) {
            $parameters = array_slice(array_merge([$value], $args), 0, $acceptedArgs);
            $value = $callback(...$parameters);
        }
    }
    return $value;
}
function current_user_can($capability, ...$args) {
    if ($capability === 'manage_options') {
        return $GLOBALS['wpcc_test_can_manage'];
    }
    return $capability === 'edit_post' && $GLOBALS['wpcc_test_can_edit'];
}
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function wp_slash($value) {
    if (is_array($value)) {
        return array_map('wp_slash', $value);
    }
    return is_string($value) ? addslashes($value) : $value;
}
function wp_unslash($value) {
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }
    return is_string($value) ? stripslashes($value) : $value;
}
function wp_cache_get($key, $group = '') { return $GLOBALS['wpcc_test_cache'][$group][$key] ?? false; }
function wp_cache_set($key, $value, $group = '', $expiry = 0) { $GLOBALS['wpcc_test_cache'][$group][$key] = $value; return true; }
function wp_cache_delete($key, $group = '') { unset($GLOBALS['wpcc_test_cache'][$group][$key]); return true; }
function wp_cache_supports($feature) { return false; }
function wp_using_ext_object_cache() { return false; }
function get_transient($key) { return $GLOBALS['wpcc_test_cache']['transient'][$key] ?? false; }
function set_transient($key, $value, $expiry = 0) { $GLOBALS['wpcc_test_cache']['transient'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['wpcc_test_cache']['transient'][$key]); return true; }
function get_option($key, $default = false) { return $GLOBALS['wpcc_test_options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['wpcc_test_options'][$key] = $value; return true; }
function is_admin() { return true; }
function delete_option($key) { unset($GLOBALS['wpcc_test_options'][$key]); return true; }
function absint($value) { return abs((int) $value); }
function wp_is_post_autosave($postId) { return false; }
function wp_is_post_revision($postId) { return false; }
function __($text, $domain = 'default') { return $text; }
function add_post_meta($postId, $key, $value, $unique = false) {
    $GLOBALS['wpcc_test_post_meta'][$postId][$key][] = wp_unslash($value);
    return count($GLOBALS['wpcc_test_post_meta'][$postId][$key]);
}
function get_post_meta($postId, $key, $single = false) {
    $values = $GLOBALS['wpcc_test_post_meta'][$postId][$key] ?? [];
    return $single ? (end($values) ?: '') : $values;
}
function update_post_meta($postId, $key, $value) {
    $GLOBALS['wpcc_test_post_meta'][$postId][$key] = [wp_unslash($value)];
    return true;
}
function wp_update_post($data, $wpError = false) {
    if ($GLOBALS['wpcc_test_update_throw']) {
        $GLOBALS['wpcc_test_update_throw'] = false;
        throw new RuntimeException('simulated update failure');
    }
    $post = new WP_Post();
    $post->ID = (int) $data['ID'];
    $post->post_title = (string) wp_unslash(apply_filters('title_save_pre', $data['post_title']));
    $post->post_content = (string) wp_unslash(apply_filters('content_save_pre', $data['post_content']));
    $GLOBALS['wpcc_test_posts'][$post->ID] = $post;
    return $post->ID;
}
function get_post($postId) { return $GLOBALS['wpcc_test_posts'][$postId] ?? null; }
function get_current_user_id() { return 7; }
function current_time($type, $gmt = false) { return $type === 'mysql' ? '2026-08-11 05:00:00' : time(); }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_filter_kses($value) { return wp_slash(strip_tags((string) wp_unslash($value))); }
function wp_filter_post_kses($value) {
    $value = (string) wp_unslash($value);
    $value = preg_replace('/\sdata-json=(?:\'[^\']*\'|"[^"]*")/', '', $value);
    return wp_slash($value);
}
function kses_remove_filters() {
    remove_filter('title_save_pre', 'wp_filter_kses');
    remove_filter('content_save_pre', 'wp_filter_post_kses');
}
function kses_init_filters() {
    add_filter('title_save_pre', 'wp_filter_kses');
    add_filter('content_save_pre', 'wp_filter_post_kses');
}
function zhconversion($text, $target) {
    return strtr((string) $text, ['网络' => '網路', '软件' => '軟體']);
}

class WP_Error {
    public $code;
    public $message;
    public function __construct($code, $message = '') { $this->code = $code; $this->message = $message; }
}
class WP_Post {
	public $ID = 0;
    public $post_title = '';
    public $post_content = '';
}

class WP_REST_Server {
    public const CREATABLE = 'POST';
    public const READABLE = 'GET';
}
function rest_ensure_response($value) { return $value; }

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/includes/core/class-wpcc-conversion-cache.php';
require dirname(__DIR__) . '/includes/core/class-wpcc-converter-factory.php';
require dirname(__DIR__) . '/includes/modules/wpcc-modern-cache.php';
require dirname(__DIR__) . '/includes/modules/wpcc-rest-api.php';
require dirname(__DIR__) . '/includes/core/class-wpcc-post-conversion-backup.php';
