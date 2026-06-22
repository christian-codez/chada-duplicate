<?php
/**
 * Plugin Name:       Chada Duplicate
 * Plugin URI:        https://shop.chadacreatives.com/chada-duplicate
 * Description:        One-click duplicate / clone for posts, pages, public custom post types, and WooCommerce products (including variations). Fast, complete clones that copy meta, taxonomies, the featured image, and full product data.
 * Version:           0.1.0-dev
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Chada Creatives
 * Author URI:        https://chadacreatives.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chada-duplicate
 * Domain Path:       /languages
 * Update URI:        https://shop.chadacreatives.com/cdup-update
 *
 * IMPORTANT: the Update URI host MUST match the host of CHADA_DUP_PLATFORM_URL —
 * WordPress dispatches updates to `update_plugins_<host>` using the URI host as
 * a literal string, so change the two together.
 *
 * This is the FREE lead-magnet product: it ships the Updater only (no license
 * gating, no premium tiers). See CLAUDE.md for the locked MVP decisions.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate;

defined( 'ABSPATH' ) || exit;

/*
 * Single source of truth for the version is the `Version:` header above —
 * derive the constant from it so the number never lives in two places.
 * get_file_data() loads before plugins, so it is always available here.
 */
if ( ! defined( 'CHADA_DUP_VERSION' ) ) {
	$cdup_header = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
	define( 'CHADA_DUP_VERSION', '' !== $cdup_header['Version'] ? $cdup_header['Version'] : '0.0.0' );
	unset( $cdup_header );
}
define( 'CHADA_DUP_FILE', __FILE__ );
define( 'CHADA_DUP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHADA_DUP_URL', plugin_dir_url( __FILE__ ) );
define( 'CHADA_DUP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The CHADA marketplace that serves /update-check and hosts release ZIPs.
 * Override in wp-config.php for local dev (point at a local platform). Keep the
 * host in sync with the `Update URI:` header above.
 */
if ( ! defined( 'CHADA_DUP_PLATFORM_URL' ) ) {
	define( 'CHADA_DUP_PLATFORM_URL', 'https://shop.chadacreatives.com' );
}

/**
 * PSR-4-ish autoloader: Chada\Duplicate\Foo\Bar -> includes/Foo/Bar.php
 */
spl_autoload_register(
	static function ( $fully_qualified_class ) {
		$namespace_prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $fully_qualified_class, $namespace_prefix ) ) {
			return;
		}
		$relative_class = substr( $fully_qualified_class, strlen( $namespace_prefix ) );
		$class_path     = CHADA_DUP_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';
		if ( is_readable( $class_path ) ) {
			require $class_path;
		}
	}
);

// Activation / deactivation lifecycle.
register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Installer::class, 'deactivate' ) );

// Boot the plugin once WordPress (and other plugins, e.g. WooCommerce) are loaded.
add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->boot();
	}
);
