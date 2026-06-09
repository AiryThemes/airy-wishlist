<?php
/**
 * Marketing Handler - Send promotional emails to customers who wishlisted a product.
 *
 * Admin-initiated: a store manager picks a product and composes a message that
 * is emailed to every logged-in customer who has that product in a wishlist.
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wishlist marketing / promotional email class.
 */
class Airy_Wishlist_Marketing {

	/**
	 * Single instance of the class.
	 *
	 * @var Airy_Wishlist_Marketing|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Airy_Wishlist_Marketing
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_post_airy_wishlist_send_promo', array( $this, 'handle_send' ) );
		}
	}

	/**
	 * Render the marketing tool as a settings-page tab.
	 */
	public function render_dashboard() {
		$db       = Airy_Wishlist_Database::instance();
		$products = $db->get_wishlisted_products_with_user_counts( 200 );

		// Success notice after a send.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a count passed back via redirect.
		if ( isset( $_GET['airy_promo_sent'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sent = absint( $_GET['airy_promo_sent'] );
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
				/* translators: %d: number of emails sent */
				esc_html( _n( '%d promotional email sent.', '%d promotional emails sent.', $sent, 'airy-wishlist' ) ),
				absint( $sent )
			) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of an error flag passed back via redirect.
		if ( isset( $_GET['airy_promo_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please select a product and enter both a subject and a message.', 'airy-wishlist' ) . '</p></div>';
		}
		?>
		<div class="airy-wishlist-marketing">
			<p><?php esc_html_e( 'Send a promotional email to all logged-in customers who have a specific product in their wishlist.', 'airy-wishlist' ); ?></p>
			<p class="description"><?php esc_html_e( 'Note: Only send marketing emails to customers who have consented to receive them, in line with your privacy policy and local laws (e.g. GDPR / CAN-SPAM).', 'airy-wishlist' ); ?></p>

			<?php if ( empty( $products ) ) : ?>
				<p><?php esc_html_e( 'No products have been wishlisted by registered customers yet.', 'airy-wishlist' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="airy_wishlist_send_promo">
					<?php wp_nonce_field( 'airy_wishlist_send_promo' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="promo_product"><?php esc_html_e( 'Product', 'airy-wishlist' ); ?></label></th>
							<td>
								<select name="promo_product" id="promo_product" required>
									<option value=""><?php esc_html_e( '— Select a product —', 'airy-wishlist' ); ?></option>
									<?php
									foreach ( $products as $row ) :
										$product = wc_get_product( $row->product_id );
										if ( ! $product ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( $row->product_id ); ?>">
											<?php
											echo esc_html(
												sprintf(
													/* translators: 1: product name, 2: number of customers */
													_n( '%1$s (%2$d customer)', '%1$s (%2$d customers)', (int) $row->users, 'airy-wishlist' ),
													$product->get_name(),
													(int) $row->users
												)
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'The number in brackets is how many customers will receive the email.', 'airy-wishlist' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="promo_subject"><?php esc_html_e( 'Email Subject', 'airy-wishlist' ); ?></label></th>
							<td>
								<input type="text" name="promo_subject" id="promo_subject" class="regular-text" required>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="promo_message"><?php esc_html_e( 'Message', 'airy-wishlist' ); ?></label></th>
							<td>
								<textarea name="promo_message" id="promo_message" rows="8" class="large-text" required></textarea>
								<p class="description"><?php esc_html_e( 'Basic HTML is allowed. A "Shop Now" link to the product is added automatically.', 'airy-wishlist' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Send Promotional Email', 'airy-wishlist' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle the send request.
	 */
	public function handle_send() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'airy-wishlist' ) );
		}

		check_admin_referer( 'airy_wishlist_send_promo' );

		$product_id = isset( $_POST['promo_product'] ) ? absint( $_POST['promo_product'] ) : 0;
		$subject    = isset( $_POST['promo_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_subject'] ) ) : '';
		$message    = isset( $_POST['promo_message'] ) ? wp_kses_post( wp_unslash( $_POST['promo_message'] ) ) : '';

		$redirect = admin_url( 'admin.php?page=airy-wishlist-settings&tab=marketing' );

		if ( ! $product_id || '' === $subject || '' === $message ) {
			wp_safe_redirect( add_query_arg( 'airy_promo_error', 1, $redirect ) );
			exit;
		}

		$sent = $this->send_promo( $product_id, $subject, $message );

		wp_safe_redirect( add_query_arg( 'airy_promo_sent', $sent, $redirect ) );
		exit;
	}

	/**
	 * Send the promotional email to everyone who wishlisted the product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $subject    Email subject.
	 * @param string $message    Email body (sanitised HTML).
	 * @return int Number of emails sent.
	 */
	private function send_promo( $product_id, $subject, $message ) {
		$db       = Airy_Wishlist_Database::instance();
		$user_ids = $db->get_users_who_wishlisted( $product_id );

		if ( empty( $user_ids ) ) {
			return 0;
		}

		$product      = wc_get_product( $product_id );
		$product_link = $product ? $product->get_permalink() : '';
		$headers      = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent         = 0;

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				continue;
			}

			$body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;">';
			$body .= wpautop( $message );

			if ( $product_link ) {
				$body .= '<p style="margin-top:20px;"><a href="' . esc_url( $product_link ) . '" style="display:inline-block;padding:10px 18px;background:#e74c3c;color:#fff;text-decoration:none;border-radius:4px;">' . esc_html__( 'Shop Now', 'airy-wishlist' ) . '</a></p>';
			}

			$body .= '<p style="color:#888;font-size:12px;margin-top:24px;">' . esc_html__( 'You are receiving this email because you added this product to your wishlist.', 'airy-wishlist' ) . '</p>';
			$body .= '</div>';

			if ( wp_mail( $user->user_email, $subject, $body, $headers ) ) {
				++$sent;
			}
		}

		return $sent;
	}
}
