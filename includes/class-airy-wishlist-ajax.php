<?php
/**
 * AJAX Handler
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX request handler class
 */
class Airy_Wishlist_Ajax {

	/**
	 * Single instance of the class
	 *
	 * @var Airy_Wishlist_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Airy_Wishlist_Ajax
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize AJAX hooks
	 */
	private function __construct() {
		// Add to wishlist.
		add_action( 'wp_ajax_airy_add_to_wishlist', array( $this, 'add_to_wishlist' ) );
		add_action( 'wp_ajax_nopriv_airy_add_to_wishlist', array( $this, 'add_to_wishlist' ) );

		// Remove from wishlist.
		add_action( 'wp_ajax_airy_remove_from_wishlist', array( $this, 'remove_from_wishlist' ) );
		add_action( 'wp_ajax_nopriv_airy_remove_from_wishlist', array( $this, 'remove_from_wishlist' ) );

		// Get wishlist count.
		add_action( 'wp_ajax_airy_get_wishlist_count', array( $this, 'get_wishlist_count' ) );
		add_action( 'wp_ajax_nopriv_airy_get_wishlist_count', array( $this, 'get_wishlist_count' ) );

		// Add to cart from wishlist.
		add_action( 'wp_ajax_airy_add_to_cart_from_wishlist', array( $this, 'add_to_cart_from_wishlist' ) );
		add_action( 'wp_ajax_nopriv_airy_add_to_cart_from_wishlist', array( $this, 'add_to_cart_from_wishlist' ) );
	}

	/**
	 * Add product to wishlist
	 */
	public function add_to_wishlist() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'airy-wishlist' ),
				)
			);
		}

		$data   = Airy_Wishlist_Data::instance();
		$result = $data->add_to_wishlist( $product_id, $variation_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'      => get_option( 'airy_wishlist_product_added_message', __( 'Product added to wishlist!', 'airy-wishlist' ) ),
					'count'        => $data->get_count(),
					'wishlist_url' => $data->get_wishlist_url(),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Product is already in wishlist.', 'airy-wishlist' ),
				)
			);
		}
	}

	/**
	 * Remove product from wishlist
	 */
	public function remove_from_wishlist() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'airy-wishlist' ),
				)
			);
		}

		$data   = Airy_Wishlist_Data::instance();
		$result = $data->remove_from_wishlist( $product_id, $variation_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => get_option( 'airy_wishlist_product_removed_message', __( 'Product removed from wishlist.', 'airy-wishlist' ) ),
					'count'   => $data->get_count(),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Could not remove product.', 'airy-wishlist' ),
				)
			);
		}
	}

	/**
	 * Get wishlist count
	 */
	public function get_wishlist_count() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$data  = Airy_Wishlist_Data::instance();
		$count = $data->get_count();

		wp_send_json_success(
			array(
				'count' => $count,
			)
		);
	}

	/**
	 * Add to cart from wishlist
	 */
	public function add_to_cart_from_wishlist() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'airy-wishlist' ),
				)
			);
		}

		// Add to cart.
		if ( $variation_id ) {
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
		} else {
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );
		}

		if ( $cart_item_key ) {
			// Remove from wishlist if option is enabled.
			if ( get_option( 'airy_wishlist_remove_after_add_to_cart', 'no' ) === 'yes' ) {
				$data = Airy_Wishlist_Data::instance();
				$data->remove_from_wishlist( $product_id, $variation_id );
			}

			wp_send_json_success(
				array(
					'message'  => __( 'Product added to cart.', 'airy-wishlist' ),
					'cart_url' => wc_get_cart_url(),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Could not add product to cart.', 'airy-wishlist' ),
				)
			);
		}
	}
}
