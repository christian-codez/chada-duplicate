<?php
/**
 * ProductDuplicator — clones WooCommerce products (incl. variations).
 *
 * Extends {@see Duplicator} (inheriting the capability / supported-type / nonce
 * machinery) but overrides the clone itself to go through the WooCommerce CRUD
 * objects rather than raw post-meta copying. Cloning the product object copies
 * attributes, the gallery, downloadable files, and all product meta; WooCommerce
 * then keeps its lookup tables in sync on save. Each variation (a child post) is
 * cloned and re-parented to the new product.
 *
 * Why CRUD instead of the base meta copy: a raw copy would duplicate the `_sku`
 * verbatim (WooCommerce rejects duplicate SKUs on save), would not recreate the
 * variation child posts, and would leave the product lookup tables stale.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate;

defined( 'ABSPATH' ) || exit;

class ProductDuplicator extends Duplicator {

	/**
	 * Clone a WooCommerce product and return the new product ID.
	 *
	 * @param int $source_post_id Product being duplicated.
	 * @return int|\WP_Error New draft product ID, or WP_Error.
	 */
	public function clone_post( $source_post_id ) {
		// If WooCommerce is gone, fall back to the generic post clone.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return parent::clone_post( $source_post_id );
		}

		$product = wc_get_product( $source_post_id );
		if ( ! $product instanceof \WC_Product ) {
			return new \WP_Error( 'cdup_not_product', __( 'That product could not be found.', 'chada-duplicate' ) );
		}

		$copy_price = $this->should_copy_price();

		// WC_Data::__clone() resets each meta row's id, so saving the clone
		// inserts fresh meta instead of overwriting the source's.
		$duplicate = clone $product;
		$duplicate->set_id( 0 );
		$duplicate->set_name( $product->get_name() . self::TITLE_SUFFIX );
		$duplicate->set_status( self::DEFAULT_STATUS );
		$duplicate->set_slug( '' );
		$duplicate->set_date_created( null );
		$duplicate->set_total_sales( 0 );
		$duplicate->set_rating_counts( array() );
		$duplicate->set_average_rating( 0 );
		$duplicate->set_review_count( 0 );
		$this->handle_sku( $duplicate, $product );
		if ( ! $copy_price ) {
			$this->clear_prices( $duplicate );
		}

		$new_product_id = $duplicate->save();
		if ( ! $new_product_id ) {
			return new \WP_Error( 'cdup_product_save_failed', __( 'The product could not be duplicated.', 'chada-duplicate' ) );
		}

		$this->clone_variations( $product, $new_product_id, $copy_price );

		/** This action is documented in includes/Duplicator.php */
		do_action( 'cdup_post_cloned', $new_product_id, get_post( $source_post_id ) );

		/**
		 * Fires after a product (and its variations) has been cloned.
		 *
		 * @param int         $new_product_id The new draft product.
		 * @param \WC_Product $product        The product that was duplicated.
		 */
		do_action( 'cdup_product_cloned', $new_product_id, $product );

		return $new_product_id;
	}

	/**
	 * Clone each variation (child post) onto the new product. Grouped/linked
	 * children are skipped — only real variation posts are recreated.
	 *
	 * @param \WC_Product $source_product Source product.
	 * @param int         $new_product_id New product ID.
	 * @param bool        $copy_price     Whether to keep prices.
	 * @return void
	 */
	private function clone_variations( $source_product, $new_product_id, $copy_price ) {
		foreach ( $source_product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation instanceof \WC_Product_Variation ) {
				continue;
			}

			$new_variation = clone $variation;
			$new_variation->set_id( 0 );
			$new_variation->set_parent_id( $new_product_id );
			$new_variation->set_date_created( null );
			$this->handle_sku( $new_variation, $variation );
			if ( ! $copy_price ) {
				$this->clear_prices( $new_variation );
			}

			$new_variation->save();
		}
	}

	/**
	 * Give the clone a unique SKU (suffixed) so WooCommerce's uniqueness check
	 * doesn't reject the save. Products with no SKU stay blank.
	 *
	 * @param \WC_Product $duplicate Clone being prepared.
	 * @param \WC_Product $source    Source product/variation.
	 * @return void
	 */
	private function handle_sku( $duplicate, $source ) {
		$source_sku = $source->get_sku( 'edit' );
		if ( '' === $source_sku ) {
			return;
		}
		$duplicate->set_sku( wc_product_generate_unique_sku( 0, $source_sku ) );
	}

	/**
	 * Blank out prices on the clone (when price-copy is disabled).
	 *
	 * @param \WC_Product $duplicate Clone being prepared.
	 * @return void
	 */
	private function clear_prices( $duplicate ) {
		$duplicate->set_regular_price( '' );
		$duplicate->set_sale_price( '' );
		$duplicate->set_price( '' );
	}

	/**
	 * Whether to copy prices onto the clone. Defaults to true; the Settings phase
	 * wires this filter to the configurable copy/skip option.
	 *
	 * @return bool
	 */
	private function should_copy_price() {
		/**
		 * Filter whether cloned products keep their prices.
		 *
		 * @param bool $copy_price Default true.
		 */
		return (bool) apply_filters( 'cdup_product_copy_price', true );
	}
}
