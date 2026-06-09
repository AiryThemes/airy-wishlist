<?php
/**
 * Admin Handler
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings and menu handler
 */
class Airy_Wishlist_Admin {

	/**
	 * Single instance of the class
	 *
	 * @var Airy_Wishlist_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Airy_Wishlist_Admin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize admin hooks
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_woocommerce is a valid WooCommerce capability.
		add_submenu_page(
			'woocommerce',
			__( 'Wishlist Settings', 'airy-wishlist' ),
			__( 'Wishlist', 'airy-wishlist' ),
			'manage_woocommerce',
			'airy-wishlist-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_airy-wishlist-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'airy-wishlist-admin',
			AIRY_WISHLIST_URL . 'assets/css/admin.css',
			array(),
			AIRY_WISHLIST_VERSION
		);

		wp_enqueue_script(
			'airy-wishlist-admin',
			AIRY_WISHLIST_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			AIRY_WISHLIST_VERSION,
			true
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// General settings.
		register_setting( 'airy_wishlist_general', 'airy_wishlist_enable_ajax', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_page_id', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_redirect_after_add', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_remove_after_add_to_cart', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_button_toggle', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_guest_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_cookie_expiry', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_multiple_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_general', 'airy_wishlist_myaccount_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Button settings.
		register_setting( 'airy_wishlist_button', 'airy_wishlist_button_position', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_button', 'airy_wishlist_button_type', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_button', 'airy_wishlist_button_icon', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_button', 'airy_wishlist_show_on_loop', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_button', 'airy_wishlist_loop_position', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Customization.
		register_setting( 'airy_wishlist_customization', 'airy_wishlist_button_bg_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'airy_wishlist_customization', 'airy_wishlist_button_text_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'airy_wishlist_customization', 'airy_wishlist_button_bg_color_hover', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'airy_wishlist_customization', 'airy_wishlist_button_text_color_hover', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'airy_wishlist_customization', 'airy_wishlist_button_added_bg_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'airy_wishlist_customization', 'airy_wishlist_button_added_text_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );

		// Wishlist page.
		register_setting( 'airy_wishlist_page', 'airy_wishlist_page_layout', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_page', 'airy_wishlist_show_stock_status', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_page', 'airy_wishlist_show_date_added', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_page', 'airy_wishlist_show_add_to_cart', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_page', 'airy_wishlist_show_remove_button', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_page', 'airy_wishlist_show_add_all_to_cart', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Social sharing.
		register_setting( 'airy_wishlist_sharing', 'airy_wishlist_enable_share', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_sharing', 'airy_wishlist_share_facebook', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_sharing', 'airy_wishlist_share_twitter', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_sharing', 'airy_wishlist_share_pinterest', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_sharing', 'airy_wishlist_share_whatsapp', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_sharing', 'airy_wishlist_share_email', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_sharing', 'airy_wishlist_sharing_title', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Labels.
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_add_button_text', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_added_button_text', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_remove_button_text', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_view_wishlist_text', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_product_added_message', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_product_removed_message', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_add_to_cart_text', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_labels', 'airy_wishlist_empty_text', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Notifications.
		register_setting( 'airy_wishlist_notifications', 'airy_wishlist_notify_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_notifications', 'airy_wishlist_notify_back_in_stock', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_notifications', 'airy_wishlist_notify_price_drop', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_notifications', 'airy_wishlist_notify_low_stock', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_notifications', 'airy_wishlist_notify_on_sale', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_notifications', 'airy_wishlist_notify_frequency', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'airy_wishlist_notifications', 'airy_wishlist_notify_subject', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	/**
	 * Notifications settings tab
	 */
	private function notifications_settings() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'airy_wishlist_notifications' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Enable Notifications', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_notify_enabled" value="yes" <?php checked( get_option( 'airy_wishlist_notify_enabled', 'no' ), 'yes' ); ?>>
							<?php esc_html_e( 'Email logged-in customers about changes to their wishlisted items', 'airy-wishlist' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Customers opt in per wishlist (a "Notify me" toggle appears on the wishlist page). Sent via WP-Cron.', 'airy-wishlist' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Notify About', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="airy_wishlist_notify_back_in_stock" value="yes" <?php checked( get_option( 'airy_wishlist_notify_back_in_stock', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Back in stock', 'airy-wishlist' ); ?>
						</label>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="airy_wishlist_notify_price_drop" value="yes" <?php checked( get_option( 'airy_wishlist_notify_price_drop', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Price drop', 'airy-wishlist' ); ?>
						</label>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="airy_wishlist_notify_low_stock" value="yes" <?php checked( get_option( 'airy_wishlist_notify_low_stock', 'no' ), 'yes' ); ?>>
							<?php esc_html_e( 'Low stock (only a few left)', 'airy-wishlist' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" name="airy_wishlist_notify_on_sale" value="yes" <?php checked( get_option( 'airy_wishlist_notify_on_sale', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'On sale', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Check Frequency', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<select name="airy_wishlist_notify_frequency">
							<option value="hourly" <?php selected( get_option( 'airy_wishlist_notify_frequency', 'daily' ), 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'airy-wishlist' ); ?></option>
							<option value="twicedaily" <?php selected( get_option( 'airy_wishlist_notify_frequency', 'daily' ), 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'airy-wishlist' ); ?></option>
							<option value="daily" <?php selected( get_option( 'airy_wishlist_notify_frequency', 'daily' ), 'daily' ); ?>><?php esc_html_e( 'Daily', 'airy-wishlist' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'How often to check wishlisted items for changes.', 'airy-wishlist' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Email Subject', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_notify_subject" value="<?php echo esc_attr( get_option( 'airy_wishlist_notify_subject', __( 'Updates on your wishlist items', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap airy-wishlist-settings">
			<h1><?php esc_html_e( 'Airy Wishlist Settings', 'airy-wishlist' ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=airy-wishlist-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=button" class="nav-tab <?php echo 'button' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Add to Wishlist', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=page" class="nav-tab <?php echo 'page' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Wishlist Page', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=customization" class="nav-tab <?php echo 'customization' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Customization', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=sharing" class="nav-tab <?php echo 'sharing' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Social Sharing', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=labels" class="nav-tab <?php echo 'labels' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Labels', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=notifications" class="nav-tab <?php echo 'notifications' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Notifications', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=analytics" class="nav-tab <?php echo 'analytics' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Analytics', 'airy-wishlist' ); ?>
				</a>
				<a href="?page=airy-wishlist-settings&tab=marketing" class="nav-tab <?php echo 'marketing' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Marketing', 'airy-wishlist' ); ?>
				</a>
			</h2>
			
			<div class="airy-wishlist-settings-content">
				<?php
				switch ( $active_tab ) {
					case 'general':
						$this->general_settings();
						break;
					case 'button':
						$this->button_settings();
						break;
					case 'page':
						$this->page_settings();
						break;
					case 'customization':
						$this->customization_settings();
						break;
					case 'sharing':
						$this->sharing_settings();
						break;
					case 'labels':
						$this->labels_settings();
						break;
					case 'notifications':
						$this->notifications_settings();
						break;
					case 'analytics':
						Airy_Wishlist_Analytics::instance()->render_dashboard();
						break;
					case 'marketing':
						Airy_Wishlist_Marketing::instance()->render_dashboard();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * General settings tab
	 */
	private function general_settings() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'airy_wishlist_general' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Enable AJAX', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_enable_ajax" value="yes" <?php checked( get_option( 'airy_wishlist_enable_ajax', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable AJAX for add/remove actions', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Wishlist Page', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'             => 'airy_wishlist_page_id',
								'selected'         => absint( get_option( 'airy_wishlist_page_id' ) ),
								'show_option_none' => esc_html__( 'Select a page', 'airy-wishlist' ),
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Select the page that contains the [airy_wishlist] shortcode.', 'airy-wishlist' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'After Adding to Wishlist', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_redirect_after_add" value="yes" <?php checked( get_option( 'airy_wishlist_redirect_after_add', 'no' ), 'yes' ); ?>>
							<?php esc_html_e( 'Redirect to wishlist page', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'After Adding to Cart', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_remove_after_add_to_cart" value="yes" <?php checked( get_option( 'airy_wishlist_remove_after_add_to_cart', 'no' ), 'yes' ); ?>>
							<?php esc_html_e( 'Remove product from wishlist when added to cart', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Wishlist Button Toggle', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_button_toggle" value="yes" <?php checked( get_option( 'airy_wishlist_button_toggle', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Clicking the "Add to Wishlist" button again removes the product from the wishlist', 'airy-wishlist' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, the button works as a toggle: first click adds the product, second click removes it. Requires AJAX.', 'airy-wishlist' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Guest Users', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_guest_enabled" value="yes" <?php checked( get_option( 'airy_wishlist_guest_enabled', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable wishlist for guest users', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Multiple Wishlists', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_multiple_enabled" value="yes" <?php checked( get_option( 'airy_wishlist_multiple_enabled', 'no' ), 'yes' ); ?>>
							<?php esc_html_e( 'Allow customers to create and manage multiple named wishlists', 'airy-wishlist' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, customers can create lists such as "Birthday" or "Christmas", switch between them on the wishlist page, and choose a target list when adding products.', 'airy-wishlist' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'My Account Menu', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_myaccount_enabled" value="yes" <?php checked( get_option( 'airy_wishlist_myaccount_enabled', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show a "Wishlist" tab in the WooCommerce My Account menu', 'airy-wishlist' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Adds a Wishlist link under My Account so logged-in customers can view their wishlist there.', 'airy-wishlist' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Cookie Expiry (Days)', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="number" name="airy_wishlist_cookie_expiry" value="<?php echo esc_attr( get_option( 'airy_wishlist_cookie_expiry', 30 ) ); ?>" min="1" max="365" class="small-text">
						<p class="description"><?php esc_html_e( 'How long to store guest wishlist data (1-365 days).', 'airy-wishlist' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Button settings tab
	 */
	private function button_settings() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'airy_wishlist_button' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Button Position (Single Product)', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<select name="airy_wishlist_button_position">
							<option value="before_add_to_cart" <?php selected( get_option( 'airy_wishlist_button_position', 'after_add_to_cart' ), 'before_add_to_cart' ); ?>>
								<?php esc_html_e( 'Before Add to Cart', 'airy-wishlist' ); ?>
							</option>
							<option value="after_add_to_cart" <?php selected( get_option( 'airy_wishlist_button_position', 'after_add_to_cart' ), 'after_add_to_cart' ); ?>>
								<?php esc_html_e( 'After Add to Cart', 'airy-wishlist' ); ?>
							</option>
							<option value="after_summary" <?php selected( get_option( 'airy_wishlist_button_position', 'after_add_to_cart' ), 'after_summary' ); ?>>
								<?php esc_html_e( 'After Product Summary', 'airy-wishlist' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Button Type', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<select name="airy_wishlist_button_type">
							<option value="icon_text" <?php selected( get_option( 'airy_wishlist_button_type', 'icon_text' ), 'icon_text' ); ?>>
								<?php esc_html_e( 'Icon + Text', 'airy-wishlist' ); ?>
							</option>
							<option value="icon" <?php selected( get_option( 'airy_wishlist_button_type', 'icon_text' ), 'icon' ); ?>>
								<?php esc_html_e( 'Icon Only', 'airy-wishlist' ); ?>
							</option>
							<option value="text" <?php selected( get_option( 'airy_wishlist_button_type', 'icon_text' ), 'text' ); ?>>
								<?php esc_html_e( 'Text Only', 'airy-wishlist' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Button Icon', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<select name="airy_wishlist_button_icon">
							<option value="heart" <?php selected( get_option( 'airy_wishlist_button_icon', 'heart' ), 'heart' ); ?>>
								<?php esc_html_e( 'Heart', 'airy-wishlist' ); ?>
							</option>
							<option value="star" <?php selected( get_option( 'airy_wishlist_button_icon', 'heart' ), 'star' ); ?>>
								<?php esc_html_e( 'Star', 'airy-wishlist' ); ?>
							</option>
							<option value="bookmark" <?php selected( get_option( 'airy_wishlist_button_icon', 'heart' ), 'bookmark' ); ?>>
								<?php esc_html_e( 'Bookmark', 'airy-wishlist' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Show on Product Loop', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_show_on_loop" value="yes" <?php checked( get_option( 'airy_wishlist_show_on_loop', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show button on shop/archive pages', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Loop Button Position', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<select name="airy_wishlist_loop_position">
							<option value="before_add_to_cart" <?php selected( get_option( 'airy_wishlist_loop_position', 'after_add_to_cart' ), 'before_add_to_cart' ); ?>>
								<?php esc_html_e( 'Before Add to Cart', 'airy-wishlist' ); ?>
							</option>
							<option value="after_add_to_cart" <?php selected( get_option( 'airy_wishlist_loop_position', 'after_add_to_cart' ), 'after_add_to_cart' ); ?>>
								<?php esc_html_e( 'After Add to Cart', 'airy-wishlist' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Page settings tab
	 */
	private function page_settings() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'airy_wishlist_page' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Page Layout', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<select name="airy_wishlist_page_layout">
							<option value="table" <?php selected( get_option( 'airy_wishlist_page_layout', 'table' ), 'table' ); ?>>
								<?php esc_html_e( 'Table', 'airy-wishlist' ); ?>
							</option>
							<option value="grid" <?php selected( get_option( 'airy_wishlist_page_layout', 'table' ), 'grid' ); ?>>
								<?php esc_html_e( 'Grid', 'airy-wishlist' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Show Stock Status', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_show_stock_status" value="yes" <?php checked( get_option( 'airy_wishlist_show_stock_status', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show stock status on wishlist page', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Show Date Added', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_show_date_added" value="yes" <?php checked( get_option( 'airy_wishlist_show_date_added', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show date added column', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Show Add to Cart Button', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_show_add_to_cart" value="yes" <?php checked( get_option( 'airy_wishlist_show_add_to_cart', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show add to cart button for each product', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Show Remove Button', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_show_remove_button" value="yes" <?php checked( get_option( 'airy_wishlist_show_remove_button', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show remove button for each product', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Show "Add All to Cart"', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_show_add_all_to_cart" value="yes" <?php checked( get_option( 'airy_wishlist_show_add_all_to_cart', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show button to add all products to cart', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Customization settings tab
	 */
	private function customization_settings() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'airy_wishlist_customization' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Button Background Color', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_button_bg_color" value="<?php echo esc_attr( get_option( 'airy_wishlist_button_bg_color', '#ffffff' ) ); ?>" class="airy-color-picker">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Button Text Color', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_button_text_color" value="<?php echo esc_attr( get_option( 'airy_wishlist_button_text_color', '#333333' ) ); ?>" class="airy-color-picker">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Button Hover Background', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_button_bg_color_hover" value="<?php echo esc_attr( get_option( 'airy_wishlist_button_bg_color_hover', '#f8f8f8' ) ); ?>" class="airy-color-picker">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Button Hover Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_button_text_color_hover" value="<?php echo esc_attr( get_option( 'airy_wishlist_button_text_color_hover', '#000000' ) ); ?>" class="airy-color-picker">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Added State Background', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_button_added_bg_color" value="<?php echo esc_attr( get_option( 'airy_wishlist_button_added_bg_color', '#e74c3c' ) ); ?>" class="airy-color-picker">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Added State Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_button_added_text_color" value="<?php echo esc_attr( get_option( 'airy_wishlist_button_added_text_color', '#ffffff' ) ); ?>" class="airy-color-picker">
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Sharing settings tab
	 */
	private function sharing_settings() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'airy_wishlist_sharing' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Enable Social Sharing', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_enable_share" value="yes" <?php checked( get_option( 'airy_wishlist_enable_share', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Show social sharing buttons on wishlist page', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Facebook', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_share_facebook" value="yes" <?php checked( get_option( 'airy_wishlist_share_facebook', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable Facebook sharing', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Twitter', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_share_twitter" value="yes" <?php checked( get_option( 'airy_wishlist_share_twitter', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable Twitter sharing', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Pinterest', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_share_pinterest" value="yes" <?php checked( get_option( 'airy_wishlist_share_pinterest', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable Pinterest sharing', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'WhatsApp', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_share_whatsapp" value="yes" <?php checked( get_option( 'airy_wishlist_share_whatsapp', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable WhatsApp sharing', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Email', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="airy_wishlist_share_email" value="yes" <?php checked( get_option( 'airy_wishlist_share_email', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable Email sharing', 'airy-wishlist' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Sharing Title', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_sharing_title" value="<?php echo esc_attr( get_option( 'airy_wishlist_sharing_title', __( 'Check out my wishlist!', 'airy-wishlist' ) ) ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'This text will be used when sharing the wishlist.', 'airy-wishlist' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Labels settings tab
	 */
	private function labels_settings() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'airy_wishlist_labels' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( '"Add to Wishlist" Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_add_button_text" value="<?php echo esc_attr( get_option( 'airy_wishlist_add_button_text', __( 'Add to Wishlist', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( '"Added to Wishlist" Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_added_button_text" value="<?php echo esc_attr( get_option( 'airy_wishlist_added_button_text', __( 'Added to Wishlist', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( '"Remove" Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_remove_button_text" value="<?php echo esc_attr( get_option( 'airy_wishlist_remove_button_text', __( 'Remove', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( '"View Wishlist" Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_view_wishlist_text" value="<?php echo esc_attr( get_option( 'airy_wishlist_view_wishlist_text', __( 'View Wishlist', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Product Added Message', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_product_added_message" value="<?php echo esc_attr( get_option( 'airy_wishlist_product_added_message', __( 'Product added to wishlist!', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Product Removed Message', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_product_removed_message" value="<?php echo esc_attr( get_option( 'airy_wishlist_product_removed_message', __( 'Product removed from wishlist.', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( '"Add to Cart" Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_add_to_cart_text" value="<?php echo esc_attr( get_option( 'airy_wishlist_add_to_cart_text', __( 'Add to Cart', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Empty Wishlist Text', 'airy-wishlist' ); ?></label>
					</th>
					<td>
						<input type="text" name="airy_wishlist_empty_text" value="<?php echo esc_attr( get_option( 'airy_wishlist_empty_text', __( 'Your wishlist is empty.', 'airy-wishlist' ) ) ); ?>" class="regular-text">
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}
}