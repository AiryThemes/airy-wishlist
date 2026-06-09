<?php
/**
 * Database Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Airy_Wishlist_Database {

	private static $instance = null;

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
	 * Get or create wishlist for user/guest
	 */
	public function get_or_create_wishlist( $user_id = 0, $session_id = '' ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

		if ( $user_id > 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
			$wishlist = $wpdb->get_row(
				$wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
					"SELECT * FROM $table WHERE user_id = %d AND is_default = 1",
					$user_id
				)
			);
		} elseif ( ! empty( $session_id ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
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

		// Create if doesn't exist
		if ( ! $wishlist ) {
			$data = array(
				'user_id'       => $user_id > 0 ? $user_id : null,
				'session_id'    => ! empty( $session_id ) ? $session_id : null,
				'wishlist_name' => __( 'My Wishlist', 'airy-wishlist' ),
				'is_default'    => 1,
				'date_created'  => current_time( 'mysql' ),
				'date_modified' => current_time( 'mysql' ),
			);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
			$wpdb->insert( $table, $data );
			$wishlist_id = $wpdb->insert_id;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
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
	 * Add item to wishlist
	 */
	public function add_item( $wishlist_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = $this->get_items_table();

		// Check if already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, checking for duplicates
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
			$this->update_wishlist_modified( $wishlist_id );
			do_action( 'airy_wishlist_item_added', $wishlist_id, $product_id, $variation_id );
			return true;
		}

		return false;
	}

	/**
	 * Remove item from wishlist
	 */
	public function remove_item( $wishlist_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete, cache invalidated via update_wishlist_modified
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
	 * Get wishlist items
	 */
	public function get_wishlist_items( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
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
	 * Get wishlist item count
	 */
	public function get_item_count( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
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
	 * Check if product is in wishlist
	 */
	public function is_product_in_wishlist( $wishlist_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching handled at application level
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
	 * Update wishlist modified date
	 */
	private function update_wishlist_modified( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_wishlist_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update, cache invalidated
		$wpdb->update(
			$table,
			array( 'date_modified' => current_time( 'mysql' ) ),
			array( 'id' => $wishlist_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Merge guest wishlist to user wishlist on login
	 */
	public function merge_wishlists( $user_id, $session_id ) {
		global $wpdb;
		$wishlist_table = $this->get_wishlist_table();
		$items_table    = $this->get_items_table();

		// Get guest wishlist
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for merging wishlists
		$guest_wishlist = $wpdb->get_row(
			$wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
				"SELECT * FROM $wishlist_table WHERE session_id = %s",
				$session_id
			)
		);

		if ( ! $guest_wishlist ) {
			return;
		}

		// Get or create user wishlist
		$user_wishlist = $this->get_or_create_wishlist( $user_id );

		// Get guest items
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for merging wishlists
		$guest_items = $wpdb->get_results(
			$wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from internal method
				"SELECT * FROM $items_table WHERE wishlist_id = %d",
				$guest_wishlist->id
			)
		);

		// Move items to user wishlist
		foreach ( $guest_items as $item ) {
			// Check if item already exists in user wishlist
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

		// Delete guest wishlist and items
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete after merging
		$wpdb->delete( $items_table, array( 'wishlist_id' => $guest_wishlist->id ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete after merging
		$wpdb->delete( $wishlist_table, array( 'id' => $guest_wishlist->id ) );
	}

	/**
	 * Clear wishlist
	 */
	public function clear_wishlist( $wishlist_id ) {
		global $wpdb;
		$table = $this->get_items_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete, cache invalidated via update_wishlist_modified
		$result = $wpdb->delete( $table, array( 'wishlist_id' => $wishlist_id ), array( '%d' ) );

		if ( $result ) {
			$this->update_wishlist_modified( $wishlist_id );
			do_action( 'airy_wishlist_cleared', $wishlist_id );
		}

		return $result;
	}
}
