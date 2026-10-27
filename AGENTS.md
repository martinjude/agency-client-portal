# AGENTS.md

Guide for coding agents (and humans) working on **Agency Client Portal**, a WordPress plugin that gives creative agencies a branded, token-secured client portal. Clients open their space from a private link (no WordPress login). Agency staff manage everything from a dedicated dashboard.

- **Slug / text domain:** `agency-client-portal` (the plugin folder may be named `agency` locally)
- **Requires:** WordPress 6.0+, PHP 7.4+ (code must stay 7.4-compatible: no enums, `match`, readonly, named args…)
- **License:** GPL-2.0-or-later, distributed as a zip on GitHub Releases (WordPress.org-ready, not published there yet). Every feature is free: no license checks, no premium gating, no self-updater.
- **No build step.** PHP, JS and CSS are edited directly. No Composer, no npm (except `design/tools/`, which is dev-only).

## Commands

| Task | Command |
|---|---|
| Lint + Plugin Check (same rules as CI) | `bin/check.sh` (`--all` lists every warning) |
| Build the distributable zip | `bin/build-zip.sh` → `dist/agency-client-portal-<version>.zip` |
| Regenerate translations | see *Internationalisation* below |

`bin/check.sh` auto-detects a [Local](https://localwp.com) site (PHP binary, MySQL socket, bundled WP-CLI). Elsewhere, set `WP_CLI="wp"` (and `PHP_BIN`, `WP_PATH` if needed). It needs the [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin installed on the site. **It must report 0 errors before any commit.** Warnings are tolerated but don't add new ones.

There is no automated test suite. After a change, open the affected pages in a browser (as a manager and as a token client) and watch `wp-content/debug.log`.

## Architecture

### PHP

| File / class | Responsibility |
|---|---|
| `agency.php` | Main file. Singleton `Agency_Plugin`, constants (`AGENCY_VERSION`, `AGENCY_PLUGIN_DIR`…), bootstraps components. |
| `includes/class-agency-autoloader.php` | Maps `Agency_Foo_Bar` → `class-agency-foo-bar.php` (in `includes/`, `includes/models/`, `includes/admin/`, `includes/frontend/`). |
| `includes/class-agency-database.php` | Creates tables with `dbDelta()`, runs migrations. `Agency_Database::DB_VERSION` is the current schema version. |
| `includes/migrations/migration-X-Y-Z.php` | One migration per version (see *Database migrations*). |
| `includes/class-agency-rest-api.php` | REST endpoints under `agency/v1`. |
| `includes/class-agency-rewrite-rules.php` | Front routes (`/client/{slug}/…`, `/agency/dashboard/`, `/agency/edit-client/{id}/`) and `load_template()`. |
| `includes/class-agency-security.php` | Token access control on `template_redirect` (priority 9), cookie `agency_client_auth`, `render_access_denied()`. |
| `includes/class-agency-assets-manager.php` | Uploads, thumbnails, protected file serving (`admin-ajax.php?action=agency_serve_asset`), `filesystem()`, `get_max_upload_size()`. |
| `includes/class-agency-roles.php` | Roles `agency_manager`, `agency_client`; caps `manage_agency_clients`, `manage_agency_projects`, `manage_agency_blocks`, `upload_agency_assets`, `view_agency_comments`, `delete_agency_content`. |
| `includes/class-agency-extensions-manager.php` | Fires `agency_load_extensions` on `plugins_loaded`. Add-ons are ordinary plugins hooking into it. |
| `includes/class-agency-template.php` | Template helpers: `time_ago()`, `initial()`, `initials()`, `file_tile()`, `client_color()`, `crumbs()`. |
| `includes/class-agency-icons.php` | `render()` (stroke icons), `render_ui()` (utility icons), `render_glyph()` (two-tone nav/block glyphs). All pass through a `wp_kses` SVG allowlist. |
| `includes/class-agency-mailer.php`, `class-agency-notifications.php` | Email notifications (comments, "Notify client"). |
| `includes/class-agency-privacy.php` | WordPress personal data exporter and eraser. |
| `includes/frontend/class-agency-frontend.php` | Enqueues portal assets, injects the client accent color (`--ag-client`), body classes. |
| `includes/admin/class-agency-admin.php` | wp-admin screens, settings, meta boxes. |
| `includes/models/` | `Agency_Client`, `Agency_Project`, `Agency_Block`, `Agency_Asset`, `Agency_Comment`, `Agency_External_Link`, `Agency_Block_Preset`. |
| `uninstall.php` | Removes data on plugin deletion. |

### Templates (`templates/`)

Loaded by `Agency_Rewrite_Rules::load_template()` or the `Agency_Admin::render_*()` methods, not by the theme.

- Pages: `client-view.php`, `project-view.php`, `block-view.php`, `dashboard.php`, `edit-client.php`, `access-denied.php`, `admin/*`.
- `block-view.php` dispatches to a dedicated template for `script`, `storyboard`, `versioned_media` (`block-view-versioned.php`), `video`, `link`, `file_deposit`, `code`. Every other type renders in `block-view.php` itself.
- Partials (`templates/partials/`): `sidebar.php` (agency sidebar, every manager page), `header.php` / `footer.php` (client only), `block-head.php`, `block-comments.php`, `video-item.php`, `link-preview-card.php`, `notify-client-button.php`, `accent-field.php`. Partials read the including template's variables.

### Database (`{$wpdb->prefix}agency_*`)

- **clients**: `id, user_id, name, slug, logo_url, primary_color, access_token, token_hash, token_active, status, …`
- **projects**: `id, client_id, name, slug, description, subtitle, cover_image_url, cover_image_thumbnail_url, cover_image_asset_id, emoji, labels, status, is_archived, order_position`
- **blocks**: `id, project_id, type, title, content, settings (JSON), order_position, grid_width (1–4 columns; separators span the row), grid_height (1–2), status`
- **assets**: `id, client_id, project_id, block_id, filename, original_name, file_path, file_url, file_type, file_size, mime_type, thumbnail_path, thumbnail_url, uploaded_by, …`
- **comments**: `id, block_id, client_id, user_id, content, type, status, created_at, …`
- **external_links**: `id, block_id, url, title, description, favicon_url, order_position`

Check the migrations for the exact current columns before writing SQL.

### Block types

Defined in `Agency_Block::TYPES` (`includes/models/class-agency-block.php`): `text`, `script`, `storyboard`, `versioned_media`, `video`, `embed`, `link`, `separator`, `progress_bar`, `file_deposit`, `code`, plus legacy types kept for old data. `Agency_Block::CLIENT_TYPES` lists the ones offered in the client-facing UI.

### JavaScript (`assets/js/`)

All scripts use an IIFE + jQuery: `(function($) { 'use strict'; … })(jQuery);`.

| File | Role |
|---|---|
| `manager.js` | Staff features: sidebar (fold, groups, search, Cmd/Ctrl+K, mobile drawer), SortableJS drag-and-drop, project/block CRUD modals, asset picker, presets, copy access link. Exposes `AgencyManager`. |
| `ui.js` | Shared helpers: `AgencyUI.toast`, `debounce`, `copyText`, dropdowns (`[data-agency-dropdown]`), accent color field, grid/list toggle. |
| `frontend.js` | Client features: comments, file deposits, video player, storyboard viewer. |
| `dashboard.js`, `edit-client.js` | Front-end agency dashboard and edit-client page. |
| `emoji-picker.js`, `emoji-render.js` | Emoji picker (use the `.agency-emoji-display` class). |
| `admin.js` | wp-admin screens. |
| `sortable.min.js`, `prism.js` | Bundled third-party libraries (don't edit). |

### CSS (`assets/css/`)

One fixed dark design system (zinc greys, Inter + JetBrains Mono bundled in `assets/fonts/`). **There are no appearance settings.** The only per-client customization is the accent color.

- `tokens.css`: `--ag-*` tokens and `@font-face`. Loaded on the front end and in wp-admin.
- `ui.css`: portal primitives (`.agency-btn*`, inputs, tags/pills/badges, menus, modals, toast). Front end only.
- `frontend/`: partials in cascade order `_base`, `_blocks`, `_block-views`, `_components`, `_app`, `_block-pages`, `_sidebar` (handle `agency-frontend`).
- `edit-client.css`, `code-block.css`, `prism-dark.css`, `external-links.css`, `emoji-picker.css`: page or feature styles.
- `admin.css`: wp-admin (keeps the WordPress look, with its own light `--agency-*` tokens).

## Conventions

### PHP

- Every PHP file starts with `if (!defined('ABSPATH')) { exit; }`.
- Classes are named `Agency_{Name}` in `class-agency-{name}.php`.
- **Prefix every variable defined at file level with `$agency_`** (templates, partials, `uninstall.php`). Plugin Check flags unprefixed ones. When a template reads a variable from its caller (e.g. meta boxes in `Agency_Admin`), the caller must use the same prefixed name.
- Escape all output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`). Sanitize all input. Check nonces and capabilities on every write.
- Use `$wpdb->prepare()` for every query that takes input.
- Echo SVG icons through `Agency_Icons`, never raw.
- Inline JS in templates: `ob_start();` … `wp_print_inline_script_tag(ob_get_clean());`. Never write raw `<script>` tags.
- Code comments are written in **French**, matching the codebase.

### REST API

- Write endpoints use `check_manage_permission()`. Client reads use `check_client_access()` (or the more specific `check_project_access()`, `check_comment_permission()`, `check_upload_permission()`).
- Always return projects through `format_project_for_rest()` (adds computed fields such as `cover_image_thumbnail_url`). Never return a raw DB row for a project.
- Never expose server file paths in responses.

### Files and uploads

- All upload logic lives in `Agency_Assets_Manager`: `wp_handle_upload()` with a scoped `upload_dir` filter, stored in `wp-content/agency-uploads/`, served with a permission check.
- File operations use `Agency_Assets_Manager::filesystem()` / `wp_delete_file()`.
- The upload limit is `Agency_Assets_Manager::get_max_upload_size()` (512 MB, filter `agency_upload_size_limit`). Never change the site-wide `upload_size_limit`.
- Thumbnails are generated for jpg/jpeg/png/webp only. SVG and GIF are served at full resolution.
- Cover images store both `cover_image_url` and `cover_image_asset_id`.

### JavaScript

- AJAX goes through `$.ajax()` with `agencyData.nonce`. REST calls go to `agencyData.rest_url` (e.g. `agency/v1/projects`).
- Cache WP media frame instances (prevents double-open bugs).
- Every dynamic `<img>` gets an `onerror` fallback.
- To filter assets down to images, use `thumbnail_url` or a file extension check.

### Database migrations

- Add a new file `includes/migrations/migration-X-Y-Z.php` with class `Agency_Migration_X_Y_Z`, `public static $version = 'X.Y.Z';` and a `public function up()` returning `true`/`false`.
- Bump `Agency_Database::DB_VERSION` to the same version.
- Migrations must be idempotent: check that a column or index exists before `ALTER TABLE`, log errors with `error_log()`, and return `false` on failure (the chain stops and the version is not bumped).
- Never put migration logic inline in `class-agency-database.php`.

### Internationalisation

- Source strings are in English, text domain `agency-client-portal`. Use the escaping variants (`esc_html__()`, `esc_html_e()`, `esc_attr__()`…).
- Every string with a placeholder needs a `/* translators: … */` comment **on the line directly above the gettext call**. Plugin Check fails otherwise.
- JS strings use `wp.i18n.__('…', 'agency-client-portal')`. The script depends on `wp-i18n` and calls `wp_set_script_translations()`.
- The French translation ships in `languages/`. To regenerate:
  ```bash
  wp i18n make-pot . languages/agency-client-portal.pot --domain=agency-client-portal --exclude=design,bin,dist
  msgmerge --update languages/agency-client-portal-fr_FR.po languages/agency-client-portal.pot
  msgfmt -o languages/agency-client-portal-fr_FR.mo languages/agency-client-portal-fr_FR.po
  wp i18n make-json languages/agency-client-portal-fr_FR.po --no-purge
  ```

## Design

- `design/design.md` is the design guide: tokens, components, artboards, and the workflow between the Illustrator file and the code. Read it before any UI work.
- `design/design_system.ai` is the source of truth, but it is **not in git** (it contains third-party reference screenshots). Only maintainers have it locally.
- `design/prototypes/` holds static HTML renders of the pages. `design/tools/` holds dev-only scripts (Playwright, Illustrator import). Nothing in `design/` ships in the plugin.

## Release

1. Bump the version in `agency.php` (header `Version:` **and** `AGENCY_VERSION`) and `Stable tag:` in `readme.txt`. All three must match (Plugin Check fails on a mismatch).
2. Add a changelog entry to `readme.txt`.
3. Run `bin/check.sh` (0 errors).
4. `git tag vX.Y.Z && git push --tags`. `.github/workflows/release.yml` checks that the tag matches `agency.php`, runs `bin/build-zip.sh` and publishes the zip as a GitHub Release. Nothing is sent to WordPress.org.

CI (`.github/workflows/plugin-check.yml`) runs PHP lint (7.4 and 8.3) and Plugin Check on every push to `main` and on every pull request.

## Distribution and files

- `.distignore` lists what never ships: `.github/`, `.wordpress-org/`, `bin/`, `design/`, `dist/`, dotfiles, `README.md`, `CONTRIBUTING.md`, `AGENTS.md`, `CLAUDE.md`. If you add a dev-only file or folder, add it there too.
- `.wordpress-org/` holds the WordPress.org listing assets (banner, icon, screenshots; see its README), kept for a future WordPress.org submission.
- `readme.txt` is the WordPress.org-format readme (also what WordPress shows in *View details*). `README.md` is for GitHub.
