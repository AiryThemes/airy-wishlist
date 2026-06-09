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

/**
 * Wishlist Counter Widget (Classic + Block Editor Compatible)
 */
class Airy_Wishlist_Counter_Widget extends WP_Widget {

	/**
	 * Constructor - Set up widget
	 */
	public function __construct() {
		parent::__construct(
			'airy_wishlist_counter',
			__( 'Airy Wishlist Counter', 'airy-wishlist' ),
			array(
				'description'           => __( 'Display wishlist counter with item count.', 'airy-wishlist' ),
				'show_instance_in_rest' => true, // Enable for block editor.
			)
		);
	}

	/**
	 * Display widget content
	 *
	 * @param array $args Display arguments.
	 * @param array $instance Widget instance settings.
	 */
	public function widget( $args, $instance ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		echo do_shortcode( '[airy_wishlist_counter]' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget'];
	}

	/**
	 * Display widget settings form
	 *
	 * @param array $instance Current widget instance settings.
	 * @return void
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'airy-wishlist' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p class="description">
			<?php esc_html_e( 'This widget is compatible with both Classic Widgets and Block-based Widget Editor (Gutenberg).', 'airy-wishlist' ); ?>
		</p>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param array $new_instance New widget instance settings.
	 * @param array $old_instance Old widget instance settings.
	 * @return array Updated widget instance settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}