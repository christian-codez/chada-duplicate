<?php
/**
 * Settings — typed access to the single `cdup_settings` option.
 *
 * One option array holds every setting (see CLAUDE.md "State Management").
 * Reads fall back to {@see Settings::defaults()}, so the plugin behaves sensibly
 * before the settings page is ever saved.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'cdup_settings';

	/** Default-enabled post types (other public CPTs are opt-in). */
	const DEFAULT_POST_TYPES = array( 'post', 'page', 'product' );

	/** Internal meta keys excluded from copies by default. */
	const DEFAULT_EXCLUDED_META = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date' );

	/** Roles allowed to duplicate by default. */
	const DEFAULT_ROLES = array( 'administrator', 'editor', 'shop_manager' );

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'post_types'     => self::DEFAULT_POST_TYPES,
			'default_status' => 'draft', // 'draft' | 'inherit' (same as original).
			'title_suffix'   => Duplicator::TITLE_SUFFIX,
			'copy_author'    => true,
			'copy_comments'  => false,
			'copy_price'     => true,
			'excluded_meta'  => self::DEFAULT_EXCLUDED_META,
			'allowed_roles'  => self::DEFAULT_ROLES,
		);
	}

	/**
	 * The full settings array (saved values merged over defaults).
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * A single setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}
}
