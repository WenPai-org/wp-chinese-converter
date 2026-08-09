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

function add_action(...$args) {}
function add_filter(...$args) {}
function do_action(...$args) {}
function apply_filters($hook, $value, ...$args) { return $value; }
function current_user_can($capability) {
    if ($capability === 'manage_options') {
        return $GLOBALS['wpcc_test_can_manage'];
    }
    return $capability === 'edit_post' && $GLOBALS['wpcc_test_can_edit'];
}
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function wp_cache_get($key, $group = '') { return $GLOBALS['wpcc_test_cache'][$group][$key] ?? false; }
function wp_cache_set($key, $value, $group = '', $expiry = 0) { $GLOBALS['wpcc_test_cache'][$group][$key] = $value; return true; }
function wp_cache_delete($key, $group = '') { unset($GLOBALS['wpcc_test_cache'][$group][$key]); return true; }
function wp_using_ext_object_cache() { return false; }
function get_transient($key) { return $GLOBALS['wpcc_test_cache']['transient'][$key] ?? false; }
function set_transient($key, $value, $expiry = 0) { $GLOBALS['wpcc_test_cache']['transient'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['wpcc_test_cache']['transient'][$key]); return true; }
function get_option($key, $default = false) { return $GLOBALS['wpcc_test_options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['wpcc_test_options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['wpcc_test_options'][$key]); return true; }
function absint($value) { return abs((int) $value); }
function wp_is_post_autosave($postId) { return false; }
function wp_is_post_revision($postId) { return false; }
function __($text, $domain = 'default') { return $text; }
function add_post_meta($postId, $key, $value, $unique = false) {
    $GLOBALS['wpcc_test_post_meta'][$postId][$key][] = $value;
    return count($GLOBALS['wpcc_test_post_meta'][$postId][$key]);
}
function get_post_meta($postId, $key, $single = false) {
    $values = $GLOBALS['wpcc_test_post_meta'][$postId][$key] ?? [];
    return $single ? (end($values) ?: '') : $values;
}
function wp_update_post($data, $wpError = false) {
    $GLOBALS['wpcc_test_posts'][(int) $data['ID']] = $data;
    return (int) $data['ID'];
}
function get_post($postId) { return $GLOBALS['wpcc_test_posts'][$postId] ?? null; }
function get_current_user_id() { return 7; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function zhconversion($text, $target) {
    return strtr((string) $text, ['网络' => '網路', '软件' => '軟體']);
}

class WP_Error {
    public $code;
    public function __construct($code, $message = '') { $this->code = $code; }
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
