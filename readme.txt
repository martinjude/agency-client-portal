=== Agency Client Portal ===
Contributors: martinjude
Tags: client portal, agency, project management, file sharing, collaboration
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 2.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A branded, token-secured client portal for creative agencies and freelancers. Clients open their projects from a private link, with no login required.

== Description ==

Agency Client Portal lets creative agencies and freelancers share work with their clients through a branded, self-hosted portal inside WordPress.

Each client gets a private link to their space. From there they can view projects, comment on deliverables, approve versions and upload files. They never need a WordPress account. Your team manages everything from a dedicated dashboard.

The plugin is free and open source. All features are included, and nothing requires a license or an account.

**Features**

* Token-based client access. Clients do not need to log in.
* A portal for each client, with its logo and a single accent color.
* One focused dark interface (Inter and JetBrains Mono, bundled), with a sidebar that lists every client, project and block for agency staff.
* Projects built from more than 10 block types: script, storyboard, versioned media, video, link, file deposit, code, separator, progress bar and more.
* Drag-and-drop block ordering, plus resizable grid cards.
* Comments on blocks, with optional email notifications.
* A "Notify client" button that emails the client a secure link to a specific block.
* "Copy link" for any block, with a tokenized link.
* Reusable block layouts (presets) across projects.
* Secure file storage outside the media library, with permission-checked serving and automatic thumbnails.
* Dedicated roles: `agency_manager` and `agency_client`.
* Privacy tools: integrates with the WordPress personal data exporter and eraser.
* Fully translatable. French translation included.

**Extending the plugin**

Add-ons are regular WordPress plugins. They hook into the `agency_load_extensions` action, which fires once the core plugin is loaded, and into the other actions and filters it provides, such as `agency_upload_size_limit`.

**Source code and contributions**

The full, unminified source code is developed in the open on GitHub: https://github.com/martinjude/agency-client-portal

Bug reports and pull requests are welcome.

== Installation ==

1. Upload the `agency-client-portal` folder to `/wp-content/plugins/`, or install the plugin from the Plugins screen in WordPress.
2. Activate **Agency Client Portal** from the Plugins screen.
3. Go to **Agency > Settings** to configure branding, uploads and email templates.
4. Create your first client from the Agency dashboard and share the portal link.

The plugin creates a `wp-content/agency-uploads/` directory for secure file storage. Make sure your server lets WordPress write to `wp-content/`.

== Frequently Asked Questions ==

= Do clients need a WordPress account? =

No. Clients open their portal through a unique, token-secured link. No registration or login is required. You can regenerate or revoke a link at any time.

= Where are uploaded files stored? =

In `wp-content/agency-uploads/`, outside the media library. Direct access is blocked, and files are served through a permission check.

= What is the maximum upload size? =

The plugin accepts files up to 512 MB by default. Your server's PHP limits (`upload_max_filesize`, `post_max_size`) still apply. Developers can change the plugin's limit with the `agency_upload_size_limit` filter. This does not change the upload limit for the rest of your site.

= Can I customize the portal appearance per client? =

Each client portal shows the client's logo and accent color, and the agency logo is set in Agency > Settings > Branding. The rest of the interface is a single, fixed design: there are no themes, fonts or colors to configure.

= Is the plugin translated? =

The plugin is written in English and ships with a French translation. Other languages can be contributed on translate.wordpress.org.

= Does deleting the plugin remove my data? =

Yes. Deleting the plugin from the Plugins screen removes its database tables, options and roles. Uploaded files are kept unless the `agency_delete_data_on_uninstall` option is enabled.

== External services ==

The plugin works without any external account. Some optional features load content from third-party services:

= Twemoji (jsDelivr CDN) =
Project and link emojis are shown as Twemoji SVG images, loaded from cdn.jsdelivr.net. If an image fails to load, the native emoji is shown instead. The visitor's IP address and user agent are sent to jsDelivr. Twemoji graphics are licensed under CC-BY 4.0.
Terms and privacy policy: https://www.jsdelivr.com/terms

= YouTube, Vimeo, Google Drive, Google Docs, Google Sheets, Google Slides, Dropbox =
When a team member embeds a video, document or presentation from one of these services in a block, the portal displays the provider's embedded player or viewer. The visitor's browser then connects to that provider. Nothing loads from these services unless you add such an embed.
YouTube / Google: https://policies.google.com/privacy — Vimeo: https://vimeo.com/privacy — Dropbox: https://www.dropbox.com/privacy

== Third-party libraries ==

* SortableJS 1.15.0 — MIT License — https://github.com/SortableJS/Sortable (bundled as `assets/js/sortable.min.js`, the official minified build)
* Prism 1.x — MIT License — https://github.com/PrismJS/prism (bundled as `assets/js/prism.js`)
* Twemoji graphics — CC-BY 4.0 — https://github.com/jdecked/twemoji
* Inter — SIL Open Font License 1.1 — https://github.com/rsms/inter (bundled in `assets/fonts/`)
* JetBrains Mono — SIL Open Font License 1.1 — https://github.com/JetBrains/JetBrainsMono (bundled in `assets/fonts/`)

== Screenshots ==

1. Client portal: project grid view.
2. Block detail view with comments.
3. Agency dashboard: client management.

== Changelog ==

= 2.0.1 =
* Block detail pages (video, versioned media, storyboard, script, link, code, file deposit) redesigned to match the design system, with a shared page head and comment thread.

= 2.0.0 =
* The plugin is now free and open source. Every feature is available without a license: progress bars, layouts (presets), advanced branding, block links and email notifications.
* Removed the license system and all calls to the license server.
* Removed the built-in updater. Updates now come from WordPress.org.
* Removed the ZIP extension installer. Add-ons are now regular WordPress plugins that hook into `agency_load_extensions`.
* Removed the upgrade banners and notices.
* The interface is now in English, with a French translation included. The text domain is now `agency-client-portal`.
* Hardened output escaping, and switched to the WordPress APIs for inline scripts, uploads and file operations.
* The 512 MB upload limit now applies only to Agency uploads instead of the whole site.
* Database migration 2.8.0 removes the leftover license options and scheduled events.
* New interface based on a single design system: dark UI, bundled Inter and JetBrains Mono fonts (no more Google Fonts requests), redesigned dashboard, portal, project, client and block pages.
* New agency sidebar on every staff page: search (Cmd/Ctrl+K), all clients with their projects and blocks, and a folded rail mode.
* Removed appearance settings: theme presets, light/dark mode, interface font, corner radius, card border and background/sidebar colors. Clients keep a single accent color; the secondary color is gone.
* Database migration 2.9.0 deletes the removed appearance options and adapts block widths to the new 4-column grid.

= 1.6.6 =
* Visual fixes.

= 1.6.0 =
* Freemium model: core features available without a license.

= 1.5.0 =
* Security: access tokens are no longer stored in plain text. Lookups use the hash only.
* Security: protected files are streamed in chunks, with HTTP Range and ETag/304 support (seekable videos).
* Performance: batched queries for cascade deletes and project reordering.
* Performance: components load lazily.
* Fixes and code cleanup.

= 1.4.1 =
* A single accent color per client. Themes control only the portal chrome.

= 1.4.0 =
* Unified portal header, mobile drawer, code copy, 4 themes, Twemoji emojis.

= 1.2.5 =
* Project search on the client page, client comments option per block, and dashboard fixes.

= 1.1.6 =
* Nonces on token logout and block creation, hardened migrations, and escaping fixes.

= 1.0.6 =
* SortableJS bundled locally, ABSPATH guards, and GDPR exporter and eraser.

= 1.0.5 =
* Minor fixes and improvements.

== Upgrade Notice ==

= 2.0.0 =
All features are now free and the license system is gone. The text domain changed to `agency-client-portal`. Custom translations need to be updated.
