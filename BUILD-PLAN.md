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
- 2026-06-05 (scaffold) — Folder + `CLAUDE.md` (MVP spec) + this plan created.
  Slug registered server-side in CLM. No plugin code yet.

**Next up:** Phase 0.

---

## Phase 0 — Scaffold
- [ ] `chada-duplicate.php` main file: header with
      `Update URI: https://shop.chadacreatives.com/cdup-update`, constants
      (`CHADA_DUP_*`, `CHADA_DUP_PLATFORM_URL`), SPL autoloader, `plugins_loaded`
      boot, `uninstall.php`. No DB table. (No WooCommerce hard-dependency —
      degrade product features gracefully if WC is absent.)

## Phase 1 — Updates only (port the Updater, NOT the License)
- [ ] Port ONLY `includes/Licensing/{UpdateClient,Updater}` from
      `chada-activity-monitor`; rename `CHADA_AM_*` → `CHADA_DUP_*`, fix the
      `Update URI` host match. Do NOT port `License`/`LicenseClient` — nothing to
      gate.
- [ ] Verify `/update-check` 200 for the free slug (no license sent).

## Phase 2 — Core duplicator (posts / pages / CPTs)
- [ ] `Duplicator::clone($post_id)`: title (+ " (copy)" suffix), content, excerpt,
      slug, status (default draft), author, parent, menu order, page template,
      **all taxonomies**, **all post meta** (skip internal keys — `_edit_lock`,
      `_edit_last`, `_wp_old_slug`, `_wp_old_date`, …; filterable exclusion list),
      featured image.
- [ ] Row action + bulk action on Posts / Pages / public CPTs.
- [ ] **Security:** per-post nonce + capability check (`edit_posts`) on every
      clone; never clone from an unauthenticated GET.

## Phase 3 — Editor integration
- [ ] "Copy to a new draft" button in block + classic editors → clone → redirect
      to the new draft's editor.

## Phase 4 — WooCommerce product cloning (decision #2 — in MVP)
- [ ] `ProductDuplicator` extends `Duplicator`: product type, attributes,
      **variations (child posts)** re-created + re-linked, gallery, downloadable
      files, SKU handling (blank or suffix to avoid duplicate-SKU save errors),
      price (configurable copy/skip).
- [ ] Row/bulk action on the Products list; `edit_products` cap.

## Phase 5 — Settings
- [ ] Enabled post types, default status of the copy, title suffix, meta-key
      exclusion list, and **which roles** may duplicate.
- [ ] Don't copy revisions or comments by default.

## Phase 6 — Cross-sell (decision #3 — tasteful only) + release
- [ ] One dismissible "More by Chada" panel on the plugin's own settings screen +
      a small footer link. **NO** store-wide notices, **NO** activation redirect,
      **NO** dashboard nag.
- [ ] `tools/build-release.sh` → ZIP + `manifest.json` into the CLM releases dir.
- [ ] Storefront listing as a free product (funnel to the paid suite).
