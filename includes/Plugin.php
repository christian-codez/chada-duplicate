<?php
/**
 * Plugin — bootstraps and wires all components.
 *
 * Phase 0/1 scaffold: only the textdomain and the marketplace Updater are wired
 * here. Later phases add the duplicator engine, list-table/editor entry points,
 * the settings page, and the cross-sell panel (see BUILD-PLAN.md).
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate;

use Chada\Duplicate\Admin\CrossSell;
use Chada\Duplicate\Admin\EditorButton;
use Chada\Duplicate\Admin\ListActions;
use Chada\Duplicate\Admin\Notices;
use Chada\Duplicate\Admin\SettingsPage;
use Chada\Duplicate\Licensing\Updater;
use Chada\Duplicate\Licensing\UpdateClient;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null Single shared instance. */
	private static $instance = null;

	/**
	 * Resolve the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Use {@see Plugin::instance()} — not constructed directly.
	 */
	private function __construct() {}

	/**
	 * Wire everything up on `plugins_loaded`.
	 *
	 * @return void
	 */
	/** Base-language locales we bundle translations for. */
	const SHIPPED_LOCALES = array( 'de_DE', 'es_ES', 'fr_FR' );

	public function boot() {
		// Load translations on `init` (WP 6.7+ warns if a textdomain is loaded
		// earlier than that). The bundled .mo/.json live in /languages.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Regional fallback: a fr_CA / es_AR / de_AT site should still get our
		// fr_FR / es_ES / de_DE strings rather than English.
		add_filter( 'load_textdomain_mofile', array( $this, 'fallback_textdomain_mofile' ), 10, 2 );
		add_filter( 'load_script_translation_file', array( $this, 'fallback_script_translation_file' ), 10, 3 );

		// Auto-updates from the CHADA marketplace (free product — no license gating).
		$update_client = new UpdateClient();
		( new Updater( $update_client ) )->register();

		// Admin-only: list-table Duplicate actions + result notices + editor button.
		if ( is_admin() ) {
			( new ListActions() )->register();
			( new Notices() )->register();
			( new EditorButton() )->register();
			( new SettingsPage() )->register();
			( new CrossSell() )->register();
		}
	}

	/**
	 * Load the plugin text domain (hooked on `init`).
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'chada-duplicate', false, dirname( CHADA_DUP_BASENAME ) . '/languages' );
	}

	/**
	 * The base-language locale we ship for a given locale, or null. An exact
	 * shipped locale returns itself; a regional variant (fr_CA) maps to its base
	 * (fr_FR) by language code.
	 *
	 * @param string $locale e.g. 'fr_CA'.
	 * @return string|null
	 */
	private function shipped_base_locale( $locale ) {
		if ( in_array( $locale, self::SHIPPED_LOCALES, true ) ) {
			return $locale;
		}
		$language = substr( $locale, 0, 2 );
		foreach ( self::SHIPPED_LOCALES as $shipped ) {
			if ( 0 === strpos( $shipped, $language ) ) {
				return $shipped;
			}
		}
		return null;
	}

	/**
	 * Fall back to the shipped base-language .mo when the exact locale is missing.
	 *
	 * @param string $mofile Path to the .mo WordPress wants to load.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public function fallback_textdomain_mofile( $mofile, $domain ) {
		if ( 'chada-duplicate' !== $domain || file_exists( $mofile ) ) {
			return $mofile;
		}
		if ( ! preg_match( '/chada-duplicate-([a-zA-Z_]+)\.mo$/', $mofile, $matches ) ) {
			return $mofile;
		}
		$base = $this->shipped_base_locale( $matches[1] );
		if ( $base && $base !== $matches[1] ) {
			$candidate = preg_replace( '/chada-duplicate-[a-zA-Z_]+\.mo$/', "chada-duplicate-{$base}.mo", $mofile );
			if ( $candidate && file_exists( $candidate ) ) {
				return $candidate;
			}
		}
		return $mofile;
	}

	/**
	 * Same regional fallback for the block-editor JS translation JSON. The md5
	 * suffix is locale-independent, so only the locale segment is swapped.
	 *
	 * @param string|false $file   Path to the JSON, or false.
	 * @param string       $handle Script handle.
	 * @param string       $domain Text domain.
	 * @return string|false
	 */
	public function fallback_script_translation_file( $file, $handle, $domain ) {
		if ( 'chada-duplicate' !== $domain || ! is_string( $file ) || file_exists( $file ) ) {
			return $file;
		}
		if ( ! preg_match( '/chada-duplicate-([a-zA-Z_]+)-[0-9a-f]{32}\.json$/', $file, $matches ) ) {
			return $file;
		}
		$base = $this->shipped_base_locale( $matches[1] );
		if ( $base && $base !== $matches[1] ) {
			$candidate = preg_replace( '/chada-duplicate-[a-zA-Z_]+-([0-9a-f]{32})\.json$/', "chada-duplicate-{$base}-\$1.json", $file );
			if ( $candidate && file_exists( $candidate ) ) {
				return $candidate;
			}
		}
		return $file;
	}
}
