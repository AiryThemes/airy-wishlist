<?php
/**
 * Installation and Upgrade Handler
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin installation and database setup class
 */
class Airy_Wishlist_Install {

	/**
	 * Database schema version.
	 *
	 * Bump this whenever the table structure changes so that
	 * maybe_upgrade() runs the relevant migrations on existing sites.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.2.0';

	/**
	 * Plugin activation
	 */
	public static function activate() {
		self::create_tables();
		self::run_migrations();
		self::create_default_options();
		self::create_wishlist_page();

		// Register the My Account endpoint so the rewrite rule is created on flush.
		add_rewrite_endpoint( 'wishlist', EP_ROOT | EP_PAGES );

		// Set activation flag.
		update_option( 'airy_wishlist_version', AIRY_WISHLIST_VERSION );
		update_option( 'airy_wishlist_db_version', self::DB_VERSION );
		update_option( 'airy_wishlist_activated', current_time( 'mysql' ) );
		update_option( 'airy_wishlist_myaccount_flushed', 1, false );

		flush_rewrite_rules();
	}

	/**
	 * Run schema upgrades on existing installs.
	 *
	 * Called on every admin load; the version gate makes it a no-op
	 * unless the stored DB version is behind the current one.
	 */
	public static function maybe_upgrade() {
		$stored = get_option( 'airy_wishlist_db_version', '1.0.0' );

		if ( version_compare( $stored, self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();
		self::run_migrations();

		update_option( 'airy_wishlist_db_version', self::DB_VERSION );
	}

	/**
	 * Apply incremental schema migrations.
	 *
	 * Each migration is guarded so it is safe to run repeatedly.
	 */
	private static function run_migrations() {
		global $wpdb;
		$table = $wpdb->prefix . 'airy_wishlist';
		$items = $wpdb->prefix . 'airy_wishlist_items';

		// 1.1.0 - Public share token for shareable wishlists.
		if ( ! self::column_exists( $table, 'share_token' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change on internal table; column/table names are not user input.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN share_token varchar(64) DEFAULT NULL, ADD KEY share_token (share_token)" );
		}

		// 1.2.0 - Stock/price notification opt-in and per-item state snapshot.
		if ( ! self::column_exists( $table, 'notifications_enabled' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change on internal table; column/table names are not user input.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN notifications_enabled tinyint(1) DEFAULT 0" );
		}
		if ( ! self::column_exists( $items, 'notify_snapshot' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change on internal table; column/table names are not user input.
			$wpdb->query( "ALTER TABLE {$items} ADD COLUMN notify_snapshot text DEFAULT NULL" );
		}
		if ( ! self::column_exists( $items, 'last_notified' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change on internal table; column/table names are not user input.
			$wpdb->query( "ALTER TABLE {$items} ADD COLUMN last_notified datetime DEFAULT NULL" );
		}
	}

	/**
	 * Check whether a column exists on a table.
	 *
	 * @param string $table  Full table name.
	 * @param string $column Column name.
	 * @return bool
	 */
	private static function column_exists( $table, $column ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema inspection on internal table; table name is not user input.
		$found = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );

		return ! empty( $found );
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		// Clear the scheduled notification check.
		$timestamp = wp_next_scheduled( 'airy_wishlist_check_notifications' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'airy_wishlist_check_notifications' );
		}
		wp_clear_scheduled_hook( 'airy_wishlist_check_notifications' );

		flush_rewrite_rules();
	}

	/**
	 * Create database tables
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_wishlist  = $wpdb->prefix . 'airy_wishlist';
		$table_items     = $wpdb->prefix . 'airy_wishlist_items';

		// Wishlist table (for future multi-wishlist support).
		$sql_wishlist = "CREATE TABLE IF NOT EXISTS $table_wishlist (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(255) DEFAULT NULL,
            wishlist_name varchar(255) DEFAULT 'My Wishlist',
            is_default tinyint(1) DEFAULT 1,
            share_token varchar(64) DEFAULT NULL,
            notifications_enabled tinyint(1) DEFAULT 0,
            date_created datetime NOT NULL,
            date_modified datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY share_token (share_token)
        ) $charset_collate;";

		// Wishlist items table.
		$sql_items = "CREATE TABLE IF NOT EXISTS $table_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wishlist_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned DEFAULT 0,
            quantity int(11) DEFAULT 1,
            notify_snapshot text DEFAULT NULL,
            last_notified datetime DEFAULT NULL,
            date_added datetime NOT NULL,
            PRIMARY KEY (id),
            KEY wishlist_id (wishlist_id),
            KEY product_id (product_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_wishlist );
		dbDelta( $sql_items );
	}

	/**
	 * Create default plugin options
	 */
	private static function create_default_options() {
		$defaults = array(
			// General settings.
			'airy_wishlist_enable_ajax'              => 'yes',
			'airy_wishlist_page_id'                  => '',
			'airy_wishlist_redirect_after_add'       => 'no',
			'airy_wishlist_remove_after_add_to_cart' => 'no',
			'airy_wishlist_button_toggle'            => 'yes',
			'airy_wishlist_guest_enabled'            => 'yes',
			'airy_wishlist_cookie_expiry'            => 30,
			'airy_wishlist_multiple_enabled'         => 'no',
			'airy_wishlist_myaccount_enabled'        => 'yes',

			// Notifications.
			'airy_wishlist_notify_enabled'           => 'no',
			'airy_wishlist_notify_back_in_stock'     => 'yes',
			'airy_wishlist_notify_price_drop'        => 'yes',
			'airy_wishlist_notify_low_stock'         => 'no',
			'airy_wishlist_notify_on_sale'           => 'yes',
			'airy_wishlist_notify_frequency'         => 'daily',
			'airy_wishlist_notify_subject'           => __( 'Updates on your wishlist items', 'airy-wishlist' ),

			// Add to Wishlist Button.
			'airy_wishlist_button_position'          => 'after_add_to_cart',
			'airy_wishlist_button_type'              => 'icon_text',
			'airy_wishlist_button_icon'              => 'heart',
			'airy_wishlist_show_on_loop'             => 'yes',
			'airy_wishlist_loop_position'            => 'after_add_to_cart',

			// Button Colors.
			'airy_wishlist_button_bg_color'          => '#ffffff',
			'airy_wishlist_button_text_color'        => '#333333',
			'airy_wishlist_button_bg_color_hover'    => '#f8f8f8',
			'airy_wishlist_button_text_color_hover'  => '#000000',
			'airy_wishlist_button_added_bg_color'    => '#e74c3c',
			'airy_wishlist_button_added_text_color'  => '#ffffff',

			// Wishlist Page.
			'airy_wishlist_page_layout'              => 'table',
			'airy_wishlist_show_stock_status'        => 'yes',
			'airy_wishlist_show_date_added'          => 'yes',
			'airy_wishlist_show_add_to_cart'         => 'yes',
			'airy_wishlist_show_remove_button'       => 'yes',
			'airy_wishlist_show_add_all_to_cart'     => 'yes',

			// Social Sharing.
			'airy_wishlist_enable_share'             => 'yes',
			'airy_wishlist_share_facebook'           => 'yes',
			'airy_wishlist_share_twitter'            => 'yes',
			'airy_wishlist_share_pinterest'          => 'yes',
			'airy_wishlist_share_whatsapp'           => 'yes',
			'airy_wishlist_share_email'              => 'yes',

			// Labels // Labels & Text Text.
			'airy_wishlist_add_button_text'          => __( 'Add to Wishlist', 'airy-wishlist' ),
			'airy_wishlist_added_button_text'        => __( 'Added to Wishlist', 'airy-wishlist' ),
			'airy_wishlist_remove_button_text'       => __( 'Remove', 'airy-wishlist' ),
			'airy_wishlist_view_wishlist_text'       => __( 'View Wishlist', 'airy-wishlist' ),
			'airy_wishlist_product_added_message'    => __( 'Product added to wishlist!', 'airy-wishlist' ),
			'airy_wishlist_product_removed_message'  => __( 'Product removed from wishlist.', 'airy-wishlist' ),
			'airy_wishlist_add_to_cart_text'         => __( 'Add to Cart', 'airy-wishlist' ),
			'airy_wishlist_empty_text'               => __( 'Your wishlist is empty.', 'airy-wishlist' ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Create wishlist page
	 */
	private static function create_wishlist_page() {
		$page_id = get_option( 'airy_wishlist_page_id' );

		// Check if page exists.
		if ( $page_id && get_post( $page_id ) ) {
			return;
		}

		// Create new page. Use the activating admin as author, falling back to user 1.
		$author_id = get_current_user_id();
		if ( ! $author_id ) {
			$author_id = 1;
		}

		$page_data = array(
			'post_title'     => __( 'My Wishlist', 'airy-wishlist' ),
			'post_content'   => '[airy_wishlist]',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_author'    => $author_id,
			'comment_status' => 'closed',
		);

		$page_id = wp_insert_post( $page_data );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'airy_wishlist_page_id', $page_id );
		}
	}
}
