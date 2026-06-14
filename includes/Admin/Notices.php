<?php
/**
 * Notices — post-duplicate admin notices, per the design handoff (Screen A).
 *
 * These render from the query args our redirect adds. They are read-only status
 * messages (no action is performed), so they carry no nonce; all dynamic values
 * are escaped. Markup uses native `.notice` classes (styling inherited from
 * core) plus a `cdup-`-prefixed hook class/ID for our own targeting.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate\Admin;

defined( 'ABSPATH' ) || exit;

class Notices {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Render whichever notice the current request asks for.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! empty( $_GET['cdup_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_single_success();
			return;
		}

		if ( isset( $_GET['cdup_bulk_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_bulk_success();
			return;
		}

		if ( ! empty( $_GET['cdup_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_error();
		}
	}

	/**
	 * Single-duplicate success: «'<Title>' duplicated.  Edit the copy →».
	 *
	 * @return void
	 */
	private function render_single_success() {
		$source_post_id = isset( $_GET['cdup_from'] ) ? absint( $_GET['cdup_from'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$new_post_id    = isset( $_GET['cdup_new'] ) ? absint( $_GET['cdup_new'] ) : 0;    // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$source_title = $source_post_id ? get_the_title( $source_post_id ) : '';
		$edit_copy_url = $new_post_id ? get_edit_post_link( $new_post_id, 'raw' ) : '';

		$message = $source_title
			/* translators: %s: title of the duplicated item, shown in quotes. */
			? sprintf( __( '&#8216;%s&#8217; duplicated.', 'chada-duplicate' ), esc_html( $source_title ) )
			: esc_html__( 'Item duplicated.', 'chada-duplicate' );

		$edit_link_html = $edit_copy_url
			? sprintf(
				' <a href="%1$s" class="cdup-notice-edit-link">%2$s</a>',
				esc_url( $edit_copy_url ),
				esc_html__( 'Edit the copy &rarr;', 'chada-duplicate' )
			)
			: '';

		printf(
			'<div id="cdup-notice-duplicated" class="cdup-notice notice notice-success is-dismissible"><p>%1$s%2$s</p></div>',
			$message,        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html above.
			$edit_link_html  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url/esc_html above.
		);
	}

	/**
	 * Bulk-duplicate success: «N items duplicated  View drafts →».
	 *
	 * @return void
	 */
	private function render_bulk_success() {
		$duplicated_count = isset( $_GET['cdup_bulk_duplicated'] ) ? absint( $_GET['cdup_bulk_duplicated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $duplicated_count < 1 ) {
			printf(
				'<div id="cdup-notice-bulk" class="cdup-notice notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'No items were duplicated.', 'chada-duplicate' )
			);
			return;
		}

		$message = sprintf(
			/* translators: %s: number of duplicated items. */
			esc_html( _n( '%s item duplicated', '%s items duplicated', $duplicated_count, 'chada-duplicate' ) ),
			number_format_i18n( $duplicated_count )
		);

		$drafts_url = add_query_arg(
			array( 'post_status' => 'draft' ),
			$this->current_list_table_url()
		);

		printf(
			'<div id="cdup-notice-bulk" class="cdup-notice notice notice-success is-dismissible"><p>%1$s <a href="%2$s" class="cdup-notice-drafts-link">%3$s</a></p></div>',
			$message, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_url( $drafts_url ),
			esc_html__( 'View drafts &rarr;', 'chada-duplicate' )
		);
	}

	/**
	 * Failure notice for a duplicate that could not be created.
	 *
	 * @return void
	 */
	private function render_error() {
		printf(
			'<div id="cdup-notice-error" class="cdup-notice notice notice-error is-dismissible"><p>%s</p></div>',
			esc_html__( 'The item could not be duplicated. Please try again.', 'chada-duplicate' )
		);
	}

	/**
	 * The current list-table URL (without our own notice query args), used to
	 * build the "View drafts" link.
	 *
	 * @return string
	 */
	private function current_list_table_url() {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = ( $screen && $screen->post_type ) ? $screen->post_type : 'post';

		return add_query_arg( 'post_type', $post_type, admin_url( 'edit.php' ) );
	}
}
