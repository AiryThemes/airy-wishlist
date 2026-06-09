<?php
/**
 * Frontend Handler - Displays buttons and loads assets
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend display and asset management class
 */
class Airy_Wishlist_Frontend {

	/**
	 * Single instance of the class
	 *
	 * @var Airy_Wishlist_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Airy_Wishlist_Frontend
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize frontend hooks
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add to wishlist button hooks.
		$this->add_button_hooks();
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_scripts() {
		// CSS.
		wp_enqueue_style(
			'airy-wishlist',
			AIRY_WISHLIST_URL . 'assets/css/airy-wishlist.css',
			array(),
			AIRY_WISHLIST_VERSION
		);

		// Add dynamic button styles.
		$this->add_dynamic_button_styles();

		// JavaScript.
		wp_enqueue_script(
			'airy-wishlist',
			AIRY_WISHLIST_URL . 'assets/js/airy-wishlist.js',
			array(),
			AIRY_WISHLIST_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'airy-wishlist',
			'airyWishlistData',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'airy_wishlist_nonce' ),
				'enableAjax'       => get_option( 'airy_wishlist_enable_ajax', 'yes' ),
				'redirectAfterAdd' => get_option( 'airy_wishlist_redirect_after_add', 'no' ),
				'wishlistUrl'      => airy_wishlist_get_url(),
				'multipleEnabled'  => get_option( 'airy_wishlist_multiple_enabled', 'no' ),
				'buttonToggle'     => get_option( 'airy_wishlist_button_toggle', 'yes' ),
				'addedMessage'     => get_option( 'airy_wishlist_product_added_message', __( 'Product added to wishlist!', 'airy-wishlist' ) ),
				'removedMessage'   => get_option( 'airy_wishlist_product_removed_message', __( 'Product removed from wishlist.', 'airy-wishlist' ) ),
				'i18n'             => array(
					'addText'        => get_option( 'airy_wishlist_add_button_text', __( 'Add to Wishlist', 'airy-wishlist' ) ),
					'addedText'      => get_option( 'airy_wishlist_added_button_text', __( 'Added to Wishlist', 'airy-wishlist' ) ),
					'selectOptions'  => __( 'Please select product options before adding to wishlist.', 'airy-wishlist' ),
					'allAddedToCart' => __( 'All products added to cart!', 'airy-wishlist' ),
					'someFailed'     => __( 'Some products could not be added to cart.', 'airy-wishlist' ),
					'genericError'   => __( 'An error occurred. Please try again.', 'airy-wishlist' ),
					'chooseList'     => __( 'Add to which list?', 'airy-wishlist' ),
					'newListName'    => __( 'New list name', 'airy-wishlist' ),
					'create'         => __( 'Create', 'airy-wishlist' ),
					'createNewList'  => __( '+ Create new list', 'airy-wishlist' ),
					'renamePrompt'   => __( 'Enter a new name for this list:', 'airy-wishlist' ),
					'newListPrompt'  => __( 'Name your new list:', 'airy-wishlist' ),
					'deleteConfirm'  => __( 'Delete this list and all its items? This cannot be undone.', 'airy-wishlist' ),
				),
			)
		);
	}

	/**
	 * Add dynamic button styles using wp_add_inline_style
	 */
	private function add_dynamic_button_styles() {
		$bg_color   = get_option( 'airy_wishlist_button_bg_color', '#ffffff' );
		$text_color = get_option( 'airy_wishlist_button_text_color', '#333333' );
		$bg_hover   = get_option( 'airy_wishlist_button_bg_color_hover', '#f8f8f8' );
		$text_hover = get_option( 'airy_wishlist_button_text_color_hover', '#000000' );
		$added_bg   = get_option( 'airy_wishlist_button_added_bg_color', '#e74c3c' );
		$added_text = get_option( 'airy_wishlist_button_added_text_color', '#ffffff' );

		$custom_css = sprintf(
			'.airy-add-to-wishlist .airy-wishlist-btn { background-color: %1$s; color: %2$s; }
            .airy-add-to-wishlist .airy-wishlist-btn:hover { background-color: %3$s; color: %4$s; }
            .airy-add-to-wishlist .airy-wishlist-btn.added { background-color: %5$s; color: %6$s; }',
			esc_attr( $bg_color ),
			esc_attr( $text_color ),
			esc_attr( $bg_hover ),
			esc_attr( $text_hover ),
			esc_attr( $added_bg ),
			esc_attr( $added_text )
		);

		wp_add_inline_style( 'airy-wishlist', $custom_css );
	}

	/**
	 * Add button hooks based on settings
	 */
	private function add_button_hooks() {
		// Single product page - Multiple hooks to ensure it shows.
		$position = get_option( 'airy_wishlist_button_position', 'after_add_to_cart' );

		switch ( $position ) {
			case 'before_add_to_cart':
				add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_to_wishlist_button' ) );
				add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'add_to_wishlist_button' ) );
				break;
			case 'after_add_to_cart':
				add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_wishlist_button' ) );
				add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'add_to_wishlist_button' ) );
				break;
			case 'after_summary':
				add_action( 'woocommerce_after_single_product_summary', array( $this, 'add_to_wishlist_button' ), 5 );
				break;
		}

		// Additional hook for out of stock products (priority 30 to run after stock check).
		add_action( 'woocommerce_single_product_summary', array( $this, 'add_to_wishlist_button_fallback' ), 35 );

		// Product loop (shop, category pages).
		if ( 'yes' === get_option( 'airy_wishlist_show_on_loop', 'yes' ) ) {
			$loop_position = get_option( 'airy_wishlist_loop_position', 'after_add_to_cart' );

			switch ( $loop_position ) {
				case 'before_add_to_cart':
					add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_to_wishlist_button_loop' ), 5 );
					break;
				case 'after_add_to_cart':
					add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_to_wishlist_button_loop' ), 15 );
					break;
			}
		}
	}

	/**
	 * Display add to wishlist button
	 *
	 * @param int $product_id Product ID (optional, uses global $product if not provided).
	 */
	public function add_to_wishlist_button( $product_id = 0 ) {
		if ( ! $product_id ) {
			global $product;
			if ( ! $product ) {
				return;
			}
			$product_id = $product->get_id();
		}

		// Prevent duplicate buttons.
		static $shown = array();
		if ( isset( $shown[ $product_id ] ) ) {
			return;
		}
		$shown[ $product_id ] = true;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped via custom airy_wishlist_kses_button() function
		echo airy_wishlist_kses_button( airy_wishlist_get_button_html( $product_id ) );
	}

	/**
	 * Fallback button for out of stock or special cases
	 */
	public function add_to_wishlist_button_fallback() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$product_id = $product->get_id();

		// Check if button already shown.
		static $shown = array();
		if ( isset( $shown[ $product_id ] ) ) {
			return;
		}

		// Only show if product is out of stock or other add to cart buttons not showing.
		if ( ! $product->is_in_stock() || ! $product->is_purchasable() ) {
			$shown[ $product_id ] = true;
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped via custom airy_wishlist_kses_button() function
			echo airy_wishlist_kses_button( airy_wishlist_get_button_html( $product_id ) );
		}
	}

	/**
	 * Display add to wishlist button in loop
	 */
	public function add_to_wishlist_button_loop() {
		global $product;
		if ( ! $product ) {
			return;
		}

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped via custom airy_wishlist_kses_button() function
		echo airy_wishlist_kses_button( airy_wishlist_get_button_html( $product->get_id(), 'loop' ) );
	}
}
