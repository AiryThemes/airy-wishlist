=== Airy Wishlist for WooCommerce ===
Contributors: airythemes
Tags: wishlist, woocommerce wishlist, woo wishlist, product wishlist, add to wishlist
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful and user-friendly wishlist plugin for WooCommerce. Let customers save their favorite products for later!

== Description ==

Airy Wishlist for WooCommerce is a feature-rich, lightweight wishlist plugin that allows your customers to create and manage wishlists of their favorite products. Perfect for any WooCommerce store looking to improve user experience and increase conversions.

### 🚀 Features

**Display Options:**
* Table or grid layout for wishlist page
* Customizable product information display
* Stock status monitoring
* Date added tracking
* Quick "Add to Cart" actions
* Bulk "Add All to Cart" functionality

**Customization:**
* Multiple button styles (icon only, text only, icon + text)
* Three icon choices (heart, star, bookmark)
* Full color customization
* Button position control
* Custom CSS support
* All text labels customizable

**Social Features:**
* Share on Facebook
* Share on Twitter/X
* Share on WhatsApp
* Share via Email
* Customizable sharing titles

**Developer Friendly:**
* Multiple shortcodes available
* Action and filter hooks
* Helper functions for theme integration
* Gutenberg block support
* Clean, commented code
* Extensive documentation

### 📱 Mobile Optimized

Works flawlessly on mobile devices with touch-friendly buttons and responsive layouts.

### 🌐 Compatible With

* ✅ WooCommerce 7.0+
* ✅ WordPress 6.0+
* ✅ PHP 7.4+
* ✅ HPOS (High-Performance Order Storage)
* ✅ WPML (Multilingual)
* ✅ RTL Languages
* ✅ Gutenberg Block Editor
* ✅ All standard WooCommerce themes
* ✅ Page builders (Elementor, Divi, etc.)
* ✅ Caching plugins

### 🔧 Technical Highlights

* Optimized database queries for excellent performance
* No jQuery dependency (vanilla JavaScript)
* Follows WordPress coding standards
* Secure with proper escaping and sanitization
* Cookie-based storage for guests (30 days)
* Gutenberg block included

== Installation ==

### Automatic Installation

1. Log in to your WordPress dashboard
2. Navigate to Plugins > Add New
3. Search for "Airy Woo Wishlist"
4. Click "Install Now"
5. Activate the plugin
6. Go to WooCommerce > Wishlist to configure settings

### Manual Installation

1. Download the plugin ZIP file
2. Log in to your WordPress dashboard
3. Navigate to Plugins > Add New
4. Click "Upload Plugin"
5. Choose the ZIP file and click "Install Now"
6. Activate the plugin
7. Go to WooCommerce > Wishlist to configure settings

### After Activation

1. A wishlist page will be auto-created with `[airy_wishlist]` shortcode
2. Configure settings in WooCommerce > Wishlist
3. Customize button appearance and position
4. Set up social sharing options
5. Customize text labels as needed
6. Use Gutenberg block in any page/post

== Frequently Asked Questions ==

= Does it work with variable products? =

Yes! The plugin fully supports WooCommerce variable products with all variations.

= Can guests use the wishlist? =

Absolutely! Guest wishlists are stored in cookies for 30 days (configurable) and automatically merge when they log in.

= Will it slow down my site? =

Optimized database queries for excellent performance.

= Is it compatible with caching plugins? =

Yes! The plugin uses AJAX for all interactions, making it fully compatible with caching plugins like WP Rocket, W3 Total Cache, etc.

= Can I customize the design? =

Yes! You can customize colors, add custom CSS, and the plugin provides extensive styling options.

= Does it work with page builders? =

Yes! It works perfectly with Elementor, Divi, Breakdance, and other popular page builders. It also includes a Gutenberg block.

= Is it translation ready? =

Yes! The plugin is fully translatable and includes a .pot file in the /languages folder. It's also WPML compatible.

= Does it support WooCommerce HPOS? =

Yes! The plugin is fully compatible with High-Performance Order Storage (HPOS).

= Can I show the wishlist button on shop pages? =

Yes! You can enable wishlist buttons on shop/category pages and customize their position.

= Will wishlists be saved when users log out? =

Yes! For logged-in users, wishlists are stored in the database. For guests, they're saved in cookies for 30 days.

= Can customers share their wishlists? =

Yes! Customers can share their wishlists on Facebook, Twitter, WhatsApp, and via email.

= Does it work with multisite? =

Yes! The plugin works perfectly in WordPress multisite installations.

= Can I add the wishlist button anywhere? =

Yes! Use the `[airy_add_to_wishlist product_id="123"]` shortcode, the Gutenberg block, or helper functions in your theme.

= How do I display the wishlist counter? =

Use the `[airy_wishlist_counter]` shortcode, the included widget, or the Gutenberg block in your sidebar/header.

= Does it include a Gutenberg block? =

Yes! The plugin includes a Gutenberg block for easy integration with the WordPress block editor.

== Changelog ==

= 1.0.1 =
* Initial release

== Shortcodes ==

= Main Wishlist Page =
`[airy_wishlist]`

Display the wishlist with default table layout.

= Grid Layout =
`[airy_wishlist layout="grid" columns="3"]`

Display wishlist in grid format with 3 columns.

= Wishlist Counter =
`[airy_wishlist_counter]`

Display item count with icon.

= Add to Wishlist Button =
`[airy_add_to_wishlist product_id="123"]`

Display add to wishlist button for specific product.

== Gutenberg Block ==

The plugin includes a Gutenberg block for easy integration:

1. Open any page/post in the block editor
2. Click "+" to add a new block
3. Search for "Airy Wishlist"
4. Choose from available blocks:
   - Wishlist Counter
   
== Developer Hooks ==

= Action Hooks =
* `airy_wishlist_before_table` - Before wishlist table
* `airy_wishlist_after_table` - After wishlist table
* `airy_wishlist_item_added` - When item added
* `airy_wishlist_item_removed` - When item removed
* `airy_wishlist_cleared` - When wishlist cleared
* `airy_wishlist_loaded` - When plugin loaded

= Filter Hooks =
* `airy_wishlist_button_html` - Modify button HTML
* `airy_wishlist_page_items` - Modify wishlist items
* `airy_wishlist_item_data` - Modify item data

= Helper Functions =
* `airy_wishlist_get_url()` - Get wishlist page URL
* `airy_wishlist_get_count()` - Get item count
* `airy_wishlist_is_product_in_wishlist($product_id, $variation_id)` - Check if product in wishlist
* `airy_wishlist_add_product($product_id, $variation_id)` - Add product to wishlist
* `airy_wishlist_remove_product($product_id, $variation_id)` - Remove product
* `airy_wishlist_get_items()` - Get all items
* `airy_wishlist_clear()` - Clear wishlist

== Privacy Policy ==

Airy Woo Wishlist stores wishlist data in your WordPress database and uses cookies for guest users. No data is sent to external servers. The plugin is fully GDPR compliant when used as intended.

= Guest User Data =
* Stores session ID in cookie (30 days, configurable)
* Stores wishlist items in database
* Data is anonymized (no personal information)
* Automatically merged and removed when user logs in

= Logged-In User Data =
* Stores wishlist items in database linked to user ID
* No personal information beyond WordPress user ID
* Can be deleted when user account is deleted

== Screenshots ==

1. Airy Wishlist Setting Page
2. Airy Wishlist button shop and categories pages
3. Airy Wishlist icon single product page
4. Airy Wishlist header icon counter
5. Airy Wishlist table layout dispaly
6. Airy Wishlist grid layout dispaly

== Credits ==

Icons provided by [Feather Icons](https://feathericons.com/) (MIT License)

Developed by [NXlogy](https://nxlogy.com) for [AiryThemes](https://airythemes.com)
