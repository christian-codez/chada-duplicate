# Handoff: Chada Clone (wp-admin plugin)

## Overview
**Chada Clone** is a free WordPress plugin that adds **one-click cloning** for posts, pages, custom post types, and **WooCommerce products (including variations)**. It surfaces as small, native additions inside existing wp-admin screens — a list-table **row action**, a **bulk action**, an **editor button**, one **settings page**, and a single tasteful **cross-sell panel**. A clone copies fields, taxonomies, meta, the featured image, and (for products) attributes/variations/gallery, landing as a ready-to-edit draft.

The whole product philosophy: **invisible utility + one restrained cross-sell.** The Duplicate affordance must feel like it was always part of WordPress; introduce no custom palette anywhere except the cross-sell cards.

## About the Design Files
The files in this bundle are **design references created in HTML** — a slide deck of pixel-accurate mockups showing intended look and behavior. **They are not production code to copy directly.** This plugin renders **inside wp-admin (PHP)**, so the task is to implement these designs as a real WordPress plugin using **native WordPress APIs and markup** — `WP_List_Table` row/bulk actions, the Settings API (`add_settings_section` / `add_settings_field` / form-table markup), `admin_notices`, the block editor `PluginPostStatusInfo` slot (and/or the editor `...` `PluginMoreMenuItem`), and the classic-editor `post_submitbox_misc_actions` hook for the Publish metabox.

Critically: **do not build a standalone SaaS dashboard or a custom CSS framework.** Inherit wp-admin's existing styles. The HTML/CSS here recreates wp-admin's look so the mockups read truthfully — in the real plugin, most of these styles come for free from core. Only the cross-sell panel (Screen D) carries any custom styling.

## Fidelity
**High-fidelity (hifi).** Final colors, typography, spacing, copy, and interaction states are specified. Recreate pixel-accurately — but by **inheriting native wp-admin styles**, not by porting this bundle's CSS. Use `wp-admin.css` in this bundle only as a precise spec of the values core already provides (and as the literal spec for the Screen D panel, which is the one custom-styled surface).

Target environment: **WordPress admin (PHP + a small amount of JS for the block-editor slot, built with `@wordpress/scripts`).** Content width ~1200px down to responsive narrow/mobile admin. Accessibility target: **WCAG AA** — keyboard navigable, visible focus, and never rely on color alone for status.

---

## Screens / Views

### Screen A — List-table affordances (primary touchpoint)
The All Posts / All Pages / All Products list table with the plugin's additions. Two mockup slides cover four states (A1–A4).

- **Name:** List-table row action, bulk action, and result notices.
- **Purpose:** Clone a single row on hover, or clone a checked selection via Bulk Actions.
- **Layout:** Standard `WP_List_Table`: full-width white table, 1px `#c3c4c7` outer border, `thead` with 14px column labels, striped rows (`.alternate` → `#f6f7f7`), a tablenav above (bulk `<select>` + Apply button + item count + pager), `admin_notices` above the tablenav.
- **Components:**
  - **Row action "Duplicate"** — inline with Edit · Quick Edit · Trash · View inside `.row-actions`. 13px, link color `#2271b1`, separated by ` | ` (`#c3c4c7`). Hidden by default; revealed on `tr:hover` (native behavior). Same weight/color as sibling actions — it must look unremarkable. Trash uses `#b32d2e`.
  - **Bulk "Duplicate" `<select>` option** — a new `<option>` appended to the native Bulk Actions dropdown (NOT a custom JS menu). 13px native select, `#8c8f94` border; focused/open border `#2271b1`. Followed by the native **Apply** `.button`.
  - **Selected-rows state** — checked rows get `#f0f6fc` background; checkbox is the native input (checked = `#2271b1` accent).
  - **Success notice (single, A4)** — `.notice.notice-success.is-dismissible`: left border 4px `#00a32a`, white bg, 14px text. Exact copy: **`'Spring Sale Landing Page' duplicated.` `Edit the copy →`** where "Edit the copy →" is a link to the new draft's editor. Dismiss "×" top-right.
  - **Bulk confirmation notice (A3)** — same component, copy: **`2 products duplicated`** — including variations & gallery images. `View drafts →`.
  - **The copy as a draft** — appears at the top of the list as `… (copy)` with state `— Draft`.
  - **Products variant (A2)** — WooCommerce columns: thumbnail (46×46 `#dcdcde` placeholder), Name, SKU (`#646970`), Stock ("In stock" `#00a32a`, weight 600), Price (sale shows `<del>` + `<ins>`), Featured (★ `#dba617` / ☆ `#dcdcde`), Date. Example hero row: **"Trail Runner GTX Shoe" — Variable product**, SKU `TRG-GTX`, In stock, $149.00, featured. The product-specific result note: its clone carries all 6 variations, attributes (sizes 7–12, 2 colors), and the gallery.

### Screen B — Editor entry points ("Copy to a new draft")
- **Name:** Block editor control + classic metabox button.
- **Purpose:** Clone the post currently being edited.
- **Block editor:**
  - A control labeled **"Copy to a new draft"** at the bottom of the **Status & visibility** panel (implement via `PluginPostStatusInfo` from `@wordpress/edit-post`). 13px, copy icon, full-row.
  - Mirrored as a **`PluginMoreMenuItem`** in the editor's **`⋯` options menu** under a "Tools" group, with the highlighted entry "Copy to a new draft" (optional keyboard hint `⌥⇧D`).
- **Classic editor:** A row in the **Publish metabox** (hook `post_submitbox_misc_actions`), above "Move to Trash / Publish". Link `#2271b1`, weight 600, copy icon.
- **Transition feedback:** On click, the control shows **"Duplicating…"** (brief spinner state), then **redirects to the new draft** in the same editor.

### Screen C — Settings page
- **Name:** Chada Clone settings (`Settings →` or its own top-level item).
- **Purpose:** Configure which types get the action, copy behavior, excluded meta, and permissions.
- **Layout:** Native Settings API. Page `<h1>` "Chada Clone" (23px/400). Sections as `<h2>` (18px/600) each followed by a `.form-table` (`<th>` labels 220px wide, 14px/600; `<td>` controls; `.description` helper text `#646970` 13px). One primary **Save Changes** button at the bottom.
- **Sections & fields:**
  1. **Post types** — checkboxes: Posts ✓, Pages ✓, Products ✓, other public CPTs (e.g. "Landing Pages (CPT)" unchecked).
  2. **Copy behavior** — Default status radios (**Draft** ✓ / Same as original); Title suffix text input (default `" (copy)"`); Also copy: Author ✓, Comments ✗.
  3. **Excluded meta keys** — monospace `<textarea>`, one key per line, pre-filled with internal defaults: `_edit_lock`, `_wp_old_slug`, `_edit_last`.
  4. **Permissions** — role checkboxes: Administrator ✓, Editor ✓, Shop Manager ✓, Author ✗.

### Screen D — "More by Chada" cross-sell panel (the one custom-styled surface)
- **Name:** Cross-sell footer panel — **settings page only**.
- **Purpose:** Tasteful promotion of the paid Chada suite. Must read as a friendly footer, never an ad/nag.
- **Layout:** A single dismissible card at the **bottom of the settings page only** (NOT a store-wide notice, NOT an activation redirect, NOT a dashboard nag). White card, 1px `#e4e6e9` border, **3px top border in Chada teal `#11776e`**, radius 10px, soft shadow.
  - **Header:** 48×48 teal rounded-square mark (megaphone icon, white), title **"More by Chada"** (27px/700), subline "Free tools that pair well with Chada Clone — no upsell, no lock-in." (20px `#50575e`). A **Dismiss** control (× + label) top-right, bordered `#e4e6e9`.
  - **Cards:** 5-up grid, each on a light teal tint `#f1f7f6` with border `#dcebe9`, radius 10px. Each card: 44×44 white icon chip (teal icon), title (22px/700), one-line benefit (19px `#50575e`), and a teal **"Learn more →"** link.
    - Cart Recovery — "Win back abandoned carts automatically."
    - AI Content — "Generate product descriptions in seconds."
    - Woo Schema — "Rich-result schema for better search listings."
    - WooFraudGuard — "Score and block fraudulent orders."
    - Activity Monitor — "See who changed what, and when."
- **Behavior:** Dismiss hides it **permanently, per user** (store a user meta flag). Never reappears.

---

## Interactions & Behavior
- **Row hover** → reveals `.row-actions` including Duplicate (native; CSS `visibility`).
- **Single duplicate** → Hover row → Duplicate → redirect/refresh with success `admin_notice` → copy appears as a draft; "Edit the copy →" links to the new draft editor.
- **Bulk duplicate** → Check rows → Bulk Actions: Duplicate → Apply → one summary notice for the batch.
- **From the editor** → "Copy to a new draft" → button shows "Duplicating…" → redirect to the new draft in the same editor.
- **Dismiss cross-sell** → panel removed for good (per-user meta); no reappearance.
- **Transitions:** brief spinner on the editor action only; otherwise standard wp-admin full-page reloads. No decorative animation.
- **Responsive:** inherits `WP_List_Table` responsive behavior (row actions collapse on narrow/mobile admin). Nothing custom to maintain.

## State Management
This is server-rendered PHP; "state" is mostly WordPress data, not client state.
- **Plugin options** (one option array via Settings API): enabled post types, default copy status, title suffix, copy-author flag, copy-comments flag, excluded meta keys (array), allowed roles (array).
- **Per-user meta:** `chada_crosssell_dismissed` (bool) for Screen D.
- **Block-editor JS state:** the only real client state — the "Duplicating…" pending flag on the editor control while the clone request resolves, then `window.location` redirect to the new draft.
- **Capability checks:** gate the row action, bulk action, and editor control on the configured allowed roles / a `duplicate_posts` capability.

## Design Tokens
All inherited from the wp-admin "Fresh" color scheme except the Chada accent.

**Colors**
- Admin chrome / sidebar / admin bar: `#1d2327` · submenu `#2c3338`
- Sidebar text `#f0f0f1` · sub `#c3c4c7` · icon `#a7aaad` · current item bg `#2271b1`
- Primary / link `#2271b1` · hover `#135e96`
- Canvas `#f0f0f1` · surface `#fff` · borders `#c3c4c7` / `#dcdcde` · stripe `#f6f7f7`
- Text `#1d2327` (headings) · `#3c434a` (body) · `#646970` (muted)
- Success `#00a32a` · Warning `#dba617` · Destructive `#b32d2e` · Info `#72aee6`
- **Chada accent `#11776e`** · panel tint `#f1f7f6` · tint border `#dcebe9` (Screen D only)

**Typography**
- Font stack: `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif`
- Base 13px · page `h1` 23px/400 · section `h2` 18px/600 · table 14px · row actions 13px · form-table `th` 14px/600 · `.description` 13px `#646970`

**Buttons**
- Secondary `.button`: text/border `#2271b1`, bg `#f6f7f7`, radius 3px, min-height 30px
- Primary `.button-primary`: bg `#2271b1`, white text, hover `#135e96`

**Notices**
- `.notice`: white bg, 4px left border (success `#00a32a` / info `#72aee6` / warning `#dba617`), dismiss "×" top-right

**Radius / shadow**
- wp-admin surfaces: radius 0–3px, shadow `0 1px 1px rgba(0,0,0,.04)`
- Screen D panel/cards only: radius 10px, soft shadow

## Assets
- **Icons:** the mockups use simple inline monoline SVGs as stand-ins for **WordPress Dashicons** (sidebar, admin bar) and the block-editor icons. In the real plugin, use Dashicons / `@wordpress/icons` rather than these SVGs.
- **Cross-sell card icons:** simple line icons (cart, sparkles/AI, schema nodes, shield, activity pulse) — supply final brand icons from the Chada brand system if available; otherwise Dashicons equivalents are fine.
- **Product images:** placeholder tiles only — real product thumbnails come from WooCommerce.
- No bitmap assets are required to ship.

## Files
In this bundle:
- `Chada Clone - Design Handoff.html` — the 10-slide mockup deck (open in a browser to view; arrow keys / thumbnail rail to navigate). Primary visual reference.
- `wp-admin.css` — precise spec of the native wp-admin component values used (and the literal spec for the Screen D panel). Reference for measurements/colors; not meant to ship.
- `deck-stage.js` — only the slide-viewer shell for the HTML deck; **not part of the product.**

### Mapping mockup → WordPress implementation
| Mockup (slide) | WordPress hook / API |
|---|---|
| Row action "Duplicate" | `post_row_actions` / `page_row_actions` / `{post_type}_row_actions` filters |
| Bulk "Duplicate" | `bulk_actions-{screen}` filter + `handle_bulk_actions-{screen}` |
| Success / bulk notices | `admin_notices` (transient or query-arg driven) |
| Editor control (block) | `PluginPostStatusInfo` + `PluginMoreMenuItem` (`@wordpress/edit-post`) |
| Editor button (classic) | `post_submitbox_misc_actions` |
| Settings page | Settings API (`add_options_page`/`add_menu_page`, `register_setting`, `add_settings_section`, `add_settings_field`) |
| Cross-sell panel | Custom markup rendered after the settings form, gated on per-user dismissed meta |
| Clone engine | `wp_insert_post` + copy of taxonomies (`wp_set_object_terms`), post meta (minus excluded keys), featured image, and — for products — WooCommerce variations/attributes/gallery |
