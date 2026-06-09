<?php
/**
 * Shortcodes Handler
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode registration and rendering class
 */
class Airy_Wishlist_Shortcodes {

	/**
	 * Single instance of the class
	 *
	 * @var Airy_Wishlist_Shortcodes|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Airy_Wishlist_Shortcodes
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Register shortcodes
	 */
	private function __construct() {
		add_shortcode( 'airy_wishlist', array( $this, 'wishlist_page' ) );
		add_shortcode( 'airy_wishlist_counter', array( $this, 'wishlist_counter' ) );
		add_shortcode( 'airy_add_to_wishlist', array( $this, 'add_to_wishlist_button' ) );
	}

	/**
	 * Wishlist page shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Wishlist HTML output.
	 */
	public function wishlist_page( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout'  => get_option( 'airy_wishlist_page_layout', 'table' ),
				'columns' => '3',
			),
			$atts
		);

		ob_start();

		$data  = Airy_Wishlist_Data::instance();
		$items = $data->get_items();

		do_action( 'airy_wishlist_before_table' );

		if ( empty( $items ) ) {
			$this->render_empty_wishlist();
		} elseif ( 'grid' === $atts['layout'] ) {
				$this->render_grid_layout( $items, $atts['columns'] );
		} else {
			$this->render_table_layout( $items );
		}

		do_action( 'airy_wishlist_after_table' );

		return ob_get_clean();
	}

	/**
	 * Render empty wishlist
	 */
	private function render_empty_wishlist() {
		$empty_text = get_option( 'airy_wishlist_empty_text', __( 'Your wishlist is empty.', 'airy-wishlist' ) );
		?>
		<div class="airy-wishlist-empty">
			<p><?php echo esc_html( $empty_text ); ?></p>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button">
				<?php esc_html_e( 'Continue Shopping', 'airy-wishlist' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render table layout
	 *
	 * @param array $items Array of wishlist item objects.
	 */
	private function render_table_layout( $items ) {
		$show_stock       = 'yes' === get_option( 'airy_wishlist_show_stock_status', 'yes' );
		$show_date        = 'yes' === get_option( 'airy_wishlist_show_date_added', 'yes' );
		$show_add_to_cart = 'yes' === get_option( 'airy_wishlist_show_add_to_cart', 'yes' );
		$show_remove      = 'yes' === get_option( 'airy_wishlist_show_remove_button', 'yes' );
		$show_add_all     = 'yes' === get_option( 'airy_wishlist_show_add_all_to_cart', 'yes' );
		?>
		<div class="airy-wishlist-wrapper">
			<?php if ( 'yes' === get_option( 'airy_wishlist_enable_share', 'yes' ) ) : ?>
				<div class="airy-wishlist-share">
					<?php $this->render_share_buttons(); ?>
				</div>
			<?php endif; ?>
			
			<table class="airy-wishlist-table">
				<thead>
					<tr>
						<?php if ( $show_remove ) : ?>
						<th class="airy-wishlist-remove"></th>
						<?php endif; ?>
						<th class="airy-wishlist-image"><?php esc_html_e( 'Product', 'airy-wishlist' ); ?></th>
						<th class="airy-wishlist-name"></th>
						<th class="airy-wishlist-price"><?php esc_html_e( 'Price', 'airy-wishlist' ); ?></th>
						<?php if ( $show_stock ) : ?>
						<th class="airy-wishlist-stock"><?php esc_html_e( 'Stock', 'airy-wishlist' ); ?></th>
						<?php endif; ?>
						<?php if ( $show_date ) : ?>
						<th class="airy-wishlist-date"><?php esc_html_e( 'Date Added', 'airy-wishlist' ); ?></th>
						<?php endif; ?>
						<?php if ( $show_add_to_cart ) : ?>
						<th class="airy-wishlist-cart"></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php $this->render_wishlist_item( $item, $show_stock, $show_date, $show_add_to_cart, $show_remove ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<?php if ( $show_add_all && count( $items ) > 1 ) : ?>
			<div class="airy-wishlist-actions">
				<button type="button" class="button airy-add-all-to-cart">
					<?php esc_html_e( 'Add All to Cart', 'airy-wishlist' ); ?>
				</button>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render wishlist item
	 *
	 * @param object $item Wishlist item object.
	 * @param bool   $show_stock Whether to show stock status.
	 * @param bool   $show_date Whether to show date added.
	 * @param bool   $show_add_to_cart Whether to show add to cart button.
	 * @param bool   $show_remove Whether to show remove button.
	 */
	private function render_wishlist_item( $item, $show_stock, $show_date, $show_add_to_cart, $show_remove ) {
		$product_id = $item->variation_id > 0 ? $item->variation_id : $item->product_id;
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		// Check if product type allows add to cart.
		$can_add_to_cart = $product->is_purchasable() && $product->is_in_stock() && ! $product->is_type( 'grouped' );
		?>
		<tr class="airy-wishlist-item" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>">
			<?php if ( $show_remove ) : ?>
			<td class="airy-wishlist-remove">
				<form method="post" class="airy-remove-form" style="display:inline;">
					<?php wp_nonce_field( 'airy_remove_' . $item->product_id . '_' . $item->variation_id, 'airy_remove_nonce' ); ?>
					<input type="hidden" name="airy_remove_product" value="<?php echo esc_attr( $item->product_id ); ?>">
					<input type="hidden" name="airy_remove_variation" value="<?php echo esc_attr( $item->variation_id ); ?>">
					<button type="submit" class="airy-remove-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>">
						&times;
					</button>
				</form>
			</td>
			<?php endif; ?>
			
			<td class="airy-wishlist-image">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'thumbnail' ) ); ?>
				</a>
			</td>
			
			<td class="airy-wishlist-name">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo wp_kses_post( $product->get_name() ); ?>
				</a>
				<?php
				// Show variation attributes if this is a variation.
				if ( $item->variation_id > 0 && $product->is_type( 'variation' ) ) {
					echo '<div class="airy-wishlist-variation">';
					echo wp_kses_post( wc_get_formatted_variation( $product, true ) );
					echo '</div>';
				}
				?>
			</td>
			
			<td class="airy-wishlist-price">
				<?php echo wp_kses_post( $product->get_price_html() ); ?>
			</td>
			
			<?php if ( $show_stock ) : ?>
			<td class="airy-wishlist-stock">
				<?php
				if ( $product->is_in_stock() ) {
					echo '<span class="in-stock">' . esc_html__( 'In Stock', 'airy-wishlist' ) . '</span>';
				} else {
					echo '<span class="out-of-stock">' . esc_html__( 'Out of Stock', 'airy-wishlist' ) . '</span>';
				}
				?>
			</td>
			<?php endif; ?>
			
			<?php if ( $show_date ) : ?>
			<td class="airy-wishlist-date">
				<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->date_added ) ) ); ?>
			</td>
			<?php endif; ?>
			
			<?php if ( $show_add_to_cart ) : ?>
			<td class="airy-wishlist-cart">
				<?php if ( $can_add_to_cart ) : ?>
					<button type="button" class="button airy-add-to-cart-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>">
						<?php echo esc_html( get_option( 'airy_wishlist_add_to_cart_text', __( 'Add to Cart', 'airy-wishlist' ) ) ); ?>
					</button>
				<?php elseif ( $product->is_type( 'grouped' ) ) : ?>
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="button">
						<?php esc_html_e( 'View Product', 'airy-wishlist' ); ?>
					</a>
				<?php endif; ?>
			</td>
			<?php endif; ?>
		</tr>
		<?php
	}

	/**
	 * Render grid layout
	 *
	 * @param array $items Array of wishlist item objects.
	 * @param int   $columns Number of columns to display.
	 */
	private function render_grid_layout( $items, $columns ) {
		?>
		<div class="airy-wishlist-grid columns-<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<?php $this->render_grid_item( $item ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render grid item
	 */
	private function render_grid_item( $item ) {
		$product_id = $item->variation_id > 0 ? $item->variation_id : $item->product_id;
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}
		?>
		<div class="airy-wishlist-grid-item">
			<div class="airy-wishlist-grid-image">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
				</a>
				<form method="post" class="airy-remove-form" style="display:inline;">
					<?php wp_nonce_field( 'airy_remove_' . $item->product_id . '_' . $item->variation_id, 'airy_remove_nonce' ); ?>
					<input type="hidden" name="airy_remove_product" value="<?php echo esc_attr( $item->product_id ); ?>">
					<input type="hidden" name="airy_remove_variation" value="<?php echo esc_attr( $item->variation_id ); ?>">
					<button type="submit" class="airy-remove-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>">
						&times;
					</button>
				</form>
			</div>
			<div class="airy-wishlist-grid-content">
				<h3 class="airy-wishlist-grid-title">
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
						<?php echo wp_kses_post( $product->get_name() ); ?>
					</a>
				</h3>
				<?php
				// Show variation attributes if this is a variation.
				if ( $item->variation_id > 0 && $product->is_type( 'variation' ) ) {
					echo '<div class="airy-wishlist-variation">';
					echo wp_kses_post( wc_get_formatted_variation( $product, true ) );
					echo '</div>';
				}
				?>
				<div class="airy-wishlist-grid-price">
					<?php echo wp_kses_post( $product->get_price_html() ); ?>
				</div>
				<?php if ( $product->is_purchasable() && $product->is_in_stock() && ! $product->is_type( 'grouped' ) ) : ?>
					<button type="button" class="button airy-add-to-cart-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>">
						<?php echo esc_html( get_option( 'airy_wishlist_add_to_cart_text', __( 'Add to Cart', 'airy-wishlist' ) ) ); ?>
					</button>
				<?php elseif ( $product->is_type( 'grouped' ) ) : ?>
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="button">
						<?php esc_html_e( 'View Product', 'airy-wishlist' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render share buttons
	 */
	private function render_share_buttons() {
		$url   = airy_wishlist_get_url();
		$title = get_option( 'airy_wishlist_sharing_title', __( 'Check out my wishlist!', 'airy-wishlist' ) );
		?>
		<div class="airy-wishlist-share-buttons">
			<span><?php esc_html_e( 'Share:', 'airy-wishlist' ); ?></span>
			
			<?php if ( 'yes' === get_option( 'airy_wishlist_share_facebook', 'yes' ) ) : ?>
			<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $url ); ?>" target="_blank" class="airy-share-facebook">
				Facebook
			</a>
			<?php endif; ?>
			
			<?php if ( 'yes' === get_option( 'airy_wishlist_share_twitter', 'yes' ) ) : ?>
			<a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( $url ); ?>&text=<?php echo rawurlencode( $title ); ?>" target="_blank" class="airy-share-twitter">
				Twitter
			</a>
			<?php endif; ?>
			
			<?php if ( 'yes' === get_option( 'airy_wishlist_share_whatsapp', 'yes' ) ) : ?>
			<a href="https://wa.me/?text=<?php echo rawurlencode( $title . ' ' . $url ); ?>" target="_blank" class="airy-share-whatsapp">
				WhatsApp
			</a>
			<?php endif; ?>
			
			<?php if ( 'yes' === get_option( 'airy_wishlist_share_email', 'yes' ) ) : ?>
			<a href="mailto:?subject=<?php echo rawurlencode( $title ); ?>&body=<?php echo rawurlencode( $url ); ?>" class="airy-share-email">
				Email
			</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Wishlist counter shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Counter HTML output.
	 */
	public function wishlist_counter( $atts ) {
		$atts = shortcode_atts(
			array(
				'icon'      => 'heart',
				'show_text' => 'no',
			),
			$atts
		);

		$data  = Airy_Wishlist_Data::instance();
		$count = $data->get_count();
		$url   = $data->get_wishlist_url();

		$icon_html = airy_wishlist_get_icon( $atts['icon'] );

		ob_start();
		?>
		<div class="airy-wishlist-counter">
			<a href="<?php echo esc_url( $url ); ?>" class="airy-wishlist-counter-link">
				<span class="airy-wishlist-icon">
					<?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped via custom airy_wishlist_kses_svg() function
					echo airy_wishlist_kses_svg( $icon_html );
					?>
					<?php if ( $count > 0 ) : ?>
					<span class="airy-wishlist-count"><?php echo absint( $count ); ?></span>
					<?php endif; ?>
				</span>
				<?php if ( 'yes' === $atts['show_text'] ) : ?>
				<span class="airy-wishlist-text"><?php esc_html_e( 'Wishlist', 'airy-wishlist' ); ?></span>
				<?php endif; ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add to wishlist button shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Button HTML output.
	 */
	public function add_to_wishlist_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts
		);

		if ( ! $atts['product_id'] ) {
			global $product;
			if ( $product ) {
				$atts['product_id'] = $product->get_id();
			}
		}

		if ( ! $atts['product_id'] ) {
			return '';
		}

		return airy_wishlist_get_button_html( $atts['product_id'] );
	}
}