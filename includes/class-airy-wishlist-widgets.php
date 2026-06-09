<?php
/**
 * Widgets Handler
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget and Gutenberg block registration class
 */
class Airy_Wishlist_Widgets {

	/**
	 * Single instance of the class
	 *
	 * @var Airy_Wishlist_Widgets|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Airy_Wishlist_Widgets
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize widget and block hooks
	 */
	private function __construct() {
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Register widgets
	 */
	public function register_widgets() {
		register_widget( 'Airy_Wishlist_Counter_Widget' );
	}

	/**
	 * Enqueue block editor assets
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'airy-wishlist-block',
			AIRY_WISHLIST_URL . 'assets/js/wishlist-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ),
			AIRY_WISHLIST_VERSION,
			true
		);
	}

	/**
	 * Register Gutenberg block widget
	 */
	public function register_block() {
		// Register block for Gutenberg widget areas and content editor.
		if ( function_exists( 'register_block_type' ) ) {
			register_block_type(
				'airy-wishlist/counter',
				array(
					'render_callback' => array( $this, 'render_counter_block' ),
					'attributes'      => array(
						'showText' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'icon'     => array(
							'type'    => 'string',
							'default' => 'heart',
						),
					),
				)
			);
		}
	}

	/**
	 * Render counter block for Gutenberg
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_counter_block( $attributes ) {
		$show_text = isset( $attributes['showText'] ) && $attributes['showText'] ? 'yes' : 'no';
		$icon      = isset( $attributes['icon'] ) ? $attributes['icon'] : 'heart';

		return do_shortcode( '[airy_wishlist_counter show_text="' . $show_text . '" icon="' . $icon . '"]' );
	}
}
