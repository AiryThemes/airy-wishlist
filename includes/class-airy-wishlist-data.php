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
			$wishlist_id  = isset( $_POST['airy_remove_wishlist'] ) ? absint( $_POST['airy_remove_wishlist'] ) : 0;

			// Verify nonce for CSRF protection.
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['airy_remove_nonce'] ) ), 'airy_remove_' . $product_id . '_' . $variation_id ) ) {
				if ( $product_id ) {
					$this->remove_from_wishlist( $product_id, $variation_id, $wishlist_id );
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
	 * Whether guest (logged-out) wishlists are enabled.
	 *
	 * @return bool
	 */
	public function guests_enabled() {
		return 'yes' === get_option( 'airy_wishlist_guest_enabled', 'yes' );
	}

	/**
	 * Whether the current visitor may use the wishlist at all.
	 *
	 * Logged-in users always may; guests only if guest wishlists are enabled.
	 *
	 * @return bool
	 */
	public function is_enabled_for_visitor() {
		return is_user_logged_in() || $this->guests_enabled();
	}

	/**
	 * Get current wishlist
	 */
	public function get_wishlist() {
		if ( ! is_null( $this->wishlist ) ) {
			return $this->wishlist;
		}

		// Guests can only use the wishlist when guest wishlists are enabled.
		if ( ! $this->is_enabled_for_visitor() ) {
			return false;
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
	 * @param int $wishlist_id Optional target wishlist ID (defaults to the user's default list).
	 * @return bool True if added successfully.
	 */
	public function add_to_wishlist( $product_id, $variation_id = 0, $wishlist_id = 0 ) {
		if ( ! $this->is_enabled_for_visitor() ) {
			return false;
		}

		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$wishlist_id  = absint( $wishlist_id );

		if ( ! $product_id ) {
			return false;
		}

		// Verify product exists.
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		// Determine the target wishlist: an explicit (owned) one, or the default.
		if ( $wishlist_id && $this->owns_wishlist( $wishlist_id ) ) {
			$target_id = $wishlist_id;
		} else {
			$wishlist = $this->get_wishlist();
			if ( ! $wishlist ) {
				return false;
			}
			$target_id = $wishlist->id;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->add_item( $target_id, $product_id, $variation_id );
	}

	/**
	 * Remove product from wishlist
	 *
	 * @param int $product_id Product ID to remove.
	 * @param int $variation_id Optional variation ID.
	 * @param int $wishlist_id Optional wishlist ID to remove from (defaults to the user's default list).
	 * @return bool True if removed successfully.
	 */
	public function remove_from_wishlist( $product_id, $variation_id = 0, $wishlist_id = 0 ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$wishlist_id  = absint( $wishlist_id );

		// Remove from the specified (owned) list, otherwise the default list.
		if ( $wishlist_id && $this->owns_wishlist( $wishlist_id ) ) {
			$target_id = $wishlist_id;
		} else {
			$wishlist = $this->get_wishlist();
			if ( ! $wishlist ) {
				return false;
			}
			$target_id = $wishlist->id;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->remove_item( $target_id, $product_id, $variation_id );
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
	 * Whether the multiple-wishlists feature is enabled.
	 *
	 * @return bool
	 */
	public function is_multiple_enabled() {
		return 'yes' === get_option( 'airy_wishlist_multiple_enabled', 'no' );
	}

	/**
	 * Maximum number of wishlists a single owner may create.
	 *
	 * @return int
	 */
	public function get_max_wishlists() {
		return (int) apply_filters( 'airy_wishlist_max_wishlists', 20 );
	}

	/**
	 * Get all wishlists owned by the current user/guest.
	 *
	 * @return array Array of wishlist row objects.
	 */
	public function get_wishlists() {
		if ( ! $this->is_enabled_for_visitor() ) {
			return array();
		}

		// Make sure a default wishlist exists.
		$this->get_wishlist();

		$db = Airy_Wishlist_Database::instance();

		if ( is_user_logged_in() ) {
			return $db->get_user_wishlists( get_current_user_id() );
		}

		return $db->get_user_wishlists( 0, $this->session_id );
	}

	/**
	 * Check whether the current user/guest owns a given wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return bool
	 */
	public function owns_wishlist( $wishlist_id ) {
		$wishlist_id = absint( $wishlist_id );
		if ( ! $wishlist_id || ! $this->is_enabled_for_visitor() ) {
			return false;
		}

		$db       = Airy_Wishlist_Database::instance();
		$wishlist = $db->get_wishlist_by_id( $wishlist_id );

		if ( ! $wishlist ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			return get_current_user_id() === (int) $wishlist->user_id;
		}

		return ! empty( $this->session_id ) && $wishlist->session_id === $this->session_id;
	}

	/**
	 * Create a new wishlist for the current user/guest.
	 *
	 * @param string $name Wishlist name.
	 * @return int|false New wishlist ID, or false on failure / limit reached.
	 */
	public function create_wishlist( $name ) {
		if ( ! $this->is_enabled_for_visitor() ) {
			return false;
		}

		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			$name = __( 'My Wishlist', 'airy-wishlist' );
		}

		// Ensure the default wishlist exists and enforce the per-owner limit.
		$existing = $this->get_wishlists();
		if ( count( $existing ) >= $this->get_max_wishlists() ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();

		if ( is_user_logged_in() ) {
			$new_id = $db->create_wishlist( get_current_user_id(), '', $name );
		} else {
			$new_id = $db->create_wishlist( 0, $this->session_id, $name );
		}

		if ( $new_id ) {
			do_action( 'airy_wishlist_created', $new_id, $name );
		}

		return $new_id ? $new_id : false;
	}

	/**
	 * Rename a wishlist owned by the current user/guest.
	 *
	 * @param int    $wishlist_id Wishlist ID.
	 * @param string $name        New name.
	 * @return bool
	 */
	public function rename_wishlist( $wishlist_id, $name ) {
		if ( ! $this->owns_wishlist( $wishlist_id ) ) {
			return false;
		}

		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->rename_wishlist( absint( $wishlist_id ), $name );
	}

	/**
	 * Delete a wishlist owned by the current user/guest.
	 *
	 * The default wishlist cannot be deleted.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return bool
	 */
	public function delete_wishlist( $wishlist_id ) {
		if ( ! $this->owns_wishlist( $wishlist_id ) ) {
			return false;
		}

		$db       = Airy_Wishlist_Database::instance();
		$wishlist = $db->get_wishlist_by_id( $wishlist_id );

		if ( ! $wishlist || $wishlist->is_default ) {
			return false;
		}

		return $db->delete_wishlist( absint( $wishlist_id ) );
	}

	/**
	 * Get items for a specific owned wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return array
	 */
	public function get_items_for( $wishlist_id ) {
		if ( ! $this->owns_wishlist( $wishlist_id ) ) {
			return array();
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->get_wishlist_items( absint( $wishlist_id ) );
	}

	/**
	 * Get total item count across all of the current owner's wishlists.
	 *
	 * @return int
	 */
	public function get_total_count() {
		if ( ! $this->is_enabled_for_visitor() ) {
			return 0;
		}

		$db = Airy_Wishlist_Database::instance();

		if ( is_user_logged_in() ) {
			return $db->get_total_count_for( get_current_user_id() );
		}

		return $db->get_total_count_for( 0, $this->session_id );
	}

	/**
	 * Enable or disable stock/price notifications for an owned wishlist.
	 *
	 * Notifications are only available to logged-in users.
	 *
	 * @param int  $wishlist_id Wishlist ID.
	 * @param bool $enabled     Whether to enable.
	 * @return bool
	 */
	public function set_notifications( $wishlist_id, $enabled ) {
		if ( ! is_user_logged_in() || ! $this->owns_wishlist( $wishlist_id ) ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();
		return $db->set_wishlist_notifications( absint( $wishlist_id ), (bool) $enabled );
	}

	/**
	 * Get a public, shareable URL for the current wishlist.
	 *
	 * Generates a share token on demand and appends it to the wishlist page URL.
	 *
	 * @return string Shareable URL, or empty string if no wishlist exists.
	 */
	public function get_share_url() {
		$wishlist = $this->get_wishlist();
		if ( ! $wishlist ) {
			return '';
		}

		$db    = Airy_Wishlist_Database::instance();
		$token = $db->ensure_share_token( $wishlist->id );

		return add_query_arg( 'airy_wid', rawurlencode( $token ), $this->get_wishlist_url() );
	}

	/**
	 * Get a shareable URL for a specific owned wishlist.
	 *
	 * Falls back to the default wishlist URL if the ID is not owned.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return string
	 */
	public function get_share_url_for( $wishlist_id ) {
		$wishlist_id = absint( $wishlist_id );

		if ( ! $wishlist_id || ! $this->owns_wishlist( $wishlist_id ) ) {
			return $this->get_share_url();
		}

		$db    = Airy_Wishlist_Database::instance();
		$token = $db->ensure_share_token( $wishlist_id );

		return add_query_arg( 'airy_wid', rawurlencode( $token ), $this->get_wishlist_url() );
	}

	/**
	 * Move a product from one owned wishlist to another.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @param int $from_id      Source wishlist ID.
	 * @param int $to_id        Target wishlist ID.
	 * @return bool
	 */
	public function move_item( $product_id, $variation_id, $from_id, $to_id ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$from_id      = absint( $from_id );
		$to_id        = absint( $to_id );

		if ( ! $product_id || ! $from_id || ! $to_id || $from_id === $to_id ) {
			return false;
		}

		if ( ! $this->owns_wishlist( $from_id ) || ! $this->owns_wishlist( $to_id ) ) {
			return false;
		}

		$db = Airy_Wishlist_Database::instance();

		// Ensure it exists in the target (no-op if already there), then drop from source.
		$db->add_item( $to_id, $product_id, $variation_id );
		$db->remove_item( $from_id, $product_id, $variation_id );

		do_action( 'airy_wishlist_item_moved', $product_id, $variation_id, $from_id, $to_id );

		return true;
	}

	/**
	 * Get a shared wishlist (and its items) by public token.
	 *
	 * @param string $token Share token.
	 * @return array{ wishlist: object, items: array }|null Null if token invalid.
	 */
	public function get_shared_wishlist( $token ) {
		$db       = Airy_Wishlist_Database::instance();
		$wishlist = $db->get_wishlist_by_token( $token );

		if ( ! $wishlist ) {
			return null;
		}

		return array(
			'wishlist' => $wishlist,
			'items'    => $db->get_wishlist_items( $wishlist->id ),
		);
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
