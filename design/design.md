# Agency Client Portal — Design guide

Working reference for implementing UI that matches `design/design_system.ai`.
**The Illustrator file is the source of truth.** This document explains how the file is organised, the tokens and components already defined in it, and how to move between Illustrator and code. When the two disagree, re-read the file (`capture_view`, `list_items`) and update this document.

---

## 1. Illustrator file structure

### Layers (top → bottom)

| Layer | Role | Rules |
|---|---|---|
| `RÉFÉRENCES` | External references (screenshots of other products) and red annotations in JetBrains Mono. | **Always present, always on top.** Read every annotation before designing a screen. Never draw production artwork here, never delete it. |
| `Nouveau design` | The design. One sublayer per artboard, named exactly like the artboard. | Draw only inside the sublayer that matches the artboard. Text = live text layers, shapes = paths, icons = vector groups. **No raster images** (use a placeholder rect named `… [image]`, fill `#484856`). |
| `Nouveau design / _Archive · anciennes sidebars` | Old per-page sidebars, kept for history. | Hidden + locked. Don't reuse. |

### Artboards

| # | Artboard | Content |
|---|---|---|
| 01 | Dashboard | Agency dashboard (`/agency/dashboard/`) |
| 02 | Nouveau client | "Nouveau client" modal over the dashboard |
| 03 | Modifier client | `/agency/edit-client/{id}/` |
| 04 | Portail client | Client portal, project list (`/client/{slug}/`) |
| 05 | Projet | Project page, cover + toolbar + block grid |
| 06 | Bloc | Generic block detail page |
| 07 | Réglages WP | `wp-admin` settings page (WordPress styling — not restyled) |
| 08 | Accès refusé | Visitor without a valid token |
| **09** | **Sidebar** | **The single sidebar instance** (expanded, folded rail, component states, tokens). 2700 × 1080. |
| 10 | Blocs · Grille projet | Project grid showing every block type card (progress bar, video, storyboard, versioned media, script, separator, link, file deposit, code) |
| 11–17 | Bloc · Vidéo / Storyboard / Média versionné / Script / Lien / Dépôt de fichiers / Code | Detail page of each block type |
| **18** | **MAIN DESIGN SYSTEM** | **Shared components** (4004 × 2178): colour/type/radius/spacing foundations, icon set, buttons, form fields, tags/badges/avatars, project and block cards, menus, alerts, modal, page patterns. The sidebar spec sits in the middle column (x 1980–2740, y < 1000). |

Conventions:
- 1 pt = 1 CSS px. Page artboards are 1440 wide (desktop, viewport height 900).
- **The sidebar exists only on artboard 09.** Page artboards never contain a sidebar copy: the left column (0 → 280 px) is where the sidebar sits at runtime. Change the sidebar on 09 only.
- Item names come from the DOM (`div.agency-block`, `… [border]`, `… [gradient]`, `icon …`) when a page was imported from the live site. Keep that naming for new items. It maps shapes back to CSS selectors.
- Demo data: client **Lumen** (accent `#7C83FD`), project **Lancement Aurora** (💡), blocks *Teaser 30s*, *Storyboard — Film principal*, *Affiche 40×60*, *Script voix off*, *Moodboard Figma*, *Dépôt des rushs*, *Intégration du player*. Other clients: Orbe `#F59E0B`, Studio Kasa `#10B981`, Maison Vega `#EC4899`. Agency user: Mark S (Administrateur). The local site has the same Lumen data (client id 9, project id 25, blocks 211–219).

### Illustrator MCP gotchas
- `run_extendscript` returns `null` when the returned string contains non-ASCII characters (é, ·, —). Return ASCII, e.g. `s.replace(/[^\x20-\x7e]/g,"?")`, or use `return`.
- Canvas limits: artboards beyond x ≈ 8000 or y ≈ 8000 (relative to artboard 01) fail with error `CoOA`. Free space is on the left (negative x).
- Point text is positioned by its baseline: `layer.textFrames.pointText([x, y])`.
- Locked parent layers lock their sublayers: unlock `Nouveau design` before scripting, then restore it.

---

## 2. Design tokens

Prefix every CSS custom property with `--ag-`. Base UI is **dark, neutral (zinc) greys**. The only saturated colour in the chrome is the **client colour** (`clients.primary_color`).

### Colours

| Token | Value | Use |
|---|---|---|
| `--ag-bg` | `#0E0E10` | Page background |
| `--ag-panel` | `#18181B` | Sidebar, cards, modals |
| `--ag-hover` | `#222225` | Row hover, secondary buttons |
| `--ag-active` | `#2A2A2E` | Active row, badges |
| `--ag-border` | `#27272A` | Panel borders, dividers, tree guides |
| `--ag-field` | `#1F1F23` | Inputs, search, popover menus |
| `--ag-field-border` | `#2E2E33` | Input borders (`#52525B` on focus) |
| `--ag-text-1` | `#F4F4F5` | Primary text |
| `--ag-text-2` | `#A1A1AA` | Secondary text, block rows, icon highlight tone |
| `--ag-text-3` | `#71717A` | Muted text, labels, chevrons, placeholders |
| `--ag-icon-a` | `#52525B` | Icon base tone |
| `--ag-icon-b` | `#A1A1AA` | Icon highlight tone |
| `--ag-danger` | `#F87171` | Destructive actions (Déconnexion, delete) |
| `--ag-client` | client `primary_color` | Client avatar, active ring, primary buttons inside a client portal |

Status colours from the existing UI stay as they are: success `#4ADE80` on `#0D3320`, error `#DC2626`.

### Typography

Two families only, loaded for the whole plugin UI:
- **Inter**: all UI text.
- **JetBrains Mono**: section labels, counters/badges, keyboard hints, meta lines, captions, code.

| Style | Spec |
|---|---|
| Page title | Inter 600, 20/28 |
| Nav item | Inter 500, 14/20 |
| Tree row (project) | Inter 500, 13 |
| Tree row (block) | Inter 400, 13, `--ag-text-2` (active: 500, `--ag-text-1`) |
| Section label | JetBrains Mono 500, 11, uppercase, letter-spacing .06em, `--ag-text-3` |
| Meta / badge / kbd | JetBrains Mono 400, 11, `--ag-text-3` or `--ag-text-2` |

In Illustrator: `Inter-Regular/Medium/SemiBold/Bold`, `JetBrainsMono-Regular/Medium`.
Implementation: bundle both fonts locally under `assets/fonts/` (both are SIL OFL, GPL-compatible) rather than loading Google Fonts, to avoid an undisclosed external call on WordPress.org.

### Shape and spacing
- Radii: rows / buttons 8, inputs 9, badges 5, avatars 6 (20 px) or 8 (28 px), cards / popovers 10–12, floating panels 14.
- Floating panels sit 8 px from the viewport edges and have a 1 px `--ag-border` border, with no shadow.
- Row heights: nav 34, tree project 32, tree block 30, section header 30, search 36.
- Horizontal rhythm: 10 px panel padding, 8 px row padding, 10 px icon → label gap.

### Icons
Grayscale **two-tone** glyphs, 20 × 20 viewBox, drawn at 16–18 px. Base shape `--ag-icon-a`, detail `--ag-icon-b`. Utility icons (search, chevrons, `+`, `···`, collapse) are 1.5–1.6 px strokes in `--ag-text-3` / `--ag-icon-b`. The source SVGs are in `design/prototypes/sidebar.html`. Port them to `Agency_Icons` with `fill="var(--ag-icon-a)"` / `fill="var(--ag-icon-b)"`.

| Item | Icon |
|---|---|
| Tableau de bord | 2×2 rounded squares (top-left highlighted) |
| Commentaires | speech bubble + 3 dots |
| Fichiers reçus | inbox tray |
| Project | folder |
| `video` | rounded rect + play + camera notch |
| `storyboard` | film frame with two panels |
| `versioned_media` | stacked layers |
| `script` | document with lines |
| `link` | chain |
| `file_deposit` | tray with up arrow |
| `code` | window with `< >` |
| `progress_bar`, `separator` | not listed in the sidebar (as today) |

---

## 3. Sidebar (artboard 09)

The sidebar is the **core navigation for agency staff**. It replaces per-client navigation: every client portal is stacked in a single tree.

### Expanded: 264 px (floating panel at x 8, full height minus 16)
1. **Header**: agency logo (32, radius 8), agency name + chevron, meta `4 clients · 9 projets` (mono). Right: collapse button (32, bordered).
2. **Search**: `Rechercher` + `⌘K` kbd. It searches clients, projects and blocks. Results are grouped and show the client name as mono meta.
3. **Primary nav**:
   - *Tableau de bord* → `/agency/dashboard/` (annotation: "Go back to agency dashboard")
   - *Commentaires* + unread count badge (replaces the reference's "Messages")
   - *Fichiers reçus* + count of new client uploads in `file_deposit` blocks
4. **`CLIENTS` section label** + `+` button (→ new client modal, `/agency/dashboard/?action=new-client`).
5. **One collapsible group per client** (styled like the reference's "Favorites" group): chevron, client avatar (20 px, client colour, initial), name, project count (mono) when collapsed. On hover / when open, a **pen icon** replaces the count → `/agency/edit-client/{id}/`. Inactive clients have their name in `--ag-text-3` plus an `inactif` badge.
6. **Projects** inside an open client: tree with a 1 px `--ag-border` guide, chevron + folder icon + name. A project **opens to reveal its blocks** (block-type icon + title, comment-count badge). The active block/project row uses `--ag-active`. A last muted row *+ Nouveau projet*.
7. **Footer**: agency user (32 px round avatar with initials, name + chevron, role in mono) + `···` menu → *Réglages*, *Admin WordPress*, divider, *Déconnexion* (danger).

### Folded rail: 56 px
Same panel styling. Logo, search button (field style), nav icons (an unread dot replaces count badges), `···` divider, one 28 px client avatar per client (active client: 2 px panel gap + 2 px client-colour ring), `+` add client, then at the bottom the expand button and user avatar. Hover shows a light tooltip (`--ag-text-1` background): `Lumen 3 projets`.

### Behaviour to implement
- Open/closed state per client and per project is persisted in `localStorage` (convenience only). The current client/project/block is always opened and highlighted from the URL.
- The collapsed/expanded state is persisted. Keyboard: `⌘K` / `Ctrl+K` focuses search, `Esc` clears.
- Data: clients → `Agency_Client`, projects → `Agency_Project` (non-archived, by `order_position`), blocks → `Agency_Block::get_by_project()` excluding `separator` / `progress_bar` (same filter as `templates/partials/sidebar.php` today). Counts come from `Agency_Comment` (unread) and `file_deposit` assets.
- Visible to agency staff only (`manage_agency_clients`). Clients with a token keep their current portal UI.

### Code mapping (implemented)
- Markup: `templates/partials/sidebar.php`, included by every manager template (dashboard, edit client, portal, project, blocks). Classes: `.agency-sidebar`, `__head`, `__search`, `__nav`, `__section`, `__group` / `__client` (client), `__pgroup` (project), `__tree`, `__row` (`--project`, `--block`, `--muted`), `is-open`, `is-active`. The folded rail is `body.agency-sidebar-closed`.
- Data: `Agency_Client::get_all_listable()`, `Agency_Project::get_by_clients()`, `Agency_Block::get_nav_by_projects()`, `Agency_Comment::count_by_blocks()` (four queries for the whole tree).
- Styles: `assets/css/frontend/_sidebar.css`; tokens in `assets/css/tokens.css`.
- JS: `AgencyManager.initSidebar()` in `assets/js/manager.js` (fold, open groups in `localStorage.agency_sidebar_open`, search, ⌘K / Ctrl+K, rail tooltips, mobile drawer under 900 px).
- *Commentaires* and *Fichiers reçus* are not implemented yet (no destination page); see §5.
- `design/prototypes/sidebar.html` is the static HTML/CSS used to generate artboard 09. Its CSS is the reference for spacing and tokens.

---

## 4. Workflow

### Design → code
1. Read the target artboard: `capture_view` (look) + `list_items` (exact bounds, colours, fonts).
2. Read all `RÉFÉRENCES` annotations on that artboard.
3. Implement with the tokens above. Never hard-code a colour that has a token.
   Code map: `assets/css/tokens.css` (tokens + fonts), `assets/css/ui.css` (buttons, fields, tags, menus, modals),
   `assets/css/frontend/_app.css` (page shell, page heads, toolbar, cards, block grid, client cards),
   `assets/css/frontend/_block-pages.css` (artboards 11–17), `assets/css/edit-client.css` (03),
   `templates/partials/block-head.php` (head of every block page).
4. Verify against the artboard: screenshot the page at 1440 × 900 and compare with `capture_view`.

### Code / HTML → Illustrator (importing a page)
Tools live in `design/tools/` (Node + Playwright, excluded from the release by `.distignore`):

```bash
cd design/tools
npm install                       # playwright 1.64.1
node login.js                     # stores a WP session in .auth/ (credentials from the site .env)
node extract.js configs/pages.json 01,05   # → out/pageXX.js + out/svg/*.svg
```

Configs (`configs/*.json`): `pages` = `[id, url, needsLogin]`, `skip` = CSS selectors not to draw (WP admin bar, old sidebar), `viewport` (default 1440 × 900). `__DESIGN__` in a URL resolves to the `design/` folder (for prototypes).
If Playwright's browser isn't installed, set `PW_CHROMIUM` to an existing Chromium/headless-shell binary.

Then draw in Illustrator (unlock `Nouveau design` first):

```js
$.evalFile(new File(".../design/tools/draw.jsx"));
$.evalFile(new File(".../design/tools/out/page05.js"));
drawPage(doc, artboardIndex /* 0-based */, "Nouveau design", "05 Projet"); // clears and redraws that sublayer
```

What the importer reproduces: backgrounds, borders (uniform or per side), radii, linear gradients (nearest 90°), zero-blur box-shadow rings, text (one point text per rendered line, ellipsis included), inline SVG and `.svg` images as vectors, and form fields (value or placeholder).
What it doesn't: blurred shadows, `backdrop-filter`, pseudo-elements (`::before` icon fonts such as dashicons), raster images (grey placeholders).

**Re-importing overwrites manual edits** in that sublayer. For screens edited by hand, edit in place instead.

To design new UI quickly, write a static prototype in `design/prototypes/` using the tokens, then import it (this is how artboard 09 was made).

---

## 4b. Components (artboard 18) and pages 04 / 05

Source: `prototypes/ag-components.css` (shared classes), generated by `tools/build-prototypes.js` into `prototypes/components.html`, `page-portail.html`, `page-projet.html`. Import with `tools/configs/components.json` (page `18`) and `tools/configs/pages-portail.json` (pages `04`, `05`); `drawPage` indexes are 17, 3 and 4.

Key specs (all dark tokens; the client colour is the only saturated colour besides status colours):
- **Buttons**: 36 px (28 px small), radius 8, Inter 500 13. Primary = `--ag-client` with `--ag-on-client` (#0E0E10) text. Secondary = `--ag-hover` + `--ag-border`. Ghost, danger (outline `#5B2326`). Icon buttons 32 / 28 / 24.
- **Fields**: 36 px, radius 9, `--ag-field` + `--ag-field-border`; focus border `--ag-focus` (#52525B); error border `--ag-danger`.
- **Tags**: block-type tag = icon + label on `--ag-active` (client-tinted when selected). Project labels are outlined. Status pills: Actif / Inactif / Archivé.
- **Cards** (262 px in a 4-column grid, 16 px gap, 1096 px content area at x 312–1408): cover 150 px (`[image]` placeholder = project cover or the block's own media) + body (tags row with `⋮`, title Inter 600 14, mono meta, comment chip footer). Hover = `#1C1C20` + `#3F3F46` border; selected = client border.
- **Block preview** per type: video (play + duration), storyboard (mosaic of slides), versioned (poster + `V3`), script (first lines), link (favicon + domain), file deposit (file list), code (snippet).
- **Menus**: block quick actions (Ouvrir, Renommer, Dupliquer, Notifier le client, Copier le lien, Supprimer) and the `+ Nouveau bloc` type picker (9 block types, including progress bar and separator).

Pages 04 and 05 have no cover banner. Layout: left 280 px reserved for the sidebar, title row, toolbar (search, Filtrer, grid/list toggle, primary button), section header with count, card grid. **04** lists the client's projects (cover, labels = `projects.labels`, name, block count; the reference's Folders row was dropped as "Non pertinent"). **05** lists the project's blocks (media preview, type tag, `⋮` quick actions, client comment count). Avatars from the reference have no data behind them and were not drawn.

### Dashboard, new-client modal, access denied (01 / 02 / 08)

Generated by `tools/build-prototypes.js` (`page-dashboard.html`, `page-nouveau-client.html`, `page-acces-refuse.html`), imported with `tools/configs/pages-admin.json`. Look up artboard indexes by name before calling `drawPage` (artboards get added and removed).
- **01** has no top bar (Admin WP and the user live in the sidebar). Title "Bonjour, Mark S !" + mono weekly line, the standard toolbar, then client cards (`.cc`, 262 px, 4 columns): avatar 40 in the client colour, name, mono project count, Actif / Inactif pill, Modifier / Projets, copy-link and access-key icon buttons. Inactive clients have a `--ag-text-3` name.
- **02** is 01 under a `rgba(14,14,16,.78)` scrim with a 480 px modal: name (focus), logo URL + Choisir, accent colour (swatch + hex + 5 presets), Annuler / Créer le client.
- **08** is public (no sidebar, no client colour): logo placeholder, 56 px tile with the two-tone `lock` icon (replaces the emoji), title, explanation, secondary button, mono `Erreur 403 · …` footer.

## 5. Open points
- Sidebar *Commentaires* / *Fichiers reçus*: left out until they have a destination (page or popover listing unread comments / new uploads).
- Client *Image de fond*: kept on the edit page (03) and in the data, but pages 04/05 have no cover banner, so it is not displayed in the portal for now.
- Block widths: the project grid is 4 columns; `grid_width` 1–4 (resizable by managers). Migration 2.9.0 mapped the old full-width value (3 of 3) to 4.
- Block detail pages (11–17) reuse the existing per-type markup inside the new head / description bar / two-column layout; the versions list of 13 is shown as chips above the media rather than as a side panel.
- "«Modify" annotation on the reference's *Messages* row was interpreted as "adapt this item": it became *Commentaires* (unread client comments). *Fichiers reçus* was added for client uploads. Confirm or replace.
- Projects use a grayscale folder icon in the sidebar (per "Use grayscale icons"). Project emojis remain on project pages. Decide whether the sidebar should show the emoji instead.
- Tag colours: the reference uses multi-colour tags; they were kept neutral here (client colour on the selected type). Switching to tinted tags is a CSS change in `ag-components.css`.
- The primary button uses dark text on the client colour (better contrast across client colours than white). Confirm.
- Artboards 03 and 06–17 still use the old layouts and should be rebuilt from the artboard-18 components.
- The page artboards (01–06, 10–17) were moved onto the new palette and fonts automatically. Their layouts are still the old ones and need a proper redesign around the 280 px sidebar column.
