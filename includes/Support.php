<?php
/**
 * Support — small shared helpers.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate;

defined( 'ABSPATH' ) || exit;

class Support {

	/**
	 * Cache-busting version for a bundled asset: the file's mtime, falling back
	 * to the plugin version. Mirrors the sibling plugins' convention.
	 *
	 * @param string $relative_path Path relative to the plugin root (e.g. 'assets/js/editor.js').
	 * @return string
	 */
	public static function asset_version( $relative_path ) {
		$absolute_path = CHADA_DUP_DIR . ltrim( $relative_path, '/' );
		return file_exists( $absolute_path ) ? (string) filemtime( $absolute_path ) : CHADA_DUP_VERSION;
	}
}
