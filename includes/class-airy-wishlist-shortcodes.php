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
	 * Whether multiple wishlists are active for the current render.
	 *
	 * @var bool
	 */
	private $ctx_multiple = false;

	/**
	 * All of the current owner's wishlists for the current render.
	 *
	 * @var array
	 */
	private $ctx_wishlists = array();

	/**
	 * The active wishlist ID for the current render.
	 *
	 * @var int
	 */
	private $ctx_active_id = 0;

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

		$data = Airy_Wishlist_Data::instance();

		// Detect a shared wishlist view via public token.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public view keyed by an unguessable token, no state change.
		$token = isset( $_GET['airy_wid'] ) ? sanitize_text_field( wp_unslash( $_GET['airy_wid'] ) ) : '';

		// Guests can't use the wishlist when guest wishlists are disabled (shared views are still allowed).
		if ( '' === $token && ! $data->is_enabled_for_visitor() ) {
			return $this->get_guest_disabled_notice();
		}
		$is_shared = false;
		$wishlists = array();
		$active_id = 0;

		// Reset render context.
		$this->ctx_multiple  = false;
		$this->ctx_wishlists = array();
		$this->ctx_active_id = 0;

		if ( '' !== $token ) {
			$shared = $data->get_shared_wishlist( $token );

			if ( null === $shared ) {
				return $this->get_shared_not_found();
			}

			$is_shared = true;
			$items     = $shared['items'];
		} else {
			// Owner view contains personalised data; prevent full-page caches from serving it to others.
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard WordPress / cache-plugin no-cache constant.
				define( 'DONOTCACHEPAGE', true );
			}

			if ( $data->is_multiple_enabled() ) {
				$wishlists = $data->get_wishlists();

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list selection; ownership is validated below.
				$active_id = isset( $_GET['airy_list'] ) ? absint( $_GET['airy_list'] ) : 0;

				if ( ! $active_id || ! $data->owns_wishlist( $active_id ) ) {
					$active_id = ! empty( $wishlists ) ? (int) $wishlists[0]->id : 0;
				}

				// Expose context to the item renderers (move dropdown, sharing).
				$this->ctx_multiple  = true;
				$this->ctx_wishlists = $wishlists;
				$this->ctx_active_id = $active_id;

				$items = $active_id ? $data->get_items_for( $active_id ) : array();
			} else {
				$items = $data->get_items();
			}
		}

		ob_start();

		do_action( 'airy_wishlist_before_table', $is_shared );

		if ( $is_shared ) {
			$this->render_shared_heading();
		}

		if ( ! $is_shared && $data->is_multiple_enabled() ) {
			$this->render_wishlist_switcher( $wishlists, $active_id );
		}

		// Stock/price notification opt-in (shown when the feature is enabled).
		if ( ! $is_shared && 'yes' === get_option( 'airy_wishlist_notify_enabled', 'no' ) ) {
			if ( is_user_logged_in() ) {
				// Logged-in customers get the per-wishlist opt-in toggle.
				$current = null;

				if ( $data->is_multiple_enabled() ) {
					foreach ( $wishlists as $wishlist ) {
						if ( (int) $wishlist->id === (int) $active_id ) {
							$current = $wishlist;
							break;
						}
					}
				} else {
					$current = $data->get_wishlist();
				}

				if ( $current ) {
					$this->render_notify_toggle( $current );
				}
			} else {
				// Guests can't receive notifications (no account email); prompt to log in.
				$this->render_notify_login_prompt();
			}
		}

		if ( empty( $items ) ) {
			$this->render_empty_wishlist();
		} elseif ( 'grid' === $atts['layout'] ) {
				$this->render_grid_layout( $items, $atts['columns'], $is_shared );
		} else {
			$this->render_table_layout( $items, $is_shared );
		}

		do_action( 'airy_wishlist_after_table', $is_shared );

		return ob_get_clean();
	}

	/**
	 * Message shown when a shared wishlist token is invalid or expired.
	 *
	 * @return string HTML output.
	 */
	private function get_shared_not_found() {
		ob_start();
		?>
		<div class="airy-wishlist-empty">
			<p><?php esc_html_e( 'This shared wishlist could not be found. The link may be invalid or has been removed.', 'airy-wishlist' ); ?></p>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button">
				<?php esc_html_e( 'Continue Shopping', 'airy-wishlist' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Notice shown to guests when guest wishlists are disabled.
	 *
	 * @return string HTML output.
	 */
	private function get_guest_disabled_notice() {
		$login_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		ob_start();
		?>
		<div class="airy-wishlist-empty">
			<p><?php esc_html_e( 'Please log in to create and manage your wishlist.', 'airy-wishlist' ); ?></p>
			<?php if ( $login_url ) : ?>
				<a href="<?php echo esc_url( $login_url ); ?>" class="button">
					<?php esc_html_e( 'Log in', 'airy-wishlist' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Heading shown above a shared (read-only) wishlist.
	 */
	private function render_shared_heading() {
		?>
		<div class="airy-wishlist-shared-notice">
			<p><?php esc_html_e( "You're viewing a shared wishlist.", 'airy-wishlist' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the per-wishlist notification opt-in toggle.
	 *
	 * @param object $wishlist Wishlist object.
	 */
	private function render_notify_toggle( $wishlist ) {
		$enabled = ! empty( $wishlist->notifications_enabled );
		?>
		<div class="airy-wishlist-notify">
			<label class="airy-notify-label">
				<input type="checkbox" class="airy-notify-toggle" data-wishlist-id="<?php echo esc_attr( $wishlist->id ); ?>" <?php checked( $enabled ); ?>>
				<?php esc_html_e( 'Notify me by email about stock & price changes for items in this list', 'airy-wishlist' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Prompt guests to log in so they can enable stock/price notifications.
	 */
	private function render_notify_login_prompt() {
		$login_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		?>
		<div class="airy-wishlist-notify airy-wishlist-notify-guest">
			<span><?php esc_html_e( 'Want email alerts when these items drop in price or come back in stock?', 'airy-wishlist' ); ?></span>
			<?php if ( $login_url ) : ?>
				<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in to enable notifications', 'airy-wishlist' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the "Move to another list" control for an item.
	 *
	 * Only shown when multiple wishlists are enabled and there is at least
	 * one other list to move the product into.
	 *
	 * @param object $item Wishlist item object.
	 */
	private function render_move_control( $item ) {
		if ( ! $this->ctx_multiple ) {
			return;
		}

		$targets = array();
		foreach ( $this->ctx_wishlists as $wishlist ) {
			if ( (int) $wishlist->id !== (int) $this->ctx_active_id ) {
				$targets[] = $wishlist;
			}
		}

		if ( empty( $targets ) ) {
			return;
		}
		?>
		<div class="airy-wishlist-move">
			<select class="airy-move-select" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>" data-from-id="<?php echo esc_attr( $this->ctx_active_id ); ?>">
				<option value=""><?php esc_html_e( 'Move to…', 'airy-wishlist' ); ?></option>
				<?php foreach ( $targets as $target ) : ?>
					<option value="<?php echo esc_attr( $target->id ); ?>"><?php echo esc_html( $target->wishlist_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Render the multiple-wishlist switcher (tabs + management controls).
	 *
	 * @param array $wishlists Array of the owner's wishlist objects.
	 * @param int   $active_id Currently active wishlist ID.
	 */
	private function render_wishlist_switcher( $wishlists, $active_id ) {
		$base_url = airy_wishlist_get_url();
		$active   = null;

		foreach ( $wishlists as $wishlist ) {
			if ( (int) $wishlist->id === (int) $active_id ) {
				$active = $wishlist;
				break;
			}
		}
		?>
		<div class="airy-wishlist-switcher" data-active-id="<?php echo esc_attr( $active_id ); ?>">
			<div class="airy-wishlist-tabs">
				<?php
				foreach ( $wishlists as $wishlist ) :
					$tab_url   = add_query_arg( 'airy_list', (int) $wishlist->id, $base_url );
					$is_active = (int) $wishlist->id === (int) $active_id;
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="airy-wishlist-tab <?php echo $is_active ? 'active' : ''; ?>" data-wishlist-id="<?php echo esc_attr( $wishlist->id ); ?>">
						<?php echo esc_html( $wishlist->wishlist_name ); ?>
					</a>
				<?php endforeach; ?>
				<button type="button" class="airy-wishlist-new-btn">
					<?php esc_html_e( '+ New List', 'airy-wishlist' ); ?>
				</button>
			</div>

			<?php if ( $active ) : ?>
			<div class="airy-wishlist-list-actions">
				<button type="button" class="airy-wishlist-rename-btn" data-wishlist-id="<?php echo esc_attr( $active->id ); ?>" data-current-name="<?php echo esc_attr( $active->wishlist_name ); ?>">
					<?php esc_html_e( 'Rename', 'airy-wishlist' ); ?>
				</button>
				<?php if ( ! $active->is_default ) : ?>
				<button type="button" class="airy-wishlist-delete-btn" data-wishlist-id="<?php echo esc_attr( $active->id ); ?>">
					<?php esc_html_e( 'Delete', 'airy-wishlist' ); ?>
				</button>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
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
	 * @param bool  $is_shared Whether this is a read-only shared view.
	 */
	private function render_table_layout( $items, $is_shared = false ) {
		$show_stock       = 'yes' === get_option( 'airy_wishlist_show_stock_status', 'yes' );
		$show_date        = 'yes' === get_option( 'airy_wishlist_show_date_added', 'yes' );
		$show_add_to_cart = 'yes' === get_option( 'airy_wishlist_show_add_to_cart', 'yes' );
		$show_remove      = ! $is_shared && 'yes' === get_option( 'airy_wishlist_show_remove_button', 'yes' );
		$show_add_all     = 'yes' === get_option( 'airy_wishlist_show_add_all_to_cart', 'yes' );
		?>
		<div class="airy-wishlist-wrapper">
			<?php if ( ! $is_shared && 'yes' === get_option( 'airy_wishlist_enable_share', 'yes' ) ) : ?>
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
		<tr class="airy-wishlist-item" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>" data-wishlist-id="<?php echo esc_attr( $this->ctx_active_id ); ?>">
			<?php if ( $show_remove ) : ?>
			<td class="airy-wishlist-remove">
				<form method="post" class="airy-remove-form" style="display:inline;">
					<?php wp_nonce_field( 'airy_remove_' . $item->product_id . '_' . $item->variation_id, 'airy_remove_nonce' ); ?>
					<input type="hidden" name="airy_remove_product" value="<?php echo esc_attr( $item->product_id ); ?>">
					<input type="hidden" name="airy_remove_variation" value="<?php echo esc_attr( $item->variation_id ); ?>">
					<input type="hidden" name="airy_remove_wishlist" value="<?php echo esc_attr( $this->ctx_active_id ); ?>">
					<button type="submit" class="airy-remove-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>" data-wishlist-id="<?php echo esc_attr( $this->ctx_active_id ); ?>">
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
				$this->render_move_control( $item );
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
					<button type="button" class="button airy-add-to-cart-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>" data-wishlist-id="<?php echo esc_attr( $this->ctx_active_id ); ?>">
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
	 * @param bool  $is_shared Whether this is a read-only shared view.
	 */
	private function render_grid_layout( $items, $columns, $is_shared = false ) {
		?>
		<?php if ( ! $is_shared && 'yes' === get_option( 'airy_wishlist_enable_share', 'yes' ) ) : ?>
			<div class="airy-wishlist-share">
				<?php $this->render_share_buttons(); ?>
			</div>
		<?php endif; ?>
		<div class="airy-wishlist-grid columns-<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<?php $this->render_grid_item( $item, $is_shared ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render grid item
	 *
	 * @param object $item      Wishlist item object.
	 * @param bool   $is_shared Whether this is a read-only shared view.
	 */
	private function render_grid_item( $item, $is_shared = false ) {
		$product_id = $item->variation_id > 0 ? $item->variation_id : $item->product_id;
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$show_remove = ! $is_shared && 'yes' === get_option( 'airy_wishlist_show_remove_button', 'yes' );
		?>
		<div class="airy-wishlist-grid-item">
			<div class="airy-wishlist-grid-image">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
				</a>
				<?php if ( $show_remove ) : ?>
				<form method="post" class="airy-remove-form" style="display:inline;">
					<?php wp_nonce_field( 'airy_remove_' . $item->product_id . '_' . $item->variation_id, 'airy_remove_nonce' ); ?>
					<input type="hidden" name="airy_remove_product" value="<?php echo esc_attr( $item->product_id ); ?>">
					<input type="hidden" name="airy_remove_variation" value="<?php echo esc_attr( $item->variation_id ); ?>">
					<input type="hidden" name="airy_remove_wishlist" value="<?php echo esc_attr( $this->ctx_active_id ); ?>">
					<button type="submit" class="airy-remove-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>" data-wishlist-id="<?php echo esc_attr( $this->ctx_active_id ); ?>">
						&times;
					</button>
				</form>
				<?php endif; ?>
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
					<button type="button" class="button airy-add-to-cart-from-wishlist" data-product-id="<?php echo esc_attr( $item->product_id ); ?>" data-variation-id="<?php echo esc_attr( $item->variation_id ); ?>" data-wishlist-id="<?php echo esc_attr( $this->ctx_active_id ); ?>">
						<?php echo esc_html( get_option( 'airy_wishlist_add_to_cart_text', __( 'Add to Cart', 'airy-wishlist' ) ) ); ?>
					</button>
				<?php elseif ( $product->is_type( 'grouped' ) ) : ?>
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="button">
						<?php esc_html_e( 'View Product', 'airy-wishlist' ); ?>
					</a>
				<?php endif; ?>
				<?php $this->render_move_control( $item ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render share buttons
	 */
	private function render_share_buttons() {
		$data = Airy_Wishlist_Data::instance();

		// Share the wishlist currently being viewed (the active list when multiple are enabled).
		$url = $this->ctx_active_id ? $data->get_share_url_for( $this->ctx_active_id ) : $data->get_share_url();

		if ( empty( $url ) ) {
			$url = airy_wishlist_get_url();
		}

		$title = get_option( 'airy_wishlist_sharing_title', __( 'Check out my wishlist!', 'airy-wishlist' ) );
		?>
		<div class="airy-wishlist-share-link">
			<input type="text" class="airy-wishlist-share-url" value="<?php echo esc_url( $url ); ?>" readonly onclick="this.select();">
			<button type="button" class="button airy-copy-share-link" data-copied-text="<?php esc_attr_e( 'Copied!', 'airy-wishlist' ); ?>">
				<?php esc_html_e( 'Copy Link', 'airy-wishlist' ); ?>
			</button>
		</div>
		<div class="airy-wishlist-share-buttons">
			<span><?php esc_html_e( 'Share:', 'airy-wishlist' ); ?></span>

			<?php if ( 'yes' === get_option( 'airy_wishlist_share_facebook', 'yes' ) ) : ?>
			<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $url ); ?>" target="_blank" rel="noopener noreferrer" class="airy-share-facebook">
				Facebook
			</a>
			<?php endif; ?>
			
			<?php if ( 'yes' === get_option( 'airy_wishlist_share_twitter', 'yes' ) ) : ?>
			<a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( $url ); ?>&text=<?php echo rawurlencode( $title ); ?>" target="_blank" rel="noopener noreferrer" class="airy-share-twitter">
				Twitter
			</a>
			<?php endif; ?>
			
			<?php if ( 'yes' === get_option( 'airy_wishlist_share_pinterest', 'yes' ) ) : ?>
				<a href="https://pinterest.com/pin/create/button/?url=<?php echo rawurlencode( $url ); ?>&description=<?php echo rawurlencode( $title ); ?>" target="_blank" rel="noopener noreferrer" class="airy-share-pinterest">
					Pinterest
				</a>
				<?php endif; ?>

				<?php if ( 'yes' === get_option( 'airy_wishlist_share_whatsapp', 'yes' ) ) : ?>
			<a href="https://wa.me/?text=<?php echo rawurlencode( $title . ' ' . $url ); ?>" target="_blank" rel="noopener noreferrer" class="airy-share-whatsapp">
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
		$count = $data->is_multiple_enabled() ? $data->get_total_count() : $data->get_count();
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
					<span class="airy-wishlist-count"<?php echo $count > 0 ? '' : ' style="display:none;"'; ?>><?php echo absint( $count ); ?></span>
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