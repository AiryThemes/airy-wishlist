<?php
/**
 * WooCommerce My Account integration.
 *
 * Adds a "Wishlist" tab to the My Account navigation and renders the
 * customer's wishlist at /my-account/wishlist/.
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * My Account endpoint handler.
 */
class Airy_Wishlist_My_Account {

	/**
	 * The My Account endpoint slug.
	 */
	const ENDPOINT = 'wishlist';

	/**
	 * Option flag used to flush rewrite rules once after the endpoint is added.
	 */
	const FLUSH_FLAG = 'airy_wishlist_myaccount_flushed';

	/**
	 * Single instance of the class.
	 *
	 * @var Airy_Wishlist_My_Account|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Airy_Wishlist_My_Account
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - register hooks.
	 */
	private function __construct() {
		// Register the endpoint with WooCommerce (WC adds the rewrite rule for it).
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );

		// Also register the rewrite endpoint directly for robustness.
		add_action( 'init', array( $this, 'add_endpoint' ) );

		// Add the menu item and render its content.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'endpoint_content' ) );

		// Endpoint page/title.
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( $this, 'endpoint_title' ) );

		// Flush rewrite rules once so the endpoint works after a plugin update.
		add_action( 'init', array( $this, 'maybe_flush_rules' ), 99 );
	}

	/**
	 * Whether the My Account wishlist menu item is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		return 'yes' === get_option( 'airy_wishlist_myaccount_enabled', 'yes' );
	}

	/**
	 * Register the endpoint query var with WooCommerce.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Register the rewrite endpoint.
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Flush rewrite rules a single time after the endpoint is introduced.
	 */
	public function maybe_flush_rules() {
		if ( get_option( self::FLUSH_FLAG ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::FLUSH_FLAG, 1, false );
	}

	/**
	 * Add the "Wishlist" item to the My Account menu (before Logout).
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function add_menu_item( $items ) {
		if ( ! $this->is_enabled() ) {
			return $items;
		}

		$label = __( 'Wishlist', 'airy-wishlist' );
		$new   = array();

		foreach ( $items as $key => $value ) {
			if ( 'customer-logout' === $key ) {
				$new[ self::ENDPOINT ] = $label;
			}
			$new[ $key ] = $value;
		}

		// Fallback if there was no logout item to anchor before.
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = $label;
		}

		return $new;
	}

	/**
	 * Endpoint title (used for the page heading and document title).
	 *
	 * @return string
	 */
	public function endpoint_title() {
		return __( 'Wishlist', 'airy-wishlist' );
	}

	/**
	 * Render the wishlist inside the My Account endpoint.
	 */
	public function endpoint_content() {
		// The shortcode output is escaped internally via airy_wishlist_kses_* helpers.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_shortcode output is escaped within the shortcode.
		echo do_shortcode( '[airy_wishlist]' );
	}
}
