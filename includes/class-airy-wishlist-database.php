<?php
/**
 * Database Handler
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom-table database access for wishlists and items.
 */
class Airy_Wishlist_Database {

	/**
	 * Single instance of the class.
	 *
	 * @var Airy_Wishlist_Database|null
	 */
	private static $instance = null;

	/**
	 * Per-request cache of wishlist rows keyed by ID.
	 *
	 * Avoids duplicate "SELECT ... WHERE id = X" queries within a single request.
	 *
	 * @var array
	 */
	private $wishlist_cache = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Airy_Wishlist_Database
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get wishlist table name
	 */
	public function get_wishlist_table() {
		global $wpdb;
		return $wpdb->prefix . 'airy_wishlist';
	}

	/**
	 * Get wishlist items table name
	 */
	public function get_items_table() {
		global $wpdb;
		return $wpdb->prefix . 'airy_wishlist_items';
	}

	/**
	 * Get or create the default wishlist for a user/guest.
	 *
	 * @param int    $user_id    User ID (0 for guest).
	 * @param string $session_id Guest session ID.
	 * @return object|false Wishlist row, or false if neither identifier is provided.
	 */
	public function get_or_create_wishlist( $user_id = 0, $session_id = '' ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		if ( $user_id > 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
			$wishlist = $wpdb->get_row(
				$wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
					"SELECT * FROM $table WHERE user_id = %d AND is_default = 1",
					$user_id
				)
			);
		} elseif ( ! empty( $session_id ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
			$wishlist = $wpdb->get_row(
				$wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
					"SELECT * FROM $table WHERE session_id = %s AND is_default = 1",
					$session_id
				)
			);
		} else {
			return false;
		}

		// Create if it doesn't exist.
		if ( ! $wishlist ) {
			$data = array(
				'user_id'       => $user_id > 0 ? $user_id : null,
				'session_id'    => ! empty( $session_id ) ? $session_id : null,
				'wishlist_name' => __( 'My Wishlist', 'airy-wishlist' ),
				'is_default'    => 1,
				'date_created'  => current_time( 'mysql' ),
				'date_modified' => current_time( 'mysql' ),
			);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table insert
			$wpdb->insert( $table, $data );
			$wishlist_id = $wpdb->insert_id;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
			$wishlist = $wpdb->get_row(
				$wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
					"SELECT * FROM $table WHERE id = %d",
					$wishlist_id
				)
			);
		}

		return $wishlist;
	}

	/**
	 * Add an item to a wishlist.
	 *
	 * @param int $wishlist_id  Wishlist ID.
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Optional variation ID.
	 * @return bool True if added, false if it already existed or on failure.
	 */
	public function add_item( $wishlist_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = $this->get_items_table();

		// Check if it already exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, checking for duplicates
		$exists = $wpdb->get_var(
			$wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
				"SELECT id FROM $table WHERE wishlist_id = %d AND product_id = %d AND variation_id = %d",
				$wishlist_id,
				$product_id,
				$variation_id
			)
		);

		if ( $exists ) {
			return false;
		}

		$data = array(
			'wishlist_id'  => $wishlist_id,
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => 1,
			'date_added'   => current_time( 'mysql' ),
		);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table insert
		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
			$this->update_wishlist_modified( $wishlist_id );
			do_action( 'airy_wishlist_item_added', $wishlist_id, $product_id, $variation_id );
			return true;
		}

		return false;
	}

	/**
	 * Remove an item from a wishlist.
	 *
	 * @param int $wishlist_id  Wishlist ID.
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Optional variation ID.
	 * @return bool True on success.
	 */
	public function remove_item( $wishlist_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete, cache invalidated via update_wishlist_modified
		$result = $wpdb->delete(
			$table,
			array(
				'wishlist_id'  => $wishlist_id,
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			),
			array( '%d', '%d', '%d' )
		);

		if ( $result ) {
			$this->update_wishlist_modified( $wishlist_id );
			do_action( 'airy_wishlist_item_removed', $wishlist_id, $product_id, $variation_id );
			return true;
		}

		return false;
	}

	/**
	 * Get all items in a wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return array
	 */
	public function get_wishlist_items( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
		$items = $wpdb->get_results(
			$wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
				"SELECT * FROM $table WHERE wishlist_id = %d ORDER BY date_added DESC",
				$wishlist_id
			)
		);

		return apply_filters( 'airy_wishlist_page_items', $items, $wishlist_id );
	}

	/**
	 * Get the item count for a wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return int
	 */
	public function get_item_count( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
		$count = $wpdb->get_var(
			$wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
				"SELECT COUNT(*) FROM $table WHERE wishlist_id = %d",
				$wishlist_id
			)
		);

		return absint( $count );
	}

	/**
	 * Check whether a product is in a wishlist.
	 *
	 * @param int $wishlist_id  Wishlist ID.
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Optional variation ID.
	 * @return bool
	 */
	public function is_product_in_wishlist( $wishlist_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
		$exists = $wpdb->get_var(
			$wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
				"SELECT id FROM $table WHERE wishlist_id = %d AND product_id = %d AND variation_id = %d",
				$wishlist_id,
				$product_id,
				$variation_id
			)
		);

		return (bool) $exists;
	}

	/**
	 * Get all wishlists belonging to a user or guest session.
	 *
	 * @param int    $user_id    User ID (0 for guest).
	 * @param string $session_id Guest session ID.
	 * @return array Array of wishlist row objects (default first, then oldest first).
	 */
	public function get_user_wishlists( $user_id = 0, $session_id = '' ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
			return $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method.
					"SELECT * FROM $table WHERE user_id = %d ORDER BY is_default DESC, date_created ASC",
					$user_id
				)
			);
		}

		if ( ! empty( $session_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
			return $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method.
					"SELECT * FROM $table WHERE session_id = %s ORDER BY is_default DESC, date_created ASC",
					$session_id
				)
			);
		}

		return array();
	}

	/**
	 * Get a single wishlist by ID.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return object|null
	 */
	public function get_wishlist_by_id( $wishlist_id ) {
		$wishlist_id = absint( $wishlist_id );
		if ( ! $wishlist_id ) {
			return null;
		}

		// Return the per-request cached row if we've already fetched it.
		if ( array_key_exists( $wishlist_id, $this->wishlist_cache ) ) {
			return $this->wishlist_cache[ $wishlist_id ];
		}

		global $wpdb;
		$table = $this->get_wishlist_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method.
				"SELECT * FROM $table WHERE id = %d",
				$wishlist_id
			)
		);

		$this->wishlist_cache[ $wishlist_id ] = $row;

		return $row;
	}

	/**
	 * Clear the per-request wishlist-row cache.
	 *
	 * @param int $wishlist_id Specific ID to clear, or 0 to clear all.
	 * @return void
	 */
	private function clear_wishlist_cache( $wishlist_id = 0 ) {
		$wishlist_id = absint( $wishlist_id );

		if ( $wishlist_id ) {
			unset( $this->wishlist_cache[ $wishlist_id ] );
		} else {
			$this->wishlist_cache = array();
		}
	}

	/**
	 * Create a new (non-default) wishlist.
	 *
	 * @param int    $user_id    User ID (0 for guest).
	 * @param string $session_id Guest session ID.
	 * @param string $name       Wishlist name.
	 * @return int New wishlist ID, or 0 on failure.
	 */
	public function create_wishlist( $user_id, $session_id, $name ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		$data = array(
			'user_id'       => $user_id > 0 ? $user_id : null,
			'session_id'    => ! empty( $session_id ) ? $session_id : null,
			'wishlist_name' => $name,
			'is_default'    => 0,
			'date_created'  => current_time( 'mysql' ),
			'date_modified' => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table insert.
		$result = $wpdb->insert( $table, $data );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Rename a wishlist.
	 *
	 * @param int    $wishlist_id Wishlist ID.
	 * @param string $name        New name.
	 * @return bool
	 */
	public function rename_wishlist( $wishlist_id, $name ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$result = $wpdb->update(
			$table,
			array(
				'wishlist_name' => $name,
				'date_modified' => current_time( 'mysql' ),
			),
			array( 'id' => $wishlist_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$this->clear_wishlist_cache( $wishlist_id );

		return false !== $result;
	}

	/**
	 * Delete a wishlist and all of its items.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return bool
	 */
	public function delete_wishlist( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_wishlist_table();
		$items = $this->get_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $items, array( 'wishlist_id' => $wishlist_id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete( $table, array( 'id' => $wishlist_id ), array( '%d' ) );

		$this->clear_wishlist_cache( $wishlist_id );

		if ( $result ) {
			do_action( 'airy_wishlist_deleted', $wishlist_id );
		}

		return (bool) $result;
	}

	/**
	 * Get IDs of logged-in users who have a given product in any wishlist.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public function get_users_who_wishlisted( $product_id ) {
		global $wpdb;
		$wishlist_table = $this->get_wishlist_table();
		$items_table    = $this->get_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe, come from internal methods.
				"SELECT DISTINCT w.user_id FROM $items_table i INNER JOIN $wishlist_table w ON i.wishlist_id = w.id WHERE i.product_id = %d AND w.user_id IS NOT NULL AND w.user_id > 0",
				$product_id
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Get products that are in at least one logged-in user's wishlist,
	 * with the number of distinct users per product.
	 *
	 * @param int $limit Max rows.
	 * @return array Rows of { product_id, users }.
	 */
	public function get_wishlisted_products_with_user_counts( $limit = 200 ) {
		global $wpdb;
		$wishlist_table = $this->get_wishlist_table();
		$items_table    = $this->get_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table aggregate query.
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe, come from internal methods.
				"SELECT i.product_id, COUNT(DISTINCT w.user_id) AS users FROM $items_table i INNER JOIN $wishlist_table w ON i.wishlist_id = w.id WHERE w.user_id IS NOT NULL AND w.user_id > 0 GROUP BY i.product_id HAVING users > 0 ORDER BY users DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get the total item count across all of an owner's wishlists in one query.
	 *
	 * @param int    $user_id    User ID (0 for guest).
	 * @param string $session_id Guest session ID.
	 * @return int
	 */
	public function get_total_count_for( $user_id = 0, $session_id = '' ) {
		global $wpdb;
		$wishlist_table = $this->get_wishlist_table();
		$items_table    = $this->get_items_table();

		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table aggregate query.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe, come from internal methods.
					"SELECT COUNT(*) FROM $items_table i INNER JOIN $wishlist_table w ON i.wishlist_id = w.id WHERE w.user_id = %d",
					$user_id
				)
			);
		} elseif ( ! empty( $session_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table aggregate query.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe, come from internal methods.
					"SELECT COUNT(*) FROM $items_table i INNER JOIN $wishlist_table w ON i.wishlist_id = w.id WHERE w.session_id = %s",
					$session_id
				)
			);
		} else {
			return 0;
		}

		return absint( $count );
	}

	/**
	 * Get a wishlist by its public share token.
	 *
	 * @param string $token Share token.
	 * @return object|null Wishlist row or null if not found.
	 */
	public function get_wishlist_by_token( $token ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		if ( empty( $token ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup by share token.
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method.
				"SELECT * FROM $table WHERE share_token = %s",
				$token
			)
		);
	}

	/**
	 * Get the share token for a wishlist, generating one if needed.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return string Share token.
	 */
	public function ensure_share_token( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$token = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method.
				"SELECT share_token FROM $table WHERE id = %d",
				$wishlist_id
			)
		);

		if ( empty( $token ) ) {
			$token = wp_generate_password( 24, false );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$wpdb->update(
				$table,
				array( 'share_token' => $token ),
				array( 'id' => $wishlist_id ),
				array( '%s' ),
				array( '%d' )
			);

			$this->clear_wishlist_cache( $wishlist_id );
		}

		return $token;
	}

	/**
	 * Get all wishlists that have notifications enabled (logged-in users only).
	 *
	 * @return array
	 */
	public function get_notify_enabled_wishlists() {
		global $wpdb;
		$table = $this->get_wishlist_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query; no user input.
		return $wpdb->get_results( "SELECT * FROM $table WHERE notifications_enabled = 1 AND user_id IS NOT NULL" );
	}

	/**
	 * Enable or disable notifications for a wishlist.
	 *
	 * @param int  $wishlist_id Wishlist ID.
	 * @param bool $enabled     Whether to enable.
	 * @return bool
	 */
	public function set_wishlist_notifications( $wishlist_id, $enabled ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$result = $wpdb->update(
			$table,
			array( 'notifications_enabled' => $enabled ? 1 : 0 ),
			array( 'id' => $wishlist_id ),
			array( '%d' ),
			array( '%d' )
		);

		$this->clear_wishlist_cache( $wishlist_id );

		return false !== $result;
	}

	/**
	 * Persist a notification state snapshot for an item.
	 *
	 * @param int         $item_id       Item row ID.
	 * @param string      $snapshot_json JSON-encoded snapshot.
	 * @param string|null $last_notified MySQL datetime, or null to leave unchanged.
	 * @return void
	 */
	public function update_item_snapshot( $item_id, $snapshot_json, $last_notified = null ) {
		global $wpdb;
		$table = $this->get_items_table();

		$data    = array( 'notify_snapshot' => $snapshot_json );
		$formats = array( '%s' );

		if ( null !== $last_notified ) {
			$data['last_notified'] = $last_notified;
			$formats[]             = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$wpdb->update( $table, $data, array( 'id' => $item_id ), $formats, array( '%d' ) );
	}

	/**
	 * Update a wishlist's modified date.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return void
	 */
	private function update_wishlist_modified( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update, cache invalidated
		$wpdb->update(
			$table,
			array( 'date_modified' => current_time( 'mysql' ) ),
			array( 'id' => $wishlist_id ),
			array( '%s' ),
			array( '%d' )
		);

		$this->clear_wishlist_cache( $wishlist_id );
	}

	/**
	 * Merge a guest's wishlists into a user account on login.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $session_id Guest session ID.
	 * @return void
	 */
	public function merge_wishlists( $user_id, $session_id ) {
		global $wpdb;
		$wishlist_table = $this->get_wishlist_table();
		$items_table    = $this->get_items_table();

		// Ownership/rows change here; drop any cached rows so later reads are fresh.
		$this->clear_wishlist_cache();

		// Get all guest wishlists for this session.
		$guest_wishlists = $this->get_user_wishlists( 0, $session_id );

		if ( empty( $guest_wishlists ) ) {
			return;
		}

		// Ensure the user has a default wishlist to merge the guest default into.
		$user_wishlist = $this->get_or_create_wishlist( $user_id );

		foreach ( $guest_wishlists as $guest_wishlist ) {
			if ( $guest_wishlist->is_default ) {
				// Merge guest default items into the user's default wishlist.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for merging wishlists.
				$guest_items = $wpdb->get_results(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method.
						"SELECT * FROM $items_table WHERE wishlist_id = %d",
						$guest_wishlist->id
					)
				);

				foreach ( $guest_items as $item ) {
					$exists = $this->is_product_in_wishlist(
						$user_wishlist->id,
						$item->product_id,
						$item->variation_id
					);

					if ( ! $exists ) {
						$this->add_item(
							$user_wishlist->id,
							$item->product_id,
							$item->variation_id
						);
					}
				}

				// Remove the now-merged guest default wishlist and its items.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete after merging.
				$wpdb->delete( $items_table, array( 'wishlist_id' => $guest_wishlist->id ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete after merging.
				$wpdb->delete( $wishlist_table, array( 'id' => $guest_wishlist->id ) );
			} else {
				// Re-assign additional guest wishlists to the logged-in user.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update transferring ownership.
				$wpdb->update(
					$wishlist_table,
					array(
						'user_id'    => $user_id,
						'session_id' => null,
					),
					array( 'id' => $guest_wishlist->id ),
					array( '%d', '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Remove all items from a wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return int|false Number of rows deleted, or false on failure.
	 */
	public function clear_wishlist( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete, cache invalidated via update_wishlist_modified
		$result = $wpdb->delete( $table, array( 'wishlist_id' => $wishlist_id ), array( '%d' ) );

		if ( $result ) {
			$this->update_wishlist_modified( $wishlist_id );
			do_action( 'airy_wishlist_cleared', $wishlist_id );
		}

		return $result;
	}
}
