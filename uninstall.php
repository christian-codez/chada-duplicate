<?php
/**
 * Uninstall — remove all plugin data.
 *
 * No database table is created by this plugin, so cleanup is limited to the
 * settings option, the cached update check, and the per-user cross-sell
 * dismissal flag.
 *
 * @package Chada\Duplicate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Single settings array (Settings phase) + cached marketplace update check.
delete_option( 'cdup_settings' );
delete_transient( 'cdup_update_check' );

// Per-user "More by Chada" cross-sell dismissal flag (Cross-sell phase).
delete_metadata( 'user', 0, 'cdup_crosssell_dismissed', '', true );
