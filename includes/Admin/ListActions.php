<?php
/**
 * ListActions — the Duplicate row action and bulk action on list tables.
 *
 * Wiring:
 * - The single-row handler hooks `admin_action_{ACTION}` (fired early by
 *   wp-admin/admin.php), so it is registered at boot.
 * - The row-action links and the per-screen bulk filters need the full
 *   post-type list, which is only complete after `init`, so they are attached
 *   on `admin_init`.
 *
 * Security: the single action carries a per-post nonce verified here; the bulk
 * action is covered by core's `bulk-{plural}` nonce before our handler runs.
 * Both paths re-check the capability per post.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate\Admin;

use Chada\Duplicate\Duplicator;

defined( 'ABSPATH' ) || exit;

class ListActions {

	/**
	 * Single-row action slug (admin.php?action=…). This is also the name of the
	 * `admin_action_{ACTION}` hook fired for every admin page that includes
	 * wp-admin/admin.php.
	 */
	const ACTION = 'cdup_duplicate';

	/**
	 * Bulk-action value. MUST differ from {@see ListActions::ACTION}: a bulk
	 * submit lands on edit.php (which includes admin.php), so a shared value
	 * would fire the single `admin_action_` handler and hijack the bulk request.
	 */
	const BULK_ACTION = 'cdup_bulk_duplicate';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_action_' . self::ACTION, array( $this, 'handle_single_duplicate' ) );
		add_action( 'admin_init', array( $this, 'register_screen_hooks' ) );
	}

	/**
	 * Attach the row-action and bulk-action filters once all post types exist.
	 *
	 * @return void
	 */
	public function register_screen_hooks() {
		// Row actions: `post_row_actions` (non-hierarchical) + `page_row_actions`
		// (hierarchical) together cover every public post type.
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_row_action' ), 10, 2 );

		foreach ( Duplicator::supported_post_types() as $post_type ) {
			$screen_id = 'edit-' . $post_type;
			add_filter( "bulk_actions-{$screen_id}", array( $this, 'add_bulk_action' ) );
			add_filter( "handle_bulk_actions-{$screen_id}", array( $this, 'handle_bulk_duplicate' ), 10, 3 );
		}
	}

	/**
	 * Nonce action string for a single post's duplicate link.
	 *
	 * @param int $source_post_id Post ID.
	 * @return string
	 */
	public static function nonce_action( $source_post_id ) {
		return self::ACTION . '_' . (int) $source_post_id;
	}

	/**
	 * Add the "Duplicate" link to a row's action list.
	 *
	 * @param array    $row_actions Existing row actions.
	 * @param \WP_Post $post        The row's post.
	 * @return array
	 */
	public function add_row_action( $row_actions, $post ) {
		if ( ! Duplicator::current_user_can_duplicate( $post->ID ) ) {
			return $row_actions;
		}

		$row_actions[ self::ACTION ] = sprintf(
			'<a href="%1$s" class="cdup-row-action-duplicate" aria-label="%2$s">%3$s</a>',
			esc_url( $this->build_single_duplicate_url( $post->ID ) ),
			/* translators: %s: post title. */
			esc_attr( sprintf( __( 'Duplicate &#8220;%s&#8221;', 'chada-duplicate' ), get_the_title( $post ) ) ),
			esc_html__( 'Duplicate', 'chada-duplicate' )
		);

		return $row_actions;
	}

	/**
	 * Add the "Duplicate" option to a list table's Bulk Actions dropdown.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array
	 */
	public function add_bulk_action( $bulk_actions ) {
		$bulk_actions[ self::BULK_ACTION ] = __( 'Duplicate', 'chada-duplicate' );
		return $bulk_actions;
	}

	/**
	 * Handle a single-row Duplicate click (admin.php?action=cdup_duplicate).
	 *
	 * @return void
	 */
	public function handle_single_duplicate() {
		// Defense in depth: a bulk submit sends `post[]` as an array. The bulk
		// value (BULK_ACTION) differs from this hook's action so we should never
		// be reached that way, but bail rather than misread an array as an ID.
		if ( isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$source_post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
		if ( ! $source_post_id ) {
			wp_die( esc_html__( 'No item was specified to duplicate.', 'chada-duplicate' ) );
		}

		check_admin_referer( self::nonce_action( $source_post_id ) );

		if ( ! Duplicator::current_user_can_duplicate( $source_post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this item.', 'chada-duplicate' ) );
		}

		$source_post = get_post( $source_post_id );
		$clone_result = ( new Duplicator() )->clone_post( $source_post_id );

		$list_table_url = $this->list_table_url( $source_post );
		if ( is_wp_error( $clone_result ) ) {
			$redirect_url = add_query_arg( 'cdup_error', '1', $list_table_url );
		} else {
			$redirect_url = add_query_arg(
				array(
					'cdup_duplicated' => '1',
					'cdup_new'        => $clone_result,
					'cdup_from'       => $source_post_id,
				),
				$list_table_url
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle the bulk Duplicate action. Core has already verified the bulk nonce.
	 *
	 * @param string $redirect_url Redirect target back to the list table.
	 * @param string $doaction     The chosen bulk action.
	 * @param int[]  $post_ids     Checked post IDs.
	 * @return string
	 */
	public function handle_bulk_duplicate( $redirect_url, $doaction, $post_ids ) {
		if ( self::BULK_ACTION !== $doaction ) {
			return $redirect_url;
		}

		$duplicator       = new Duplicator();
		$duplicated_count = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id || ! Duplicator::current_user_can_duplicate( $post_id ) ) {
				continue;
			}
			if ( ! is_wp_error( $duplicator->clone_post( $post_id ) ) ) {
				$duplicated_count++;
			}
		}

		$redirect_url = remove_query_arg( array( 'cdup_duplicated', 'cdup_new', 'cdup_from', 'cdup_error' ), $redirect_url );
		return add_query_arg( 'cdup_bulk_duplicated', $duplicated_count, $redirect_url );
	}

	/**
	 * Build the nonce-protected single-duplicate URL for a post.
	 *
	 * @param int $source_post_id Post ID.
	 * @return string
	 */
	private function build_single_duplicate_url( $source_post_id ) {
		$action_url = add_query_arg(
			array(
				'action' => self::ACTION,
				'post'   => $source_post_id,
			),
			admin_url( 'admin.php' )
		);

		return wp_nonce_url( $action_url, self::nonce_action( $source_post_id ) );
	}

	/**
	 * Resolve the list-table URL to return to after a single duplicate. Prefers
	 * the referring list screen, falling back to the post type's edit.php.
	 *
	 * @param \WP_Post|null $source_post The duplicated post.
	 * @return string
	 */
	private function list_table_url( $source_post ) {
		$referer_url = wp_get_referer();
		if ( $referer_url ) {
			return $referer_url;
		}

		$post_type = $source_post instanceof \WP_Post ? $source_post->post_type : 'post';
		return add_query_arg( 'post_type', $post_type, admin_url( 'edit.php' ) );
	}
}
