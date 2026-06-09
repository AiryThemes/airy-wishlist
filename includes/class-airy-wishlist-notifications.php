<?php
/**
 * Notifications Handler - Stock & price alerts for wishlisted items.
 *
 * Logged-in users opt in per wishlist; a WP-Cron job periodically checks each
 * item's stock/price state against a stored snapshot and emails a digest of
 * changes (back in stock, price drop, low stock, on sale).
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wishlist notifications class.
 */
class Airy_Wishlist_Notifications {

	const CRON_HOOK = 'airy_wishlist_check_notifications';

	/**
	 * Single instance of the class.
	 *
	 * @var Airy_Wishlist_Notifications|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Airy_Wishlist_Notifications
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - register cron, scheduling, and AJAX.
	 */
	private function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_checks' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );

		// Per-wishlist opt-in toggle (logged-in users only).
		add_action( 'wp_ajax_airy_toggle_notifications', array( $this, 'ajax_toggle' ) );
	}

	/**
	 * Whether the notifications feature is enabled globally.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === get_option( 'airy_wishlist_notify_enabled', 'no' );
	}

	/**
	 * Ensure the cron event matches the current settings.
	 */
	public function maybe_schedule() {
		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( ! $this->is_enabled() ) {
			if ( $scheduled ) {
				wp_unschedule_event( $scheduled, self::CRON_HOOK );
			}
			delete_option( 'airy_wishlist_notify_scheduled_freq' );
			return;
		}

		$frequency = get_option( 'airy_wishlist_notify_frequency', 'daily' );
		if ( ! in_array( $frequency, array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
			$frequency = 'daily';
		}

		$stored_freq = get_option( 'airy_wishlist_notify_scheduled_freq' );

		if ( ! $scheduled ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $frequency, self::CRON_HOOK );
			update_option( 'airy_wishlist_notify_scheduled_freq', $frequency, false );
		} elseif ( $stored_freq !== $frequency ) {
			// Frequency changed - reschedule.
			wp_unschedule_event( $scheduled, self::CRON_HOOK );
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $frequency, self::CRON_HOOK );
			update_option( 'airy_wishlist_notify_scheduled_freq', $frequency, false );
		}
	}

	/**
	 * Toggle notifications for a wishlist (AJAX).
	 */
	public function ajax_toggle() {
		check_ajax_referer( 'airy_wishlist_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to manage notifications.', 'airy-wishlist' ) ) );
		}

		$wishlist_id = isset( $_POST['wishlist_id'] ) ? absint( $_POST['wishlist_id'] ) : 0;
		$enabled     = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];

		$data = Airy_Wishlist_Data::instance();

		if ( $data->set_notifications( $wishlist_id, $enabled ) ) {
			wp_send_json_success(
				array(
					'enabled' => $enabled,
					'message' => $enabled ? __( 'Notifications enabled.', 'airy-wishlist' ) : __( 'Notifications disabled.', 'airy-wishlist' ),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'Could not update notifications.', 'airy-wishlist' ) ) );
	}

	/**
	 * Cron callback: scan opted-in wishlists and email digests of changes.
	 */
	public function run_checks() {
		if ( ! $this->is_enabled() || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$opts = array(
			'back_in_stock' => 'yes' === get_option( 'airy_wishlist_notify_back_in_stock', 'yes' ),
			'price_drop'    => 'yes' === get_option( 'airy_wishlist_notify_price_drop', 'yes' ),
			'low_stock'     => 'yes' === get_option( 'airy_wishlist_notify_low_stock', 'no' ),
			'on_sale'       => 'yes' === get_option( 'airy_wishlist_notify_on_sale', 'yes' ),
		);

		$db        = Airy_Wishlist_Database::instance();
		$wishlists = $db->get_notify_enabled_wishlists();
		$digests   = array();

		foreach ( $wishlists as $wishlist ) {
			$user = get_userdata( $wishlist->user_id );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				continue;
			}

			$items = $db->get_wishlist_items( $wishlist->id );

			foreach ( $items as $item ) {
				$product_id = $item->variation_id > 0 ? $item->variation_id : $item->product_id;
				$product    = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$result = $this->evaluate_item( $item, $product, $opts );

				if ( ! empty( $result['triggers'] ) ) {
					$db->update_item_snapshot( $item->id, wp_json_encode( $result['snapshot'] ), current_time( 'mysql' ) );
					$digests[ $wishlist->user_id ][] = array(
						'product'  => $product,
						'triggers' => $result['triggers'],
					);
				} else {
					$db->update_item_snapshot( $item->id, wp_json_encode( $result['snapshot'] ) );
				}
			}
		}

		foreach ( $digests as $user_id => $lines ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$this->send_digest( $user, $lines );
			}
		}
	}

	/**
	 * Evaluate a single item against its stored snapshot.
	 *
	 * @param object     $item    Wishlist item row (with notify_snapshot).
	 * @param WC_Product $product Product object.
	 * @param array      $opts    Enabled trigger flags.
	 * @return array { triggers: array, snapshot: array }
	 */
	private function evaluate_item( $item, $product, $opts ) {
		$current = array(
			'price'     => (float) $product->get_price(),
			'in_stock'  => (bool) $product->is_in_stock(),
			'on_sale'   => (bool) $product->is_on_sale(),
			'low_stock' => $this->is_low_stock( $product ),
		);

		$snapshot = ! empty( $item->notify_snapshot ) ? json_decode( $item->notify_snapshot, true ) : null;
		$triggers = array();

		// Only compare when we have a prior baseline; first run just records it.
		if ( is_array( $snapshot ) ) {
			if ( $opts['back_in_stock'] && empty( $snapshot['in_stock'] ) && $current['in_stock'] ) {
				$triggers['back_in_stock'] = true;
			}

			if ( $opts['price_drop'] && isset( $snapshot['price'] ) && $current['price'] > 0 && $current['price'] < (float) $snapshot['price'] ) {
				$triggers['price_drop'] = array(
					'old' => (float) $snapshot['price'],
					'new' => $current['price'],
				);
			}

			if ( $opts['low_stock'] && empty( $snapshot['low_stock'] ) && $current['low_stock'] ) {
				$triggers['low_stock'] = true;
			}

			if ( $opts['on_sale'] && empty( $snapshot['on_sale'] ) && $current['on_sale'] ) {
				$triggers['on_sale'] = true;
			}
		}

		return array(
			'triggers' => $triggers,
			'snapshot' => $current,
		);
	}

	/**
	 * Determine whether a product is at or below its low-stock threshold.
	 *
	 * @param WC_Product $product Product object.
	 * @return bool
	 */
	private function is_low_stock( $product ) {
		if ( ! $product->managing_stock() ) {
			return false;
		}

		$qty = $product->get_stock_quantity();
		if ( null === $qty ) {
			return false;
		}

		$threshold = function_exists( 'wc_get_low_stock_amount' )
			? wc_get_low_stock_amount( $product )
			: (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );

		return $qty > 0 && $qty <= $threshold;
	}

	/**
	 * Send a digest email of changes to a user.
	 *
	 * @param WP_User $user  Recipient.
	 * @param array   $lines Array of { product, triggers }.
	 */
	private function send_digest( $user, $lines ) {
		$subject = get_option( 'airy_wishlist_notify_subject', __( 'Updates on your wishlist items', 'airy-wishlist' ) );

		$rows = '';
		foreach ( $lines as $line ) {
			$product = $line['product'];
			$name    = $product->get_name();
			$link    = $product->get_permalink();
			$badges  = $this->trigger_labels( $line['triggers'] );

			$rows .= '<tr>';
			$rows .= '<td style="padding:12px 0;border-bottom:1px solid #eee;">';
			$rows .= '<a href="' . esc_url( $link ) . '" style="font-weight:600;color:#1d2327;text-decoration:none;">' . esc_html( $name ) . '</a><br>';
			$rows .= '<span style="color:#555;font-size:13px;">' . wp_kses_post( $badges ) . '</span>';
			$rows .= '</td></tr>';
		}

		$wishlist_url = airy_wishlist_get_url();

		$body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;">';
		$body .= '<p>' . sprintf(
			/* translators: %s: user display name */
			esc_html__( 'Hi %s, there are updates on items in your wishlist:', 'airy-wishlist' ),
			esc_html( $user->display_name )
		) . '</p>';
		$body .= '<table style="width:100%;border-collapse:collapse;">' . $rows . '</table>';
		$body .= '<p style="margin-top:20px;"><a href="' . esc_url( $wishlist_url ) . '" style="display:inline-block;padding:10px 18px;background:#e74c3c;color:#fff;text-decoration:none;border-radius:4px;">' . esc_html__( 'View Your Wishlist', 'airy-wishlist' ) . '</a></p>';
		$body .= '<p style="color:#888;font-size:12px;margin-top:24px;">' . esc_html__( 'To stop these emails, open your wishlist and turn off notifications.', 'airy-wishlist' ) . '</p>';
		$body .= '</div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $user->user_email, $subject, $body, $headers );
	}

	/**
	 * Build a human-readable label string for a set of triggers.
	 *
	 * @param array $triggers Triggers map.
	 * @return string
	 */
	private function trigger_labels( $triggers ) {
		$labels = array();

		if ( isset( $triggers['back_in_stock'] ) ) {
			$labels[] = esc_html__( 'Back in stock', 'airy-wishlist' );
		}

		if ( isset( $triggers['price_drop'] ) ) {
			$labels[] = sprintf(
				/* translators: 1: old price, 2: new price */
				esc_html__( 'Price dropped from %1$s to %2$s', 'airy-wishlist' ),
				wp_strip_all_tags( wc_price( $triggers['price_drop']['old'] ) ),
				wp_strip_all_tags( wc_price( $triggers['price_drop']['new'] ) )
			);
		}

		if ( isset( $triggers['low_stock'] ) ) {
			$labels[] = esc_html__( 'Low stock - order soon', 'airy-wishlist' );
		}

		if ( isset( $triggers['on_sale'] ) ) {
			$labels[] = esc_html__( 'Now on sale', 'airy-wishlist' );
		}

		return implode( ' &middot; ', $labels );
	}
}
