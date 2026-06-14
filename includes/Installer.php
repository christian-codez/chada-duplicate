<?php
/**
 * Installer — activation / deactivation lifecycle.
 *
 * This plugin has NO database table (see CLAUDE.md). State lives in a single
 * options array (`cdup_settings`) plus a per-user dismissal meta flag, both
 * introduced in later phases. For now activation only ensures the next update
 * check is fresh.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate;

use Chada\Duplicate\Licensing\UpdateClient;

defined( 'ABSPATH' ) || exit;

class Installer {

	/** Option name holding the single settings array (added in the Settings phase). */
	const SETTINGS_OPTION = 'cdup_settings';

	/**
	 * Activation: drop any stale update-check cache so a freshly installed copy
	 * checks the marketplace promptly.
	 *
	 * @return void
	 */
	public static function activate() {
		( new UpdateClient() )->flush();
	}

	/**
	 * Deactivation: clear the cached update check (keep settings + user meta).
	 *
	 * @return void
	 */
	public static function deactivate() {
		( new UpdateClient() )->flush();
	}
}
