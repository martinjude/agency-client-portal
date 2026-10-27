# Agency Client Portal

A branded, token-secured client portal for creative agencies and freelancers, built as a WordPress plugin.

Clients open their space from a private link, with no WordPress account needed. There they can review projects, comment on deliverables, approve versions and upload files. Your team manages everything from a dedicated dashboard.

- **License:** GPL-2.0-or-later
- **Requires:** WordPress 6.0+, PHP 7.4+
- **Text domain:** `agency-client-portal` (English source strings, French translation included)

## Features

- Token-based client access (no login), with links you can regenerate or revoke at any time
- A portal for each client with its logo and a single accent color
- One fixed dark design system (Inter + JetBrains Mono, bundled) and an agency sidebar listing every client, project and block
- Projects built from blocks: script, storyboard, versioned media, video, link, file deposit, code, separator, progress bar…
- Drag-and-drop ordering and resizable grid cards
- Comments on blocks, email notifications, a "Notify client" button
- Shareable links to individual blocks
- Reusable block layouts (presets)
- Protected file storage in `wp-content/agency-uploads/`, with permission-checked serving, HTTP Range support and thumbnails
- Roles: `agency_manager`, `agency_client`
- Integration with the WordPress personal data exporter and eraser

## Installation

### From a release zip

Download `agency-client-portal-X.Y.Z.zip` from the [latest GitHub release](https://github.com/martinjude/agency-client-portal/releases/latest), then upload it under *Plugins → Add New → Upload Plugin*.

### From source

```bash
cd wp-content/plugins
git clone https://github.com/martinjude/agency-client-portal.git
```

Then activate the plugin under *Plugins*.

## Project structure

```
agency.php                      Main plugin file (bootstrap, singleton Agency_Plugin)
uninstall.php                   Cleanup on plugin deletion
includes/                       PHP classes (autoloaded: Agency_Foo → class-agency-foo.php)
  admin/                        WP admin screens
  frontend/                     Frontend assets and rendering
  models/                       Data models (clients, projects, blocks, assets…)
  migrations/                   Versioned DB migrations (migration-X-Y-Z.php)
templates/                      PHP templates for the portal, blocks and admin pages
assets/css, assets/js           Static assets (no build step)
languages/                      .pot template, fr_FR .po/.mo and JS .json translations
```

## Extending

Add-ons are standard WordPress plugins. Hook into `agency_load_extensions`, which fires once the core plugin has loaded:

```php
add_action( 'agency_load_extensions', function () {
    // Your add-on bootstrap code.
} );
```

Other useful hooks:

| Hook | Type | Purpose |
|---|---|---|
| `agency_before_init` / `agency_after_init` | action | Run code around the plugin's `init` routine |
| `agency_comment_notification` | action | Fired when a comment is posted on a block |
| `agency_client_hard_deleted` | action | Fired after a client and its data are deleted |
| `agency_upload_size_limit` | filter | Max upload size for Agency files (default 512 MB). The site-wide limit is not affected. |

## Development

There is no build step. Edit the PHP, JS and CSS files directly.

### Translations

Source strings are in English. To regenerate the translation files after changing strings (requires [WP-CLI](https://wp-cli.org/) and GNU gettext):

```bash
wp i18n make-pot . languages/agency-client-portal.pot --domain=agency-client-portal --exclude=design,bin,dist
msgmerge --update languages/agency-client-portal-fr_FR.po languages/agency-client-portal.pot
msgfmt -o languages/agency-client-portal-fr_FR.mo languages/agency-client-portal-fr_FR.po
wp i18n make-json languages/agency-client-portal-fr_FR.po --no-purge
```

JavaScript strings use `wp.i18n.__()` with the `agency-client-portal` text domain.

### Database migrations

Schema changes go in a new file, `includes/migrations/migration-X-Y-Z.php`, with a class `Agency_Migration_X_Y_Z` that exposes `public static $version` and an `up()` method returning `true` or `false`. Bump `Agency_Database::DB_VERSION` to match. Migrations must be idempotent.

### Coding standards

- Escape all output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`…).
- Use `$wpdb->prepare()` for every query that takes input.
- Check capabilities and nonces on every write endpoint.
- Prefix every variable defined at file level (templates, `uninstall.php`) with `$agency_`.
- Run `bin/check.sh` before submitting a pull request. It runs PHP lint and [Plugin Check](https://wordpress.org/plugins/plugin-check/), auto-detecting a [Local](https://localwp.com) site (elsewhere: `WP_CLI=wp bin/check.sh`), and must report 0 errors.

Coding agents: see [AGENTS.md](AGENTS.md).

### Releasing

1. Bump the version in `agency.php` (header and `AGENCY_VERSION`) and the `Stable tag` in `readme.txt`.
2. Add a changelog entry to `readme.txt`.
3. Run `bin/check.sh`.
4. Tag the release (`git tag vX.Y.Z && git push --tags`). The `Release` workflow checks that the tag matches the plugin version, builds the zip (files listed in `.distignore` are excluded) and publishes it as a GitHub Release.

`bin/build-zip.sh` builds the same zip locally in `dist/`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Bug reports and pull requests are welcome.

## Third-party code

- [SortableJS](https://github.com/SortableJS/Sortable) 1.15.0, MIT
- [Prism](https://github.com/PrismJS/prism), MIT
- [Twemoji](https://github.com/jdecked/twemoji) graphics (loaded from jsDelivr), CC-BY 4.0

## License

Agency Client Portal is free software, released under the [GNU General Public License v2 or later](LICENSE).
