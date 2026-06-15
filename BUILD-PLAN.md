# Build Plan — Chada Duplicate

> Durable roadmap for building the plugin. Spans multiple sessions — when you
> resume, read **§ Current status** first, then continue from the first
> unchecked box.

A **free** one-click clone plugin for posts, pages, CPTs, and WooCommerce
products (incl. variations). The lead-magnet that cross-sells the paid suite.

- **Spec / scope source of truth:** `CLAUDE.md` (locked MVP decisions live there).
- **Conventions:** `CLAUDE.md` (`CHADA_DUP_*`, `cdup_*`, `Chada\Duplicate`).
- **Licensing model:** FREE, no gating. Ships the **Updater only** (no
  `License`/`LicenseClient`). Slug `chada-duplicate` already registered in CLM
  `$product_configs` as a single `lite` entry so `/update-check` serves it.

---

## Current status

**Session log (newest first):**
- 2026-06-14 (Phase 6) — Cross-sell + release on `feature/phase-6-crosssell`:
  `Admin\CrossSell` renders the "More by Chada" panel (Screen D) after the
  settings form + a footer link, dismissible per-user (`cdup_crosssell_dismissed`
  meta); `assets/css/settings.css` is the one custom-styled surface;
  `tools/build-release.sh` builds the ZIP + manifest into the CLM releases dir.
  **Verified live** (panel renders per Screen D, Dismiss hides it + persists,
  release ZIP excludes dev files). MVP feature-complete.
- 2026-06-14 (Phase 5) — Settings page on `feature/phase-5-settings`: `Settings`
  repository + `Admin\SettingsPage` (post types, default status, title suffix,
  also-copy author/comments/price, excluded meta keys, allowed roles) wired into
  the duplicator; Plugins-row "Settings" link added. **Verified live** (renders
  per Screen C, saves/persists, suffix drives the clone). Fixed two sanitize
  bugs found live: double-sanitize `"Array"` corruption + suffix space trimming.
- 2026-06-14 (Phase 4) — WooCommerce product cloning on `feature/phase-4-products`:
  `ProductDuplicator` (extends `Duplicator`) clones via WC CRUD — product type,
  attributes, variations, gallery, downloadable files, SKU (unique-suffixed),
  price (filterable copy/skip). `Duplicator::for_post()` routes products there;
  our row/bulk action replaces WooCommerce's native product Duplicate; product-
  aware bulk notice. **Verified live** on a variable-subscription product
  (3 variations + prices recreated, featured image/attr/category copied) + the
  product bulk notice. EditorButton skips products (WC owns the edit screen).
- 2026-06-14 (Phase 3) — Editor "Copy to a new draft" on
  `feature/phase-3-editor-button`: block-editor `PluginPostStatusInfo` +
  `PluginMoreMenuItem` (plain JS in `assets/js/editor.js`, no build) and classic
  `post_submitbox_misc_actions` link, both reusing the single-duplicate handler
  with `cdup_redirect=editor` → redirects to the new draft's editor.
  **Verified live** (block editor: control + ⋯ menu + clone→redirect + featured
  image copy). Classic-editor link not exercised (site uses block editor).
- 2026-06-14 (Phase 2) — Core duplicator built on `feature/phase-2-duplicator`:
  `Duplicator::clone_post()` (core fields, taxonomies, all non-internal meta —
  featured image + page template ride along as meta), row action + bulk action
  on posts/pages/public CPTs (products excluded until Phase 4), per-post nonce +
  capability checks, and the Screen-A success/bulk/error admin notices. Awaiting
  review/merge.
- 2026-06-14 (Phase 0+1) — Built scaffold (`chada-duplicate.php` main file,
  autoloader, boot, `Installer`, `uninstall.php`) + ported the **Updater**
  (`Licensing/UpdateClient` + `Licensing/Updater`, free — no `LicenseClient`).
  On branch `feature/phase-0-scaffold`, awaiting review/merge. Slug stays
  `chada-duplicate` (naming briefly flirted with "chada-clone", reverted).
- 2026-06-05 (scaffold) — Folder + `CLAUDE.md` (MVP spec) + this plan created.
  Slug registered server-side in CLM. No plugin code yet.

**Next up:** MVP feature-complete. Remaining ops (not plugin code): register the
free product on the storefront, and verify `/update-check` end-to-end once CLM is
reachable + a release is published via `tools/build-release.sh`.

---

## Phase 0 — Scaffold
- [x] `chada-duplicate.php` main file: header with
      `Update URI: https://shop.chadacreatives.com/cdup-update`, constants
      (`CHADA_DUP_*`, `CHADA_DUP_PLATFORM_URL`), SPL autoloader, `plugins_loaded`
      boot, `uninstall.php`. No DB table. (No WooCommerce hard-dependency —
      degrade product features gracefully if WC is absent.)

## Phase 1 — Updates only (port the Updater, NOT the License)
- [x] Port ONLY `includes/Licensing/{UpdateClient,Updater}` from
      `chada-activity-monitor`; rename `CHADA_AM_*` → `CHADA_DUP_*`, fix the
      `Update URI` host match. Do NOT port `License`/`LicenseClient` — nothing to
      gate.
- [ ] Verify `/update-check` 200 for the free slug (no license sent) — needs CLM
      reachable; verify after merge / when the platform is up.

## Phase 2 — Core duplicator (posts / pages / CPTs)
- [x] `Duplicator::clone_post($post_id)`: title (+ " (copy)" suffix), content,
      excerpt, status (default draft), author, parent, menu order, slug (fresh,
      unique), **all taxonomies**, **all post meta** (skip internal keys —
      `_edit_lock`, `_edit_last`, `_wp_old_slug`, `_wp_old_date`, …; filterable
      via `cdup_excluded_meta_keys`). Featured image + page template ride along
      as meta.
- [x] Row action + bulk action on Posts / Pages / public CPTs (products excluded
      until ProductDuplicator, Phase 4).
- [x] **Security:** per-post nonce + capability check on the single action;
      core bulk nonce + per-post cap on the bulk action. No unauthenticated GET.

## Phase 3 — Editor integration
- [x] "Copy to a new draft" button in block + classic editors → clone → redirect
      to the new draft's editor. (Block: `PluginPostStatusInfo` +
      `PluginMoreMenuItem` via `assets/js/editor.js`. Classic:
      `post_submitbox_misc_actions`. Shared `cdup_redirect=editor` handler.)

## Phase 4 — WooCommerce product cloning (decision #2 — in MVP)
- [x] `ProductDuplicator` extends `Duplicator`: product type, attributes,
      **variations (child posts)** re-created + re-linked, gallery, downloadable
      files, SKU handling (unique-suffixed via `wc_product_generate_unique_sku`),
      price (configurable copy/skip via `cdup_product_copy_price`). Clones via WC
      CRUD so lookup tables stay in sync.
- [x] Row/bulk action on the Products list; `edit_products` cap (inherited from
      the product post type). Replaces WooCommerce's native product Duplicate.

## Phase 5 — Settings
- [x] Enabled post types, default status of the copy, title suffix, meta-key
      exclusion list, **which roles** may duplicate, plus also-copy
      author/comments and product price. One `cdup_settings` option via
      `Settings` + `Admin\SettingsPage`; Plugins-row "Settings" link added.
- [x] Don't copy revisions or comments by default (comments opt-in via setting).

## Phase 6 — Cross-sell (decision #3 — tasteful only) + release
- [x] One dismissible "More by Chada" panel on the plugin's own settings screen +
      a small footer link. **NO** store-wide notices, **NO** activation redirect,
      **NO** dashboard nag. (`Admin\CrossSell` + `assets/css/settings.css`,
      per-user `cdup_crosssell_dismissed` meta.)
- [x] `tools/build-release.sh` → ZIP + `manifest.json` into the CLM releases dir.
- [ ] Storefront listing as a free product (funnel to the paid suite). — OPS task,
      done on the storefront/CLM, not in this plugin's code.
