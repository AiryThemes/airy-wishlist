# Contributing to Airy Wishlist

Thanks for helping improve Airy Wishlist! 🙌

## Workflow

1. **Open an issue** describing the bug or feature (or comment on an existing one).
2. **Create a branch** off `main`:
   - `fix/short-description` for bug fixes
   - `feature/short-description` for new features
3. Make your changes, following the WordPress Coding Standards.
4. **Open a Pull Request** to `main`. CI will run automatically:
   - **PHPCS** (WordPress Coding Standards) — must pass
   - **WordPress Plugin Check** — must pass
5. A maintainer reviews and merges. Releases are tagged from `main` and deployed to WordPress.org.

## Coding standards

Run these locally before pushing:

```bash
phpcs        # report issues
phpcbf       # auto-fix what can be fixed
```

The ruleset is `phpcs.xml.dist`. Please keep PRs green (0 errors / 0 warnings).

## Guidelines

- Keep changes focused; one logical change per PR.
- Prefix all globals/functions/classes/options with `airy_wishlist` / `Airy_Wishlist` / `AIRY_WISHLIST`.
- Escape on output, sanitize on input, and use `$wpdb->prepare()` for queries.
- Use the text domain `airy-wishlist` for all user-facing strings.
- Don't commit development/build files into releases (handled by `.distignore`).

## Reporting security issues

Please do **not** open a public issue for security vulnerabilities. Contact the maintainer privately.
