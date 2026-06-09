<?php
/**
 * Wishlist Counter Widget
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided widget wrapper markup.
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided title markup, title passed through core filter.
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		echo do_shortcode( '[airy_wishlist_counter]' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided widget wrapper markup.
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
