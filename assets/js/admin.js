/**
 * Airy WooCommerce Wishlist - Admin JavaScript
 *
 * @package Airy_Wishlist
 */

(function ($) {
	'use strict';

	$( document ).ready(
		function () {
			// Initialize color pickers.
			if ($.fn.wpColorPicker) {
				$( '.airy-color-picker' ).wpColorPicker();
			}

			// Save settings notification.
			$( 'form' ).on(
				'submit',
				function () {
					const submitBtn = $( this ).find( 'input[type="submit"]' );
					submitBtn.val( 'Saving...' );
				}
			);
		}
	);

})( jQuery );