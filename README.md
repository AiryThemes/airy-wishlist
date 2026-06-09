# Airy Wishlist for WooCommerce

A powerful, lightweight wishlist plugin for WooCommerce — let customers save products for later, organize multiple lists, share them, and get back-in-stock / price-drop alerts.

[![CI](../../actions/workflows/ci.yml/badge.svg)](../../actions/workflows/ci.yml)

> This repository is the development source. The released plugin is distributed on
> [WordPress.org](https://wordpress.org/plugins/airy-wishlist/) and updated automatically
> from GitHub Releases.

## Features

- Add to wishlist on shop, category and product pages (toggle add/remove)
- Table or grid wishlist page, add-to-cart and "add all to cart"
- **Multiple named wishlists** (e.g. Birthday, Christmas) with move-between-lists
- **Shareable wishlists** via a public token (Facebook, X, Pinterest, WhatsApp, Email, copy link)
- **WooCommerce My Account** "Wishlist" tab
- **Stock & price notifications** (back in stock, price drop, low stock, on sale) via WP-Cron
- **Wishlist Analytics** dashboard and **Marketing** (promo email to product wishlisters)
- Guest wishlists (optional), HPOS-compatible, vanilla JS (no jQuery)

## Development

Requirements: PHP 7.4+ and WooCommerce 7.0+.

### Coding standards (PHPCS + WPCS)

This project follows the WordPress Coding Standards. The ruleset is in
[`phpcs.xml.dist`](phpcs.xml.dist).

```bash
composer global require "squizlabs/php_codesniffer" "wp-coding-standards/wpcs" \
  "phpcsstandards/phpcsextra" "phpcsstandards/phpcsutils" \
  "dealerdirect/phpcodesniffer-composer-installer"

phpcs        # check
phpcbf       # auto-fix
```

Every pull request automatically runs **PHPCS** and the official **WordPress Plugin Check**
(see [`.github/workflows/ci.yml`](.github/workflows/ci.yml)). PRs should be green before merging.

### Building a release ZIP

Development files (`.github`, `phpcs.xml.dist`, `.distignore`, etc.) are excluded from the
distributed package via [`.distignore`](.distignore):

```bash
wp dist-archive . ../airy-wishlist.zip
```

## Release / deploy

1. Bump the version in `airy-wishlist.php` (header + `AIRY_WISHLIST_VERSION`) and
   `readme.txt` (`Stable tag`), and add a changelog entry.
2. Merge to `main`.
3. Publish a **GitHub Release** tagged with the version (e.g. `1.7.4`).
4. The **Deploy to WordPress.org** workflow pushes it to the plugin SVN automatically.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Issues and pull requests are welcome.

## License

GPL-2.0-or-later. See plugin headers for details.
