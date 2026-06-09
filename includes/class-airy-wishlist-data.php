<?php
/**
 * Data Handler - Manages wishlist data and user sessions
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wishlist data management class
 */
class Airy_Wishlist_Data {

	/**
	 * Single instance of the class
	 *
	 * @var Airy_Wishlist_Data|null
	 */
	private static $instance = null;

	/**
	 * Current session ID
	 *
	 * @var string|null
	 */
	private $session_id = null;

	/**
	 * Current wishlist object
	 *
	 * @var object|null
	 */
	private $wishlist = null;

	/**
	 * Get singleton instance
	 *
	 * @return Airy_Wishlist_Data
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize hooks
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init_session' ) );
		add_action( 'wp_login', array( $this, 'on_user_login' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'handle_non_ajax_requests' ) );
	}

	/**
	 * Handle non-AJAX requests (when AJAX is disabled)
	 */
	public function handle_non_ajax_requests() {
		// Add to wishlist.
		if ( isset( $_POST['airy_add_to_wishlist'] ) ) {
			// Verify nonce for security.
			if ( ! isset( $_POST['airy_wishlist_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['airy_wishlist_nonce'] ) ), 'airy_wishlist_nonce' ) ) {
				return;
			}

			$product_id   = absint( $_POST['airy_add_to_wishlist'] );
			$variation_id = isset( $_POST['airy_variation_id'] ) ? absint( $_POST['airy_variation_id'] ) : 0;

			if ( $product_id ) {
				$this->add_to_wishlist( $product_id, $variation_id );

				if ( 'yes' === get_option( 'airy_wishlist_redirect_after_add', 'no' ) ) {
					wp_safe_redirect( $this->get_wishlist_url() );
					exit;
				}
			}
		}

		// Remove from wishlist (POST with nonce verification).
		if ( isset( $_POST['airy_remove_product'] ) && isset( $_POST['airy_remove_nonce'] ) ) {
			$product_id   = absint( $_POST['airy_remove_product'] );
			$variation_id = isset( $_POST['airy_remove_variation'] ) ? absint( $_POST['airy_remove_variation'] ) : 0;

			// Verify nonce for CSRF protection.
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['airy_remove_nonce'] ) ), 'airy_remove_' . $product_id . '_' . $variation_id ) ) {
				if ( $product_id ) {
					$this->remove_from_wishlist( $product_id, $variation_id );
					wp_safe_redirect( remove_query_arg( array( 'airy_remove_product', 'airy_remove_variation', 'airy_remove_nonce' ) ) );
					exit;
				}
			}
		}
	}

	/**
	 * Initialize session
	 */
	public function init_session() {
		if ( is_user_logged_in() ) {
			$this->session_id = 'user_' . get_current_user_id();
		} else {
			$this->session_id = $this->get_or_create_guest_session();
		}
	}

	/**
	 * Get or create guest session
	 *
	 * @return string Session ID.
	 */
	private function get_or_create_guest_session() {
		$cookie_name = 'airy_wishlist_session';

		if ( isset( $_COOKIE[ $cookie_name ] ) && ! empty( $_COOKIE[ $cookie_name ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		}

		// Create new session.
		$session_id = 'guest_' . wp_generate_password( 32, false );

		// Set cookie (30 days default).
		$expiry      = get_option( 'airy_wishlist_cookie_expiry', 30 );
		$expiry_time = time() + ( DAY_IN_SECONDS * absint( $expiry ) );

		setcookie( $cookie_name, $session_id, $expiry_time, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ $cookie_name ] = $session_id;

		return $session_id;
	}

	/**
	 * Get current wishlist
	 */
	public function get_wishlist() {
		if ( ! is_null( $this->wishlist ) ) {
			return $this->wishlist;
		}

		$db = Airy_Wishlist_Database::instance();

		if ( is_user_logged_in() ) {
			$this->wishlist = $db->get_or_create_wishlist( get_current_user_id() );
		} else {
			$this->wishlist = $db->get_or_create_wishlist( 0, $this->session_id );
		}

		return $this->wishlist;
	}

	/**
	 * Add product to wishlist
	 *
	 * @param int $product_id Product ID to add.
	 * @param int $variation_id Optional variation ID.
	 * @return bool True if added successfully.
	 */
	public function add_to_wishlist( $product_id, $variation_id = 0 ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( ! $product_id ) {
			return false;
		}

		// Verify product exists.
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$wishlist = $this->get_wishlist();
		if ( ! $wishlist ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->add_item( $wishlist->id, $product_id, $variation_id );
	}

	/**
	 * Remove product from wishlist
	 *
	 * @param int $product_id Product ID to remove.
	 * @param int $variation_id Optional variation ID.
	 * @return bool True if removed successfully.
	 */
	public function remove_from_wishlist( $product_id, $variation_id = 0 ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		$wishlist = $this->get_wishlist();
		if ( ! $wishlist ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->remove_item( $wishlist->id, $product_id, $variation_id );
	}

	/**
	 * Get wishlist items
	 */
	public function get_items() {
		$wishlist = $this->get_wishlist();
		if ( ! $wishlist ) {
			return array();
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->get_wishlist_items( $wishlist->id );
	}

	/**
	 * Get wishlist item count
	 */
	public function get_count() {
		$wishlist = $this->get_wishlist();
		if ( ! $wishlist ) {
			return 0;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->get_item_count( $wishlist->id );
	}

	/**
	 * Check if product is in wishlist
	 *
	 * @param int $product_id Product ID to check.
	 * @param int $variation_id Optional variation ID.
	 * @return bool True if in wishlist.
	 */
	public function is_in_wishlist( $product_id, $variation_id = 0 ) {
		$wishlist = $this->get_wishlist();
		if ( ! $wishlist ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->is_product_in_wishlist( $wishlist->id, $product_id, $variation_id );
	}

	/**
	 * On user login - merge guest wishlist
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user User object.
	 */
	public function on_user_login( $user_login, $user ) {
		if ( isset( $_COOKIE['airy_wishlist_session'] ) ) {
			$session_id = sanitize_text_field( wp_unslash( $_COOKIE['airy_wishlist_session'] ) );

			if ( strpos( $session_id, 'guest_' ) === 0 ) {
				$db = Airy_Wishlist_Database::instance();
				$db->merge_wishlists( $user->ID, $session_id );
			}
		}
	}

	/**
	 * Get wishlist URL
	 */
	public function get_wishlist_url() {
		$page_id = get_option( 'airy_wishlist_page_id' );

		if ( $page_id && get_post( $page_id ) ) {
			return get_permalink( $page_id );
		}

		return home_url( '/my-wishlist/' );
	}

	/**
	 * Clear wishlist
	 */
	public function clear_wishlist() {
		$wishlist = $this->get_wishlist();
		if ( ! $wishlist ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->clear_wishlist( $wishlist->id );
	}
}
