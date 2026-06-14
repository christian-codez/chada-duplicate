# Chada Duplicate — plugin notes for Claude

**STATUS: MVP scaffold — no code yet.** This file is the contract/spec.

A **one-click duplicate / clone** plugin for posts, pages, any public custom post
type, and **WooCommerce products** (incl. variations). Row action + bulk action +
an editor "Copy to a new draft" button. Positioning: *fast, complete clones that
actually copy everything* (meta, taxonomies, featured image, product
variations) — the common free plugins miss product data.

**This is the FREE lead-magnet product**, not a paid subscription (see strategy
note below). It still lives in the CHADA tree and is distributed through the
marketplace, so it ships the **Updater only** to receive updates from the
platform — but it has **no license gating** and no premium tiers. **Native
wp-admin UI — no SPA.**

### Locked MVP decisions (agreed with the user 2026-06-05)
1. **Free-only lead magnet** — no paid tier, no `License`/`LicenseClient`, no
   gating. Ships the Updater only; registered as a free product in CLM.
2. **WooCommerce product cloning is IN the MVP** — full product type, attributes,
   variations (child posts), gallery, SKU handling. This is the differentiator
   vs. the free competitors that botch product data; it ships day one.
3. **Cross-sell = tasteful in-plugin only** — one dismissible "More by Chada"
   panel on the plugin's own settings screen + a small footer link. **No**
   store-wide admin notices, **no** activation redirect, **no** dashboard nag.
   Goodwill is the entire point of the free product.

## Strategy: free funnel, not revenue
Duplicating content is a *feature*, not a business (the leading free competitor
has millions of installs). We ship it free to:
- build install base + goodwill + reviews on the marketplace,
- **cross-sell the paid suite** (`chada-cart-recovery`, `chada-ai-content`,
  `chada-woo-schema`, `woofraudguard`, `chada-activity-monitor`) via a tasteful
  "More by Chada" admin panel — NOT nag spam (see Gotchas).

## Licensing (updates only — no gating)
- Port ONLY `UpdateClient` + `Updater` from `chada-activity-monitor` (skip
  `License`/`LicenseClient` — there's nothing to gate). Rename `CHADA_AM_*` →
  `CHADA_DUP_*`. Platform host `CHADA_DUP_PLATFORM_URL` (default
  `https://shop.chadacreatives.com`); `Update URI: …/cdup-update` host MUST match.
- Register slug `chada-duplicate` in CLM as a **free** product so `/update-check`
  serves it without a license. **Cross-product rule** still applies to any
  `update-check`/`download` shape change.

## MVP scope (build this, nothing more)
In:
1. **Row action** "Duplicate" on Posts, Pages, public CPTs, and Products list.
2. **Editor button** "Copy to a new draft" (block + classic editor).
3. **Deep copy:** title (+ " (copy)" suffix), content, excerpt, slug, status
   (default `draft`), author, parent, menu order, page template, **all
   taxonomies**, **all post meta** (except internal keys — see Gotchas),
   featured image.
4. **WooCommerce-aware copy:** product type, attributes, **variations (child
   posts)**, gallery, downloadable files, SKU handling (blank or suffixed to
   avoid duplicate-SKU errors), price (configurable copy/skip).
5. **Settings:** which post types are enabled, default status of the copy, the
   title suffix, which meta keys to skip, and **which roles** may duplicate.

Out (no premium tiers — this product is free): scheduled/bulk-template cloning,
cross-site cloning, etc. If a paid clone feature is ever wanted, it belongs in a
*different* product, not here.

## Architecture (one-way)
```
Row/bulk action or editor button → Duplicator::clone($post_id)
  → copy core fields → copy taxonomies → copy meta (skip internal)
  → copy featured image → if product: ProductDuplicator (variations, attrs, gallery)
  → return new draft id → redirect to its editor
```
- **Duplicator** is the single clone path; **ProductDuplicator** extends it for
  Woo. Capability + nonce checked before either runs.

## Conventions (follow exactly)
- Namespace `Chada\Duplicate`; `includes/` via SPL autoloader.
- Prefixes: constants `CHADA_DUP_*`, options `cdup_*`, CSS/JS `cdup-*`, text
  domain `chada-duplicate`. (No DB table.)
- **Security:** every duplicate action requires a per-post nonce AND a capability
  check (default `edit_posts` / `edit_products`, further narrowed by the
  role setting). Never clone from an unauthenticated/GET link without nonce.

## Gotchas specific to this plugin
- **Skip internal meta** on copy: `_edit_lock`, `_edit_last`, `_wp_old_slug`,
  `_wp_old_date`, and any plugin lock/transient-ish keys — copying them corrupts
  the new post. Maintain an exclusion list (filterable).
- **Products = parent + children.** Variations are child posts of the product;
  cloning the parent alone produces a broken variable product. ProductDuplicator
  must recreate each variation and re-link attributes.
- **Duplicate SKU errors:** Woo enforces unique SKUs — blank or suffix the cloned
  SKU, or the save fails.
- **Don't copy revisions** or comments by default (revisions especially —
  cloning them bloats the DB).
- **Cross-sell tastefully:** one dismissible panel on the plugin's own settings
  screen + a small "More by Chada" link. NO store-wide nag notices, NO redirect
  on activate. Goodwill is the whole point of shipping this free — don't burn it.
```
