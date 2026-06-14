<?php
/**
 * EditorButton — the "Copy to a new draft" control inside the post editor.
 *
 * Two entry points, both cloning the post currently being edited and then
 * redirecting to the NEW draft in the same editor (design handoff, Screen B):
 *
 * - Classic editor: a link in the Publish metabox via `post_submitbox_misc_actions`.
 * - Block editor: a `PluginPostStatusInfo` control + a `PluginMoreMenuItem`,
 *   rendered by assets/js/editor.js (plain JS using the `wp.*` globals — no build
 *   step, matching the sibling plugins' convention).
 *
 * Both reuse the single-duplicate handler in {@see ListActions} with
 * `cdup_redirect=editor`, so capability + per-post nonce are enforced server-side.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate\Admin;

use Chada\Duplicate\Duplicator;
use Chada\Duplicate\Support;

defined( 'ABSPATH' ) || exit;

class EditorButton {

	const SCRIPT_HANDLE = 'cdup-editor';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_classic_button' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Render the "Copy to a new draft" link in the classic Publish metabox.
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public function render_classic_button( $post ) {
		if ( ! $this->is_duplicatable( $post ) ) {
			return;
		}

		printf(
			'<div class="misc-pub-section cdup-copy-to-draft"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <a href="%1$s" class="cdup-copy-to-draft-link">%2$s</a></div>',
			esc_url( ListActions::editor_duplicate_url( $post->ID ) ),
			esc_html__( 'Copy to a new draft', 'chada-duplicate' )
		);
	}

	/**
	 * Enqueue the block-editor control script for a duplicatable post.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		$post = get_post();
		if ( ! $this->is_duplicatable( $post ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CHADA_DUP_URL . 'assets/js/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			Support::asset_version( 'assets/js/editor.js' ),
			true
		);

		wp_set_script_translations( self::SCRIPT_HANDLE, 'chada-duplicate' );

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'cdupEditor',
			array(
				'duplicateUrl' => ListActions::editor_duplicate_url( $post->ID ),
			)
		);
	}

	/**
	 * Whether the given post can be duplicated from the editor: a real, saved
	 * post of a supported type that the current user may duplicate.
	 *
	 * @param \WP_Post|null $post Post being edited.
	 * @return bool
	 */
	private function is_duplicatable( $post ) {
		if ( ! $post instanceof \WP_Post || 'auto-draft' === $post->post_status ) {
			return false;
		}
		// Products keep WooCommerce's own edit-screen "Copy to a new draft"
		// button; we own the Products list row/bulk action instead. (The list
		// action still routes products through ProductDuplicator.)
		if ( 'product' === $post->post_type ) {
			return false;
		}
		return Duplicator::current_user_can_duplicate( $post->ID );
	}
}
