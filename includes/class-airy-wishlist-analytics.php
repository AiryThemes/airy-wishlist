<?php
/**
 * Analytics Handler - Tracks wishlist activity and renders the stats dashboard.
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wishlist analytics tracking and reporting.
 */
class Airy_Wishlist_Analytics {

	/**
	 * Single instance of the class.
	 *
	 * @var Airy_Wishlist_Analytics|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Airy_Wishlist_Analytics
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - register tracking hooks and the admin dashboard.
	 */
	private function __construct() {
		// Tracking runs everywhere (front-end AJAX fires these actions).
		add_action( 'airy_wishlist_item_added', array( $this, 'record_add' ), 10, 2 );
		add_action( 'airy_wishlist_added_to_cart', array( $this, 'record_cart' ), 10, 1 );
	}

	/**
	 * Record a wishlist add against the product.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @param int $product_id  Product ID.
	 */
	public function record_add( $wishlist_id, $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		$current = (int) get_post_meta( $product_id, '_airy_wishlist_adds', true );
		update_post_meta( $product_id, '_airy_wishlist_adds', $current + 1 );

		$total = (int) get_option( 'airy_wishlist_total_adds', 0 );
		update_option( 'airy_wishlist_total_adds', $total + 1, false );
	}

	/**
	 * Record an add-to-cart-from-wishlist conversion against the product.
	 *
	 * @param int $product_id Product ID.
	 */
	public function record_cart( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		$current = (int) get_post_meta( $product_id, '_airy_wishlist_carts', true );
		update_post_meta( $product_id, '_airy_wishlist_carts', $current + 1 );

		$total = (int) get_option( 'airy_wishlist_total_carts', 0 );
		update_option( 'airy_wishlist_total_carts', $total + 1, false );
	}

	/**
	 * Get headline totals.
	 *
	 * @return array
	 */
	private function get_totals() {
		global $wpdb;
		$db        = Airy_Wishlist_Database::instance();
		$wishlists = $db->get_wishlist_table();
		$items     = $db->get_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Aggregate over internal table; no user input.
		$total_wishlists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wishlists" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Aggregate over internal table; no user input.
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $items" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Aggregate over internal table; no user input.
		$unique_products = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM $items" );

		$total_adds  = (int) get_option( 'airy_wishlist_total_adds', 0 );
		$total_carts = (int) get_option( 'airy_wishlist_total_carts', 0 );

		return array(
			'total_wishlists' => $total_wishlists,
			'total_items'     => $total_items,
			'unique_products' => $unique_products,
			'total_adds'      => $total_adds,
			'total_carts'     => $total_carts,
			'conversion'      => $total_adds > 0 ? round( ( $total_carts / $total_adds ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get the most-wishlisted products (currently saved).
	 *
	 * @param int $limit Number of products.
	 * @return array
	 */
	private function get_top_products( $limit = 20 ) {
		global $wpdb;
		$db    = Airy_Wishlist_Database::instance();
		$items = $db->get_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over internal table.
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method.
				"SELECT product_id, COUNT(*) AS cnt FROM $items GROUP BY product_id ORDER BY cnt DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Render the analytics dashboard as a settings-page tab.
	 */
	public function render_dashboard() {
		$totals = $this->get_totals();
		$top    = $this->get_top_products( 20 );
		?>
		<div class="airy-wishlist-analytics">
			<div class="airy-stats-cards">
				<?php
				$this->render_stat_card( __( 'Total Wishlists', 'airy-wishlist' ), $totals['total_wishlists'] );
				$this->render_stat_card( __( 'Items Saved (now)', 'airy-wishlist' ), $totals['total_items'] );
				$this->render_stat_card( __( 'Unique Products', 'airy-wishlist' ), $totals['unique_products'] );
				$this->render_stat_card( __( 'All-time Adds', 'airy-wishlist' ), $totals['total_adds'] );
				$this->render_stat_card( __( 'Added to Cart', 'airy-wishlist' ), $totals['total_carts'] );
				$this->render_stat_card( __( 'Cart Conversion', 'airy-wishlist' ), $totals['conversion'] . '%' );
				?>
			</div>

			<h2><?php esc_html_e( 'Most Wishlisted Products', 'airy-wishlist' ); ?></h2>

			<?php if ( empty( $top ) ) : ?>
				<p><?php esc_html_e( 'No wishlist data yet.', 'airy-wishlist' ); ?></p>
			<?php else : ?>
				<table class="widefat striped airy-analytics-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'airy-wishlist' ); ?></th>
							<th><?php esc_html_e( 'In Wishlists (now)', 'airy-wishlist' ); ?></th>
							<th><?php esc_html_e( 'All-time Adds', 'airy-wishlist' ); ?></th>
							<th><?php esc_html_e( 'Added to Cart', 'airy-wishlist' ); ?></th>
							<th><?php esc_html_e( 'Conversion', 'airy-wishlist' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top as $row ) : ?>
							<?php
							$product_id = (int) $row->product_id;
							$product    = wc_get_product( $product_id );
							$name       = $product ? $product->get_name() : sprintf( /* translators: %d: product ID */ __( 'Product #%d (deleted)', 'airy-wishlist' ), $product_id );
							$edit_link  = get_edit_post_link( $product_id );
							$adds       = (int) get_post_meta( $product_id, '_airy_wishlist_adds', true );
							$carts      = (int) get_post_meta( $product_id, '_airy_wishlist_carts', true );
							$conv       = $adds > 0 ? round( ( $carts / $adds ) * 100, 1 ) . '%' : '—';
							?>
							<tr>
								<td>
									<?php if ( $product && $edit_link ) : ?>
										<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $name ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $name ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (int) $row->cnt ); ?></td>
								<td><?php echo esc_html( $adds ); ?></td>
								<td><?php echo esc_html( $carts ); ?></td>
								<td><?php echo esc_html( $conv ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single stat card.
	 *
	 * @param string     $label Card label.
	 * @param string|int $value Card value.
	 */
	private function render_stat_card( $label, $value ) {
		?>
		<div class="airy-stat-card">
			<div class="airy-stat-value"><?php echo esc_html( $value ); ?></div>
			<div class="airy-stat-label"><?php echo esc_html( $label ); ?></div>
		</div>
		<?php
	}
}
