<?php
/**
 * Helper Functions
 *
 * @package Airy_Wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get wishlist URL
 */
function airy_wishlist_get_url() {
	$data = Airy_Wishlist_Data::instance();
	return $data->get_wishlist_url();
}

/**
 * Get wishlist count
 */
function airy_wishlist_get_count() {
	$data = Airy_Wishlist_Data::instance();
	return $data->get_count();
}

/**
 * Get a public, shareable URL for the current user's wishlist.
 *
 * @return string Shareable URL.
 */
function airy_wishlist_get_share_url() {
	$data = Airy_Wishlist_Data::instance();
	return $data->get_share_url();
}

/**
 * Check if product is in wishlist
 *
 * @param int $product_id Product ID to check.
 * @param int $variation_id Optional variation ID.
 * @return bool True if product is in wishlist.
 */
function airy_wishlist_is_product_in_wishlist( $product_id, $variation_id = 0 ) {
	$data = Airy_Wishlist_Data::instance();
	return $data->is_in_wishlist( $product_id, $variation_id );
}

/**
 * Get add to wishlist button HTML
 *
 * @param int    $product_id Product ID.
 * @param string $context    Display context - 'single' or 'loop'.
 * @return string Button HTML.
 */
function airy_wishlist_get_button_html( $product_id, $context = 'single' ) {
	// Hide the button entirely for guests when guest wishlists are disabled.
	if ( ! Airy_Wishlist_Data::instance()->is_enabled_for_visitor() ) {
		return '';
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return '';
	}

	// For variable products in loop, don't show button (needs variation selection).
	if ( 'loop' === $context && $product->is_type( 'variable' ) ) {
		return '';
	}

	// For grouped products, don't show button (complex logic).
	if ( $product->is_type( 'grouped' ) ) {
		return '';
	}

	$data        = Airy_Wishlist_Data::instance();
	$in_wishlist = $data->is_in_wishlist( $product_id );

	$button_type = get_option( 'airy_wishlist_button_type', 'icon_text' );
	$icon        = get_option( 'airy_wishlist_button_icon', 'heart' );
	$add_text    = get_option( 'airy_wishlist_add_button_text', __( 'Add to Wishlist', 'airy-wishlist' ) );
	$added_text  = get_option( 'airy_wishlist_added_button_text', __( 'Added to Wishlist', 'airy-wishlist' ) );

	$classes = array( 'airy-wishlist-btn' );
	if ( $in_wishlist ) {
		$classes[] = 'added';
	}

	// For variable products, add disabled state initially.
	$is_variable     = 'single' === $context && $product->is_type( 'variable' );
	$button_style    = '';
	$button_disabled = '';

	if ( $is_variable ) {
		$button_style    = ' style="display:none;"'; // Hide initially.
		$button_disabled = ' disabled="disabled"'; // Also disable.
		$classes[]       = 'airy-variable-disabled';
	}

	$button_html  = '<div class="airy-add-to-wishlist airy-context-' . esc_attr( $context ) . '"' . $button_style . '>';
	$button_html .= '<button type="button" class="' . esc_attr( implode( ' ', $classes ) ) . '" ';
	$button_html .= 'data-product-id="' . esc_attr( $product_id ) . '" ';
	$button_html .= 'data-variation-id="0"';

	// For variable products on single page, we'll get variation from form.
	if ( $is_variable ) {
		$button_html .= ' data-is-variable="yes"';
	}

	$button_html .= $button_disabled;
	$button_html .= '>';

	if ( 'icon' === $button_type || 'icon_text' === $button_type ) {
		$button_html .= '<span class="airy-wishlist-icon">' . airy_wishlist_get_icon( $icon ) . '</span>';
	}

	if ( 'text' === $button_type || 'icon_text' === $button_type ) {
		$button_html .= '<span class="airy-wishlist-text">';
		$button_html .= $in_wishlist ? esc_html( $added_text ) : esc_html( $add_text );
		$button_html .= '</span>';
	}

	$button_html .= '</button>';
	$button_html .= '</div>';

	return apply_filters( 'airy_wishlist_button_html', $button_html, $product_id, $in_wishlist );
}

/**
 * Get icon SVG
 *
 * @param string $icon Icon type - 'heart', 'star', or 'bookmark'.
 * @return string SVG HTML.
 */
function airy_wishlist_get_icon( $icon = 'heart' ) {
	$icons = array(
		'heart'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
		'star'     => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
		'bookmark' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>',
	);

	return isset( $icons[ $icon ] ) ? $icons[ $icon ] : $icons['heart'];
}

/**
 * Escape SVG output - allows SVG tags that wp_kses_post strips
 *
 * @param string $svg SVG HTML to escape.
 * @return string Escaped SVG HTML.
 */
function airy_wishlist_kses_svg( $svg ) {
	$allowed_tags = array(
		'svg'     => array(
			'xmlns'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
		),
		'path'    => array(
			'd'      => true,
			'fill'   => true,
			'stroke' => true,
		),
		'polygon' => array(
			'points' => true,
			'fill'   => true,
			'stroke' => true,
		),
		'circle'  => array(
			'cx'     => true,
			'cy'     => true,
			'r'      => true,
			'fill'   => true,
			'stroke' => true,
		),
		'rect'    => array(
			'x'      => true,
			'y'      => true,
			'width'  => true,
			'height' => true,
			'fill'   => true,
			'stroke' => true,
		),
		'line'    => array(
			'x1'     => true,
			'y1'     => true,
			'x2'     => true,
			'y2'     => true,
			'stroke' => true,
		),
		'g'       => array(
			'fill'   => true,
			'stroke' => true,
		),
	);

	return wp_kses( $svg, $allowed_tags );
}

/**
 * Escape button HTML with SVG support
 *
 * @param string $html Button HTML to escape.
 * @return string Escaped button HTML.
 */
function airy_wishlist_kses_button( $html ) {
	$allowed_tags = array(
		'div'     => array(
			'class' => true,
			'style' => true,
		),
		'button'  => array(
			'type'              => true,
			'class'             => true,
			'data-product-id'   => true,
			'data-variation-id' => true,
			'data-is-variable'  => true,
			'disabled'          => true,
		),
		'span'    => array(
			'class' => true,
		),
		'svg'     => array(
			'xmlns'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
		),
		'path'    => array(
			'd'      => true,
			'fill'   => true,
			'stroke' => true,
		),
		'polygon' => array(
			'points' => true,
			'fill'   => true,
			'stroke' => true,
		),
		'circle'  => array(
			'cx'     => true,
			'cy'     => true,
			'r'      => true,
			'fill'   => true,
			'stroke' => true,
		),
	);

	return wp_kses( $html, $allowed_tags );
}

/**
 * Add product to wishlist
 *
 * @param int $product_id Product ID to add.
 * @param int $variation_id Optional variation ID.
 * @return bool True if added successfully.
 */
function airy_wishlist_add_product( $product_id, $variation_id = 0 ) {
	$data = Airy_Wishlist_Data::instance();
	return $data->add_to_wishlist( $product_id, $variation_id );
}

/**
 * Remove product from wishlist
 *
 * @param int $product_id Product ID to remove.
 * @param int $variation_id Optional variation ID.
 * @return bool True if removed successfully.
 */
function airy_wishlist_remove_product( $product_id, $variation_id = 0 ) {
	$data = Airy_Wishlist_Data::instance();
	return $data->remove_from_wishlist( $product_id, $variation_id );
}

/**
 * Get wishlist items
 */
function airy_wishlist_get_items() {
	$data = Airy_Wishlist_Data::instance();
	return $data->get_items();
}

/**
 * Clear wishlist
 */
function airy_wishlist_clear() {
	$data = Airy_Wishlist_Data::instance();
	return $data->clear_wishlist();
}
