# Design Brief — Chada Duplicate (admin UI)

**For:** a designer (human or a fresh Claude conversation with design skills)
**Deliverable requested:** Hi-fidelity mockups (full visual styling, real content) for every screen and state listed below.
**Visual direction:** **Native WordPress.** This is a wp-admin plugin. It must look and feel like built-in WordPress admin — default WP admin color scheme, typography, spacing, components. Most of this plugin is *small touches inside existing WP screens* (row actions, bulk actions, an editor button) rather than new pages. Do **not** design a standalone SaaS dashboard. When in doubt, match Posts → All Posts row actions, the Bulk Actions dropdown, and the block-editor Status & visibility panel.

> You do not need any other document. Everything required to design this is below.

---

## 1. Product context

Chada Duplicate adds **one-click cloning** for posts, pages, custom post types, and **WooCommerce products (with variations)**. Clone a row, bulk-clone a selection, or "Copy to a new draft" from inside the editor — copies fields, taxonomies, meta, featured image, and (for products) attributes/variations/gallery. It's a **free** plugin — the goodwill/lead-magnet that cross-sells the paid Chada suite.

**Primary users:** content editors, shop managers, and agencies who reuse posts/products as templates. The whole value is *fast and complete* — the clone should be one obvious click and copy everything that matters.

Because it's free, the **cross-sell panel is the business** — but it must be tasteful and never naggy (see §3 Screen D and §6).

---

## 2. Platform & constraints

- Renders **inside wp-admin** as additions to existing screens: list-table **row actions** + **bulk actions** (Posts, Pages, CPTs, Products), an **editor button** (block + classic), one **settings page**, and one **cross-sell panel** on that settings page.
- **Native WordPress admin UI**: list-table row hover actions, the Bulk Actions `<select>`, Settings API form-table, native buttons, admin notices, active color scheme.
- Content width **~1200px** down to responsive narrow/mobile admin.
- **Accessibility:** WCAG AA — contrast, keyboard navigable, visible focus. Don't rely on color alone for any status.

---

## 3. Screens to design

### Screen A — List-table affordances (the primary touchpoint)
Show the **All Posts / All Products** list table with the plugin's additions:
- A row-hover action **"Duplicate"** (alongside Edit / Quick Edit / Trash / View).
- A **"Duplicate"** entry in the **Bulk Actions** dropdown, plus the selected-rows state.
- The post-clone **admin notice**: *"'Spring Sale Landing Page' duplicated. Edit the copy →"* (success notice with a link to the new draft).
**States:** A1. Row hover showing the Duplicate action (Posts). A2. Products list variant (note the product-specific result). A3. Bulk action selected + confirmation notice. A4. Success notice after a single duplicate.

### Screen B — Editor button ("Copy to a new draft")
Where the clone entry point lives inside the editor:
- **Block editor** — a control in the Status & visibility panel (or the editor's "..." options menu) labeled "Copy to a new draft".
- **Classic editor** — a button in the Publish metabox.
Mock both so engineering knows placement. Include the brief transition feedback ("Duplicating…" → redirect to the new draft).

### Screen C — Settings page
Native WP settings page, sectioned:
- **Post types** — checkboxes for which types get the Duplicate action (Posts, Pages, Products, other public CPTs).
- **Copy behavior** — default status of the copy (Draft / same as original), title suffix (default " (copy)"), copy comments? (off), copy author?
- **Excluded meta keys** — a textarea/list of meta keys to skip when cloning (pre-filled with internal defaults like `_edit_lock`, `_wp_old_slug`).
- **Permissions** — which roles may duplicate (checkboxes).
Native form-table layout, section headings, description text, one "Save Changes" primary button.

### Screen D — "More by Chada" cross-sell panel (tasteful)
A single, **dismissible** panel on the **settings page only** (NOT a store-wide notice, NOT an activation redirect, NOT a dashboard nag). It promotes the paid Chada plugins (Cart Recovery, AI Content, Woo Schema, WooFraudGuard, Activity Monitor) as small cards: icon, one-line benefit, "Learn more" link to the marketplace. Calm, helpful, easy to dismiss. Design it so it reads as a friendly footer, not an ad. This is the one piece of custom styling worth getting right.

---

## 4. Visual / semantic direction

- **Native WP** — inherit the admin color scheme; introduce no custom palette except light accent on the cross-sell cards.
- The Duplicate action should feel **native and unremarkable** — like it was always part of WordPress.
- The cross-sell panel is the only place with a touch of Chada brand; keep it restrained and dismissible.

---

## 5. Data reference (use realistic content in mockups)

**Example posts/pages:** "Spring Sale Landing Page" (page), "10 Tips for Trail Running" (post).
**Example product:** "Trail Runner GTX Shoe" — variable product, sizes 7–12, 2 colors, 6 variations, image gallery — to illustrate that variations/gallery are copied.
**Success notice copy:** "'Spring Sale Landing Page' duplicated. Edit the copy →"
**Cross-sell cards (for Screen D):**
- Cart Recovery — "Win back abandoned carts automatically."
- AI Content — "Generate product descriptions in seconds."
- Woo Schema — "Rich-result schema for better search listings."
- WooFraudGuard — "Score and block fraudulent orders."
- Activity Monitor — "See who changed what, and when."

---

## 6. Out of scope (do NOT design these)

No paid tier exists for this plugin — there is no license/upgrade screen here. Do **not** design: scheduled/recurring duplication, cross-site cloning, any premium gating UI, or intrusive promotion (store-wide banners, activation redirects, dashboard nags). The cross-sell is limited to Screen D's single dismissible settings-page panel.

---

## 7. What to deliver

Hi-fi mockups for: A1–A4, B (block + classic), C, D. For each, note interaction behavior (hover reveals action, bulk apply, duplicate → redirect, dismiss panel). Flag any place where the native-WP constraint forced a tradeoff. Keep the overall feel: invisible utility + one tasteful cross-sell.
