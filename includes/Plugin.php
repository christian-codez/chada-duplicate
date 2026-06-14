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
	public function boot() {
		load_plugin_textdomain( 'chada-duplicate', false, dirname( CHADA_DUP_BASENAME ) . '/languages' );

		// Auto-updates from the CHADA marketplace (free product — no license gating).
		$update_client = new UpdateClient();
		( new Updater( $update_client ) )->register();

		// Admin-only: list-table Duplicate actions + result notices + editor button.
		if ( is_admin() ) {
			( new ListActions() )->register();
			( new Notices() )->register();
			( new EditorButton() )->register();
			( new SettingsPage() )->register();
		}
	}
}
