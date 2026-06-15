<?php
/**
 * CrossSell — the tasteful "More by Chada" panel (design handoff, Screen D).
 *
 * The ONE custom-styled surface in the plugin. Rendered only at the bottom of
 * the Chada Duplicate settings page (never a store-wide notice, activation
 * redirect, or dashboard nag — see CLAUDE.md, decision #3). Dismiss is permanent
 * and per-user (user meta `cdup_crosssell_dismissed`).
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate\Admin;

use Chada\Duplicate\Support;

defined( 'ABSPATH' ) || exit;

class CrossSell {

	const USER_META  = 'cdup_crosssell_dismissed';
	const DISMISS_ARG = 'cdup_dismiss_crosssell';
	const SCREEN_ID  = 'settings_page_chada-duplicate';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'handle_dismiss' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'cdup_after_settings_form', array( $this, 'render' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_link' ) );
	}

	/**
	 * The paid suite shown in the panel.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function products() {
		return array(
			array(
				'slug'    => 'chada-cart-recovery',
				'icon'    => 'cart',
				'title'   => __( 'Cart Recovery', 'chada-duplicate' ),
				'benefit' => __( 'Win back abandoned carts automatically.', 'chada-duplicate' ),
			),
			array(
				'slug'    => 'chada-ai-content',
				'icon'    => 'lightbulb',
				'title'   => __( 'AI Content', 'chada-duplicate' ),
				'benefit' => __( 'Generate product descriptions in seconds.', 'chada-duplicate' ),
			),
			array(
				'slug'    => 'chada-woo-schema',
				'icon'    => 'editor-code',
				'title'   => __( 'Woo Schema', 'chada-duplicate' ),
				'benefit' => __( 'Rich-result schema for better search listings.', 'chada-duplicate' ),
			),
			array(
				'slug'    => 'woofraudguard',
				'icon'    => 'shield',
				'title'   => __( 'WooFraudGuard', 'chada-duplicate' ),
				'benefit' => __( 'Score and block fraudulent orders.', 'chada-duplicate' ),
			),
			array(
				'slug'    => 'chada-activity-monitor',
				'icon'    => 'visibility',
				'title'   => __( 'Activity Monitor', 'chada-duplicate' ),
				'benefit' => __( 'See who changed what, and when.', 'chada-duplicate' ),
			),
		);
	}

	/**
	 * Whether the current user has dismissed the panel.
	 *
	 * @return bool
	 */
	private function is_dismissed() {
		return (bool) get_user_meta( get_current_user_id(), self::USER_META, true );
	}

	/**
	 * Marketplace URL for a product slug, with cross-sell UTM tags.
	 *
	 * @param string $slug Product slug.
	 * @return string
	 */
	private function product_url( $slug ) {
		$base   = defined( 'CHADA_DUP_PLATFORM_URL' ) ? CHADA_DUP_PLATFORM_URL : 'https://shop.chadacreatives.com';
		$target = '' === $slug ? trailingslashit( $base ) : trailingslashit( $base ) . $slug . '/';
		return add_query_arg(
			array(
				'utm_source'   => 'chada-duplicate',
				'utm_medium'   => 'cross-sell',
				'utm_campaign' => 'more-by-chada',
			),
			$target
		);
	}

	/**
	 * Handle the Dismiss link (sets the per-user flag, permanently).
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( empty( $_GET[ self::DISMISS_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		check_admin_referer( self::DISMISS_ARG );

		update_user_meta( get_current_user_id(), self::USER_META, 1 );

		wp_safe_redirect( remove_query_arg( array( self::DISMISS_ARG, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Enqueue the panel stylesheet on the settings screen only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		if ( self::SCREEN_ID !== $hook_suffix || $this->is_dismissed() ) {
			return;
		}
		wp_enqueue_style(
			'cdup-settings',
			CHADA_DUP_URL . 'assets/css/settings.css',
			array( 'dashicons' ),
			Support::asset_version( 'assets/css/settings.css' )
		);
	}

	/**
	 * Render the panel after the settings form.
	 *
	 * @return void
	 */
	public function render() {
		if ( $this->is_dismissed() ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( self::DISMISS_ARG, '1' ),
			self::DISMISS_ARG
		);
		?>
		<div class="cdup-crosssell" id="cdup-crosssell">
			<div class="cdup-crosssell__head">
				<span class="cdup-crosssell__mark" aria-hidden="true"><span class="dashicons dashicons-megaphone"></span></span>
				<div class="cdup-crosssell__intro">
					<h2 class="cdup-crosssell__title"><?php esc_html_e( 'More by Chada', 'chada-duplicate' ); ?></h2>
					<p class="cdup-crosssell__subtitle"><?php esc_html_e( 'Free and paid tools that pair well with Chada Duplicate — no upsell, no lock-in.', 'chada-duplicate' ); ?></p>
				</div>
				<a class="cdup-crosssell__dismiss" href="<?php echo esc_url( $dismiss_url ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Dismiss', 'chada-duplicate' ); ?>
				</a>
			</div>

			<div class="cdup-crosssell__grid">
				<?php foreach ( $this->products() as $product ) : ?>
					<div class="cdup-crosssell__card">
						<span class="cdup-crosssell__chip" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr( $product['icon'] ); ?>"></span></span>
						<h3 class="cdup-crosssell__card-title"><?php echo esc_html( $product['title'] ); ?></h3>
						<p class="cdup-crosssell__benefit"><?php echo esc_html( $product['benefit'] ); ?></p>
						<a class="cdup-crosssell__link" href="<?php echo esc_url( $this->product_url( $product['slug'] ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Learn more', 'chada-duplicate' ); ?> <span aria-hidden="true">&rarr;</span>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Small "More by Chada" link in the admin footer on the settings screen.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function footer_link( $text ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::SCREEN_ID !== $screen->id ) {
			return $text;
		}

		$link = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $this->product_url( '' ) ),
			esc_html__( 'More by Chada', 'chada-duplicate' )
		);
		return $text . ' &middot; ' . $link;
	}
}
