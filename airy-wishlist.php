<?php
/**
 * Plugin Name: Airy Wishlist for WooCommerce
 * Description: A powerful and user-friendly wishlist plugin for WooCommerce. Add products to wishlist, share with friends, and more!
 * Version: 2.0.0
 * Author: NXlogy
 * Author URI: https://nxlogy.com
 * Text Domain: airy-wishlist
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AIRY_WISHLIST_VERSION', '2.0.0' );
define( 'AIRY_WISHLIST_FILE', __FILE__ );
define( 'AIRY_WISHLIST_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIRY_WISHLIST_URL', plugin_dir_url( __FILE__ ) );
define( 'AIRY_WISHLIST_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Airy_Wishlist Class
 */
final class Airy_Wishlist {

	/**
	 * Single instance of the class
	 *
	 * @var Airy_Wishlist|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->declare_hpos_compatibility();
		$this->init_hooks();
		$this->includes();
	}

	/**
	 * Declare HPOS compatibility
	 */
	private function declare_hpos_compatibility() {
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			}
		);
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Include required files
	 */
	private function includes() {
		// Core files.
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-install.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-database.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-data.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-frontend.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-ajax.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-shortcodes.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-widgets.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-counter-widget.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-analytics.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-notifications.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-marketing.php';
		require_once AIRY_WISHLIST_PATH . 'includes/class-airy-wishlist-my-account.php';

		// Admin files.
		if ( is_admin() ) {
			require_once AIRY_WISHLIST_PATH . 'admin/class-airy-wishlist-admin.php';
		}

		// Helper functions.
		require_once AIRY_WISHLIST_PATH . 'includes/airy-wishlist-functions.php';
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Run any pending database upgrades (no-op once up to date).
		if ( is_admin() ) {
			Airy_Wishlist_Install::maybe_upgrade();
		}

		// Initialize classes.
		Airy_Wishlist_Database::instance();
		Airy_Wishlist_Data::instance();
		Airy_Wishlist_Frontend::instance();
		Airy_Wishlist_Ajax::instance();
		Airy_Wishlist_Shortcodes::instance();
		Airy_Wishlist_Widgets::instance();
		Airy_Wishlist_Analytics::instance();
		Airy_Wishlist_Notifications::instance();
		Airy_Wishlist_Marketing::instance();
		Airy_Wishlist_My_Account::instance();

		if ( is_admin() ) {
			Airy_Wishlist_Admin::instance();
		}

		do_action( 'airy_wishlist_loaded' );
	}

	/**
	 * Load plugin textdomain
	 *
	 * Note: Since WordPress 4.6, translations are automatically loaded from WordPress.org
	 * for plugins hosted there. This method is kept for compatibility with locally hosted plugins.
	 */
	public function load_textdomain() {
		// Translations are automatically loaded by WordPress.org for hosted plugins.
		// load_plugin_textdomain('airy-wishlist', false, dirname(AIRY_WISHLIST_BASENAME) . '/languages').
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		Airy_Wishlist_Install::activate();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		Airy_Wishlist_Install::deactivate();
	}

	/**
	 * WooCommerce missing notice
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Airy Woo Wishlist requires WooCommerce to be installed and active.', 'airy-wishlist' ); ?></p>
		</div>
		<?php
	}
}

// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Main plugin file requires both class and helper function.
/**
 * Initialize the plugin
 *
 * @return Airy_Wishlist
 */
function airy_wishlist() {
	return Airy_Wishlist::instance();
}

// Start the plugin.
airy_wishlist();
