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

		// Check if a product/variation is in the wishlist.
		add_action( 'wp_ajax_airy_check_in_wishlist', array( $this, 'check_in_wishlist' ) );
		add_action( 'wp_ajax_nopriv_airy_check_in_wishlist', array( $this, 'check_in_wishlist' ) );

		// Multiple wishlists: list, create, rename, delete.
		add_action( 'wp_ajax_airy_get_wishlists', array( $this, 'get_wishlists' ) );
		add_action( 'wp_ajax_nopriv_airy_get_wishlists', array( $this, 'get_wishlists' ) );
		add_action( 'wp_ajax_airy_create_wishlist', array( $this, 'create_wishlist' ) );
		add_action( 'wp_ajax_nopriv_airy_create_wishlist', array( $this, 'create_wishlist' ) );
		add_action( 'wp_ajax_airy_rename_wishlist', array( $this, 'rename_wishlist' ) );
		add_action( 'wp_ajax_nopriv_airy_rename_wishlist', array( $this, 'rename_wishlist' ) );
		add_action( 'wp_ajax_airy_delete_wishlist', array( $this, 'delete_wishlist' ) );
		add_action( 'wp_ajax_nopriv_airy_delete_wishlist', array( $this, 'delete_wishlist' ) );

		// Move an item between wishlists.
		add_action( 'wp_ajax_airy_move_item', array( $this, 'move_item' ) );
		add_action( 'wp_ajax_nopriv_airy_move_item', array( $this, 'move_item' ) );
	}

	/**
	 * Move a product from one wishlist to another.
	 */
	public function move_item() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$from_id      = isset( $_POST['from_id'] ) ? absint( $_POST['from_id'] ) : 0;
		$to_id        = isset( $_POST['to_id'] ) ? absint( $_POST['to_id'] ) : 0;

		if ( ! $product_id || ! $to_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'airy-wishlist' ) ) );
		}

		$data = Airy_Wishlist_Data::instance();

		if ( $data->move_item( $product_id, $variation_id, $from_id, $to_id ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Product moved.', 'airy-wishlist' ),
					'count'   => $data->is_multiple_enabled() ? $data->get_total_count() : $data->get_count(),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'Could not move product.', 'airy-wishlist' ) ) );
	}

	/**
	 * Return the current owner's wishlists.
	 */
	public function get_wishlists() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$data      = Airy_Wishlist_Data::instance();
		$db        = Airy_Wishlist_Database::instance();
		$wishlists = array();

		foreach ( $data->get_wishlists() as $wishlist ) {
			$wishlists[] = array(
				'id'         => (int) $wishlist->id,
				'name'       => $wishlist->wishlist_name,
				'is_default' => (int) $wishlist->is_default,
				'count'      => $db->get_item_count( $wishlist->id ),
			);
		}

		wp_send_json_success( array( 'wishlists' => $wishlists ) );
	}

	/**
	 * Create a new wishlist.
	 */
	public function create_wishlist() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		if ( ! Airy_Wishlist_Data::instance()->is_enabled_for_visitor() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to create a wishlist.', 'airy-wishlist' ) ) );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		$data   = Airy_Wishlist_Data::instance();
		$new_id = $data->create_wishlist( $name );

		if ( $new_id ) {
			wp_send_json_success(
				array(
					'id'      => (int) $new_id,
					'message' => __( 'Wishlist created.', 'airy-wishlist' ),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => __( 'Could not create wishlist. You may have reached the maximum number of wishlists.', 'airy-wishlist' ),
			)
		);
	}

	/**
	 * Rename a wishlist.
	 */
	public function rename_wishlist() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$wishlist_id = isset( $_POST['wishlist_id'] ) ? absint( $_POST['wishlist_id'] ) : 0;
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		$data = Airy_Wishlist_Data::instance();

		if ( $data->rename_wishlist( $wishlist_id, $name ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Wishlist renamed.', 'airy-wishlist' ),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => __( 'Could not rename wishlist.', 'airy-wishlist' ),
			)
		);
	}

	/**
	 * Delete a wishlist.
	 */
	public function delete_wishlist() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		$wishlist_id = isset( $_POST['wishlist_id'] ) ? absint( $_POST['wishlist_id'] ) : 0;

		$data = Airy_Wishlist_Data::instance();

		if ( $data->delete_wishlist( $wishlist_id ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Wishlist deleted.', 'airy-wishlist' ),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => __( 'Could not delete wishlist. The default wishlist cannot be deleted.', 'airy-wishlist' ),
			)
		);
	}

	/**
	 * Check if a product (optionally a specific variation) is in the wishlist.
	 *
	 * Used by the frontend to keep variable-product buttons in the correct
	 * "added" state when the customer selects different variations.
	 */
	public function check_in_wishlist() {
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

		$data = Airy_Wishlist_Data::instance();

		wp_send_json_success(
			array(
				'in_wishlist' => $data->is_in_wishlist( $product_id, $variation_id ),
			)
		);
	}

	/**
	 * Add product to wishlist
	 */
	public function add_to_wishlist() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		if ( ! Airy_Wishlist_Data::instance()->is_enabled_for_visitor() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to use the wishlist.', 'airy-wishlist' ) ) );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$wishlist_id  = isset( $_POST['wishlist_id'] ) ? absint( $_POST['wishlist_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'airy-wishlist' ),
				)
			);
		}

		$data   = Airy_Wishlist_Data::instance();
		$result = $data->add_to_wishlist( $product_id, $variation_id, $wishlist_id );

		if ( $result ) {
			$wishlist_url = $data->get_wishlist_url();

			// With multiple wishlists, deep-link the redirect to the list the product was added to.
			if ( $data->is_multiple_enabled() ) {
				$target_id = ( $wishlist_id && $data->owns_wishlist( $wishlist_id ) ) ? $wishlist_id : 0;

				if ( ! $target_id ) {
					$default   = $data->get_wishlist();
					$target_id = $default ? (int) $default->id : 0;
				}

				if ( $target_id ) {
					$wishlist_url = add_query_arg( 'airy_list', $target_id, $wishlist_url );
				}
			}

			wp_send_json_success(
				array(
					'message'      => get_option( 'airy_wishlist_product_added_message', __( 'Product added to wishlist!', 'airy-wishlist' ) ),
					'count'        => $data->is_multiple_enabled() ? $data->get_total_count() : $data->get_count(),
					'wishlist_url' => $wishlist_url,
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
		$wishlist_id  = isset( $_POST['wishlist_id'] ) ? absint( $_POST['wishlist_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'airy-wishlist' ),
				)
			);
		}

		$data   = Airy_Wishlist_Data::instance();
		$result = $data->remove_from_wishlist( $product_id, $variation_id, $wishlist_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => get_option( 'airy_wishlist_product_removed_message', __( 'Product removed from wishlist.', 'airy-wishlist' ) ),
					'count'   => $data->is_multiple_enabled() ? $data->get_total_count() : $data->get_count(),
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
		$count = $data->is_multiple_enabled() ? $data->get_total_count() : $data->get_count();

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
		$wishlist_id  = isset( $_POST['wishlist_id'] ) ? absint( $_POST['wishlist_id'] ) : 0;

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
			// Record the wishlist-to-cart conversion.
			do_action( 'airy_wishlist_added_to_cart', $product_id, $variation_id );

			$data    = Airy_Wishlist_Data::instance();
			$removed = false;

			// Remove from wishlist only if the option is enabled.
			if ( 'yes' === get_option( 'airy_wishlist_remove_after_add_to_cart', 'no' ) ) {
				$removed = (bool) $data->remove_from_wishlist( $product_id, $variation_id, $wishlist_id );
			}

			wp_send_json_success(
				array(
					'message'  => __( 'Product added to cart.', 'airy-wishlist' ),
					'cart_url' => wc_get_cart_url(),
					'removed'  => $removed,
					'count'    => $data->is_multiple_enabled() ? $data->get_total_count() : $data->get_count(),
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
