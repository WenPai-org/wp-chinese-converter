<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPCC_Post_Conversion_Backup {
	private const LEGACY_META_KEY       = '_wpcc_conversion_backup';
	private const HISTORY_META_KEY      = '_wpcc_conversion_history_v2';
	private const HISTORY_SCHEMA        = 2;
	private const DEFAULT_HISTORY_LIMIT = 10;
	private const MAX_HISTORY_LIMIT     = 50;

	/** @var array<int,array<string,mixed>> */
	private static $pending = array();

	/** @var bool */
	private static $restoring = false;

	/**
	 * Convert saved content and retain the exact pre-conversion value for rollback.
	 *
	 * @param array<string,mixed> $data Prepared post data.
	 * @param array<string,mixed> $postarr Submitted post data.
	 * @return array<string,mixed>
	 */
	public static function filter_post_data( $data, $postarr ) {
		if ( self::$restoring ) {
			return $data;
		}

		$post_type          = isset( $data['post_type'] ) ? sanitize_key( $data['post_type'] ) : '';
		$allowed_post_types = apply_filters( 'wpcc_post_conversion_post_types', array( 'post', 'page' ) );
		if ( ! is_array( $allowed_post_types ) || ! in_array( $post_type, $allowed_post_types, true ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		if (
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			( $post_id && ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) )
		) {
			return $data;
		}

		global $wpcc_options;
		$target_lang = isset( $wpcc_options['wpcc_post_conversion_target'] )
			? sanitize_key( $wpcc_options['wpcc_post_conversion_target'] )
			: 'zh-cn';
		$engine      = isset( $wpcc_options['wpcc_engine'] ) ? sanitize_key( $wpcc_options['wpcc_engine'] ) : 'mediawiki';
		// wp_insert_post_data supplies slashed values. Keep every in-memory and
		// history value unslashed, then restore slashes only at WordPress writes.
		$original = self::fields_from_array( wp_unslash( $data ) );

		try {
			if ( '' !== $original['post_content'] ) {
				$data['post_content'] = wp_slash( wpcc_convert_post_content_safely( $original['post_content'], $target_lang ) );
			}
			if ( '' !== $original['post_title'] ) {
				$data['post_title'] = wp_slash( zhconversion( $original['post_title'], $target_lang ) );
			}
		} catch ( Throwable $e ) {
			error_log( 'WPCC Post Conversion Error: ' . $e->getMessage() );
			return $data;
		}

		$converted = self::fields_from_array( wp_unslash( $data ) );
		if ( $original !== $converted ) {
			$fingerprint     = self::fields_hash(
				array_merge(
					$original,
					array(
						'_target' => $target_lang,
						'_engine' => $engine,
					)
				)
			);
			self::$pending[] = array(
				'id'             => self::new_history_id( $fingerprint ),
				'post_id'        => $post_id,
				'target'         => $target_lang,
				'engine'         => $engine,
				'original'       => $original,
				'converted'      => $converted,
				'original_hash'  => self::fields_hash( $original ),
				'converted_hash' => self::fields_hash( $converted ),
				'fingerprint'    => $fingerprint,
				'user_id'        => get_current_user_id(),
				'created_at'     => time(),
				'created_at_gmt' => current_time( 'mysql', true ),
				'source_context' => self::source_context(),
			);
		}

		return $data;
	}

	/**
	 * Persist the matching pending backup after WordPress has assigned a post ID.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function persist_backup( $post_id, $post ) {
		if ( self::$restoring || ! $post instanceof WP_Post ) {
			return;
		}

		foreach ( self::$pending as $index => $backup ) {
			$id_matches      = 0 === $backup['post_id'] || (int) $backup['post_id'] === (int) $post_id;
			$content_matches = $backup['converted']['post_title'] === $post->post_title &&
				$backup['converted']['post_content'] === $post->post_content;
			if ( $id_matches && $content_matches ) {
				$backup['post_id'] = (int) $post_id;
				$history           = self::load_history( (int) $post_id, true );
				$latest            = end( $history );
				if ( ! is_array( $latest ) || empty( $latest['fingerprint'] ) || $latest['fingerprint'] !== $backup['fingerprint'] ) {
					$history[] = $backup;
					$history   = array_slice( $history, -self::history_limit( (int) $post_id ) );
					self::save_history( (int) $post_id, $history );
				}
				unset( self::$pending[ $index ] );
				self::$pending = array_values( self::$pending );
				break;
			}
		}
	}

	/**
	 * Return bounded conversion history for an editable post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public static function get_history( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wpcc_forbidden', __( 'You are not allowed to view conversion history for this post.', 'wp-chinese-converter' ) );
		}

		return self::load_history( $post_id, true );
	}

	/**
	 * Hash the fields currently protected by conversion rollback.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public static function hash_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		return self::fields_hash(
			array(
				'post_title'   => (string) $post->post_title,
				'post_content' => (string) $post->post_content,
			)
		);
	}

	/**
	 * Preview or restore one selected history entry.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $backup_id History entry ID. Empty selects the latest entry.
	 * @param string $expected_hash Hash observed when the restore form was rendered.
	 * @param bool   $dry_run Return the field diff without writing.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function restore( $post_id, $backup_id = '', $expected_hash = '', $dry_run = false ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wpcc_forbidden', __( 'You are not allowed to restore this post.', 'wp-chinese-converter' ) );
		}

		$history = self::load_history( $post_id, true );
		$backup  = self::find_history_entry( $history, sanitize_key( $backup_id ) );
		if ( ! is_array( $backup ) ) {
			return new WP_Error( 'wpcc_no_backup', __( 'No matching conversion backup is available.', 'wp-chinese-converter' ) );
		}

		$current = get_post( $post_id );
		if ( ! $current instanceof WP_Post ) {
			return new WP_Error( 'wpcc_post_missing', __( 'The post no longer exists.', 'wp-chinese-converter' ) );
		}

		$current_fields = array(
			'post_title'   => (string) $current->post_title,
			'post_content' => (string) $current->post_content,
		);
		$original       = self::fields_from_array( $backup['original'] ?? array() );
		$converted      = self::fields_from_array( $backup['converted'] ?? array() );
		$current_hash   = self::fields_hash( $current_fields );
		$diff           = self::field_diff( $current_fields, $original );

		if ( $current_fields === $original ) {
			return array(
				'success'          => true,
				'already_restored' => true,
				'dry_run'          => (bool) $dry_run,
				'backup_id'        => (string) $backup['id'],
				'current_hash'     => $current_hash,
				'diff'             => $diff,
			);
		}

		if ( '' !== $expected_hash ) {
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) || ! hash_equals( $expected_hash, $current_hash ) ) {
				return new WP_Error( 'wpcc_restore_stale', __( 'The post changed after the restore screen was loaded.', 'wp-chinese-converter' ) );
			}
		} elseif ( $current_fields !== $converted ) {
			// Calls using the legacy helper do not have a form-view hash. Preserve
			// their stricter protection by allowing only the matching conversion.
			return new WP_Error( 'wpcc_post_changed', __( 'The post changed after conversion; automatic restore was stopped.', 'wp-chinese-converter' ) );
		}

		$result = array(
			'success'          => true,
			'already_restored' => false,
			'dry_run'          => (bool) $dry_run,
			'backup_id'        => (string) $backup['id'],
			'current_hash'     => $current_hash,
			'diff'             => $diff,
		);
		if ( $dry_run ) {
			return $result;
		}

		self::$restoring = true;
		$restore_kses    = false;
		$kses_removed    = false;
		try {
			$restore_kses = self::post_kses_filters_active();
			if ( $restore_kses && function_exists( 'kses_remove_filters' ) ) {
				kses_remove_filters();
				$kses_removed = true;
			}
			$updated = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => wp_slash( $original['post_title'] ),
					'post_content' => wp_slash( $original['post_content'] ),
				),
				true
			);
		} finally {
			try {
				if ( $kses_removed && $restore_kses && function_exists( 'kses_init_filters' ) ) {
					kses_init_filters();
				}
			} finally {
				self::$restoring = false;
			}
		}

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		$restored = get_post( $post_id );
		if ( ! $restored instanceof WP_Post || self::hash_post( $restored ) !== self::fields_hash( $original ) ) {
			return new WP_Error( 'wpcc_restore_failed', __( 'WordPress did not persist the requested restore.', 'wp-chinese-converter' ) );
		}

		$result['current_hash'] = self::fields_hash( $original );
		return $result;
	}

	/** @return array<string,string> */
	private static function fields_from_array( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'post_title'   => isset( $value['post_title'] ) ? (string) $value['post_title'] : '',
			'post_content' => isset( $value['post_content'] ) ? (string) $value['post_content'] : '',
		);
	}

	private static function fields_hash( $fields ) {
		return hash( 'sha256', (string) wp_json_encode( $fields ) );
	}

	/**
	 * Whether WordPress' post-save KSES group is active for this request.
	 *
	 * Conversion backups contain trusted values captured by this service before
	 * WordPress saved them. Restore removes only the core save filters for the
	 * duration of wp_update_post; capability, nonce and hash checks remain intact.
	 */
	private static function post_kses_filters_active() {
		if ( ! function_exists( 'has_filter' ) ) {
			return false;
		}
		return false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ||
			false !== has_filter( 'title_save_pre', 'wp_filter_kses' );
	}

	private static function new_history_id( $fingerprint ) {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return sanitize_key( wp_generate_uuid4() );
		}
		return 'wpcc-' . substr( hash( 'sha256', $fingerprint . '|' . microtime( true ) . '|' . count( self::$pending ) ), 0, 24 );
	}

	private static function source_context() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp-cli';
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return 'xml-rpc';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		return is_admin() ? 'admin' : 'other';
	}

	private static function history_limit( $post_id ) {
		$limit = absint( apply_filters( 'wpcc_post_conversion_history_limit', self::DEFAULT_HISTORY_LIMIT, $post_id ) );
		return max( 1, min( self::MAX_HISTORY_LIMIT, $limit ) );
	}

	/** @return array<int,array<string,mixed>> */
	private static function load_history( $post_id, $migrate_legacy ) {
		$stored = get_post_meta( $post_id, self::HISTORY_META_KEY, true );
		if ( is_array( $stored ) && self::HISTORY_SCHEMA === (int) ( $stored['schema'] ?? 0 ) && is_array( $stored['entries'] ?? null ) ) {
			$history = array_values( array_filter( array_map( array( __CLASS__, 'normalize_entry' ), $stored['entries'] ) ) );
			$bounded = array_slice( $history, -self::history_limit( $post_id ) );
			if ( $migrate_legacy && count( $bounded ) !== count( $history ) ) {
				self::save_history( $post_id, $bounded );
			}
			return $bounded;
		}

		$legacy  = get_post_meta( $post_id, self::LEGACY_META_KEY, false );
		$history = is_array( $legacy ) ? array_values( array_filter( array_map( array( __CLASS__, 'normalize_entry' ), $legacy ) ) ) : array();
		$history = array_slice( $history, -self::history_limit( $post_id ) );
		if ( $migrate_legacy && ! empty( $history ) ) {
			self::save_history( $post_id, $history );
		}
		return $history;
	}

	/** @return array<string,mixed>|null */
	private static function normalize_entry( $entry ) {
		if ( ! is_array( $entry ) || ! is_array( $entry['original'] ?? null ) || ! is_array( $entry['converted'] ?? null ) ) {
			return null;
		}
		$entry['original']       = self::fields_from_array( $entry['original'] );
		$entry['converted']      = self::fields_from_array( $entry['converted'] );
		$entry['original_hash']  = self::fields_hash( $entry['original'] );
		$entry['converted_hash'] = self::fields_hash( $entry['converted'] );
		$entry['fingerprint']    = isset( $entry['fingerprint'] ) ? (string) $entry['fingerprint'] : self::fields_hash(
			array_merge(
				$entry['original'],
				array(
					'_target' => $entry['target'] ?? '',
					'_engine' => $entry['engine'] ?? '',
				)
			)
		);
		$entry['id']             = ! empty( $entry['id'] ) ? sanitize_key( $entry['id'] ) : 'legacy-' . substr( $entry['fingerprint'], 0, 24 );
		return $entry;
	}

	private static function save_history( $post_id, $entries ) {
		update_post_meta(
			$post_id,
			self::HISTORY_META_KEY,
			wp_slash(
				array(
					'schema'  => self::HISTORY_SCHEMA,
					'entries' => array_values( $entries ),
				)
			)
		);
	}

	/** @return array<string,mixed>|null */
	private static function find_history_entry( $history, $backup_id ) {
		if ( '' === $backup_id ) {
			$latest = end( $history );
			return is_array( $latest ) ? $latest : null;
		}
		foreach ( $history as $entry ) {
			if ( is_array( $entry ) && isset( $entry['id'] ) && hash_equals( (string) $entry['id'], $backup_id ) ) {
				return $entry;
			}
		}
		return null;
	}

	/** @return array<string,array<string,mixed>> */
	private static function field_diff( $before, $after ) {
		$diff = array();
		foreach ( array( 'post_title', 'post_content' ) as $field ) {
			$diff[ $field ] = array(
				'before'  => (string) $before[ $field ],
				'after'   => (string) $after[ $field ],
				'changed' => (string) $before[ $field ] !== (string) $after[ $field ],
			);
		}
		return $diff;
	}
}

/**
 * Restore a selected saved-content conversion backup.
 *
 * @return array<string,mixed>|WP_Error
 */
function wpcc_restore_post_conversion( $post_id, $backup_id, $expected_hash, $dry_run = false ) {
	return WPCC_Post_Conversion_Backup::restore( $post_id, $backup_id, $expected_hash, $dry_run );
}

/**
 * Backward-compatible latest-backup restore helper.
 *
 * @return bool|WP_Error
 */
function wpcc_restore_latest_post_conversion( $post_id ) {
	$result = WPCC_Post_Conversion_Backup::restore( $post_id );
	return is_wp_error( $result ) ? $result : ! empty( $result['success'] );
}

function wpcc_init_post_conversion() {
	global $wpcc_options;

	if ( empty( $wpcc_options ) ) {
		$wpcc_options = get_wpcc_option( 'wpcc_options' );
	}

	// Recovery must remain available even after automatic conversion is disabled.
	add_action( 'add_meta_boxes', 'wpcc_add_conversion_meta_box' );

	if ( ! empty( $wpcc_options['wpcc_enable_post_conversion'] ) ) {
		add_filter( 'wp_insert_post_data', array( 'WPCC_Post_Conversion_Backup', 'filter_post_data' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( 'WPCC_Post_Conversion_Backup', 'persist_backup' ), 10, 2 );
	}
}

/**
 * 使用区块解析安全转换文章内容，仅转换非 WPCC 区块的纯文本
 */
function wpcc_convert_post_content_safely( $content, $target_lang ) {
	if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) {
		// 回退：无法解析区块时，使用整体转换（可能导致占位被转换）
		return zhconversion( $content, $target_lang );
	}

	$blocks    = parse_blocks( $content );
	$converted = wpcc_convert_blocks_array_safely( $blocks, $target_lang );
	return serialize_blocks( $converted );
}

function wpcc_convert_blocks_array_safely( $blocks, $target_lang ) {
	foreach ( $blocks as &$block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		// 跳过 WPCC 自有区块，避免转换其内部占位与结构
		if ( substr( $name, 0, 5 ) === 'wpcc/' ) {
			// 递归处理其子块（如不希望转换子块，也可以直接 continue）
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = wpcc_convert_blocks_array_safely( $block['innerBlocks'], $target_lang );
			}
			continue;
		}

		// 转换非 WPCC 区块的纯文本内容
		if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $i => $piece ) {
				if ( is_string( $piece ) && $piece !== '' ) {
					$block['innerContent'][ $i ] = zhconversion( $piece, $target_lang );
				}
			}
		}

		if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) && $block['innerHTML'] !== '' ) {
			$block['innerHTML'] = zhconversion( $block['innerHTML'], $target_lang );
		}

		// 递归处理子块
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = wpcc_convert_blocks_array_safely( $block['innerBlocks'], $target_lang );
		}
	}
	return $blocks;
}
