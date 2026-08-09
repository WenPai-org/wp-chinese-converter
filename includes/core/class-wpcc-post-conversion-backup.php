<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPCC_Post_Conversion_Backup {
	private const META_KEY = '_wpcc_conversion_backup';

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

		$post_type         = isset( $data['post_type'] ) ? sanitize_key( $data['post_type'] ) : '';
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
		$engine     = isset( $wpcc_options['wpcc_engine'] ) ? sanitize_key( $wpcc_options['wpcc_engine'] ) : 'mediawiki';
		$original    = array(
			'post_title'   => isset( $data['post_title'] ) ? (string) $data['post_title'] : '',
			'post_content' => isset( $data['post_content'] ) ? (string) $data['post_content'] : '',
		);

		try {
			if ( '' !== $original['post_content'] ) {
				$data['post_content'] = wpcc_convert_post_content_safely( $original['post_content'], $target_lang );
			}
			if ( '' !== $original['post_title'] ) {
				$data['post_title'] = zhconversion( $original['post_title'], $target_lang );
			}
		} catch ( Throwable $e ) {
			error_log( 'WPCC Post Conversion Error: ' . $e->getMessage() );
			return $data;
		}

		$converted = array(
			'post_title'   => isset( $data['post_title'] ) ? (string) $data['post_title'] : '',
			'post_content' => isset( $data['post_content'] ) ? (string) $data['post_content'] : '',
		);
		if ( $original !== $converted ) {
			self::$pending[] = array(
				'post_id'    => $post_id,
				'target'     => $target_lang,
				'engine'     => $engine,
				'original'   => $original,
				'converted'  => $converted,
				'fingerprint'=> hash( 'sha256', wp_json_encode( array( $post_id, $target_lang, $engine, $original ) ) ),
				'user_id'    => get_current_user_id(),
				'created_at' => time(),
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
				$existing = get_post_meta( $post_id, self::META_KEY, false );
				$latest   = is_array( $existing ) ? end( $existing ) : false;
				if ( ! is_array( $latest ) || empty( $latest['fingerprint'] ) || $latest['fingerprint'] !== $backup['fingerprint'] ) {
					add_post_meta( $post_id, self::META_KEY, $backup, false );
				}
				unset( self::$pending[ $index ] );
				self::$pending = array_values( self::$pending );
				break;
			}
		}
	}

	/**
	 * Restore the most recent pre-conversion title/content without deleting history.
	 *
	 * @param int $post_id Post ID.
	 * @return bool|WP_Error
	 */
	public static function restore_latest( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wpcc_forbidden', __( 'You are not allowed to restore this post.', 'wp-chinese-converter' ) );
		}

		$backups = get_post_meta( $post_id, self::META_KEY, false );
		$backup  = is_array( $backups ) ? end( $backups ) : false;
		if ( ! is_array( $backup ) || empty( $backup['original'] ) || ! is_array( $backup['original'] ) ) {
			return new WP_Error( 'wpcc_no_backup', __( 'No conversion backup is available.', 'wp-chinese-converter' ) );
		}
		$current = get_post( $post_id );
		if (
			! $current instanceof WP_Post ||
			empty( $backup['converted'] ) ||
			$current->post_title !== (string) ( $backup['converted']['post_title'] ?? '' ) ||
			$current->post_content !== (string) ( $backup['converted']['post_content'] ?? '' )
		) {
			return new WP_Error( 'wpcc_post_changed', __( 'The post changed after conversion; automatic restore was stopped.', 'wp-chinese-converter' ) );
		}

		self::$restoring = true;
		$result          = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => (string) ( $backup['original']['post_title'] ?? '' ),
				'post_content' => (string) ( $backup['original']['post_content'] ?? '' ),
			),
			true
		);
		self::$restoring = false;

		return ! is_wp_error( $result );
	}
}

/**
 * Restore the latest saved-content conversion backup.
 *
 * @param int $post_id Post ID.
 * @return bool|WP_Error
 */
function wpcc_restore_latest_post_conversion( $post_id ) {
	return WPCC_Post_Conversion_Backup::restore_latest( $post_id );
}

function wpcc_init_post_conversion() {
	global $wpcc_options;

	if ( empty( $wpcc_options ) ) {
		$wpcc_options = get_wpcc_option( 'wpcc_options' );
	}

	if ( ! empty( $wpcc_options['wpcc_enable_post_conversion'] ) ) {
		add_filter( 'wp_insert_post_data', array( 'WPCC_Post_Conversion_Backup', 'filter_post_data' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( 'WPCC_Post_Conversion_Backup', 'persist_backup' ), 10, 2 );

		add_action( 'add_meta_boxes', 'wpcc_add_conversion_meta_box' );
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

	$blocks = parse_blocks( $content );
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
