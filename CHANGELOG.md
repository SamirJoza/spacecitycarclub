# Changelog

All notable changes to this theme are documented in this file.

## Versioning (this project)

- **Source of truth:** `style.css` header field `Version:` (WordPress reads it for updates and theme list).
- **Bumps per calendar day:** Use **one** version increment per **calendar day** of theme work. Further changes the **same day** reuse that version number: **do not** bump `Version:` again; **append** new bullets under the existing `## [version] - YYYY-MM-DD` section (use the correct subsection: Added / Changed / Fixed). The **first** change of a new calendar day gets a new minor bump and a **new** changelog heading for that date.
- **Default increment** when you bump: increase the **minor** segment by `0.01` (e.g. `1.21` → `1.22`), unless the change set is **fundamental** (architecture, breaking public APIs, large migrations) — then increase **major** and reset minor as appropriate (e.g. `1.x` → `2.0`).
- **Process:** Keep `style.css` `Version:` and this changelog aligned. On the **first** change of a day, bump the version and add the `## [version] - date` block; on **later** changes that day, only extend that block (and code), not the version number.
- **Dates:** The `## [version] - YYYY-MM-DD` heading uses the **calendar date for that version bump** (the day you opened that version). Do not add a second version heading for the same date unless you are correcting the log.

Format below is inspired by [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.37] - 2026-10-05

Cleanup pass after a full theme audit (dark mode, icon font, shared tokens). No new features.

### Changed

- **Dark mode:** Tailwind `dark:` classes now follow the site's theme switch (`<html class="dark">`) instead of the visitor's operating system (`@custom-variant dark` in `resources/css/app.css`).
- **Theme boot script:** one shared partial, `resources/views/partials/theme-boot.blade.php`, included by all six layouts (plain-PHP copy in `header.php`). It now uses the same rule as `resources/js/components/theme.js`: saved choice, otherwise dark. Before, the boot script followed the OS while `theme.js` defaulted to dark.
- **Material Symbols:** Outlined only, self-hosted from the npm package for both the frontend (`app.css`) and the block editor (`editor.css`). The Google Fonts icon requests in `app/setup.php` are gone.
- **Brand tokens:** the headline gradients and accent colors (`--sccc-gradient-*`, `--sccc-color-*`) live in the new `resources/css/tokens/brand.css`. The copies inside the Benefits Proof, Card Grid, Event Impact Flow, Feature, Hero, Image Text Split, Masthead and Section Header blocks are removed.
- **Blocks:** SCCC Accordion, Memberships Tiers Grid and Membership CTA print their styles once per page (`@once`) instead of once per block.
- **Build:** `public/build` rebuilt. The previous build was missing about 40 utility classes used by templates (mostly the event signup pages).

- **Shared surface styles:** new `resources/css/components/surfaces.css` with reusable classes lifted unchanged from the Member Directory: `.sccc-surface` (card, with `--panel`, `--accent`, `--lift`), `.sccc-control` and `.sccc-control-label` (fields), `.sccc-pill` (utility button, `--accent`), `.sccc-chip` and `.sccc-pager`. The Member Directory template and card partial now use these classes; about 190 lines of inline CSS are gone from the template and the page renders the same.

- **Design tokens:** new `resources/css/tokens/scale.css` holds the shape, depth, motion and layout values (radius, hairline borders, shadows, durations, easing, text measure). `colors.css` and `typography.css` now declare the names that views already referenced but nothing defined: `--color-primary`, `--color-primary-hover`, `--color-accent-400`, `--color-signal-red`, `--background-dark`, `--color-field-bg`, status colors, and `--font-headline` / `--font-body`. Section headers and other elements that asked for the headline font now get the display face instead of falling back to the body face; elements that asked for `--color-primary` now get the brand blue instead of their own hardcoded fallback.
- **Components on tokens:** `surfaces.css` and `cards.css` read radius, borders, shadows and motion from the tokens instead of repeating literal values.
- **Section header:** layout and type moved from the Section Header block's per-instance inline CSS into `resources/css/components/section-header.css` (also loaded in the editor). The block keeps only its headline-highlight rules inline.
- **Blade UI components:** `<x-ui.surface>`, `<x-ui.pill>`, `<x-ui.chip>`, `<x-ui.control-label>` and `<x-ui.section-header>` in `resources/views/components/ui/` wrap the shared classes for use in templates and blocks.

### Fixed

- **Editor styles:** a stray comma in `resources/css/editor.css` made browsers drop the white-text rule for hero gradient backgrounds.
- **Cart badge:** removed a leftover `color: red` in `resources/views/partials/eyebrow.blade.php`.
- **Undefined CSS variables:** `footer-nav.css` now uses `--font-display`, `--font-sans` and `--color-on-primary`; the author page and member directory use `--color-canvas` where they referenced the non-existent `--color-bg`. Footer headings now render in the display font, and the background gradients on those two templates now apply.
- **Sponsor sync:** `SponsorApcSync::DEBUG` is off.

### Removed

- Material Symbols Rounded and Sharp font files from `public/build` (about 8.5 MB, never used).
- **Dead code:** `app/Support/Admin/Users/ManualRoleAssignment.php` (superseded by `UserRoles/SecondaryRoleAssignment`), `app/Support/Members/MemberRole.php` and `app/Support/CPT/LeadershipUserPicker.php`. None of them was loaded.
- **Unused filter:** the `sccc_pmpro_club_level_ids` hook in `app/filters.php` (nothing ever applied it).
- **Scaffold leftovers:** the `my-block` placeholder script and stylesheet, and the **Custom Template** and **Typography Test** page templates (no page used them).

### Added

- **Type scale safelist:** `resources/css/tailwind-safelist.css` keeps the `text-h1`–`text-h6` and `text-body*` utilities in the build, so they stay available to classes typed in the editor.

- `style.css`: `Version` `1.36` → `1.37`.

## [1.36] - 2026-10-04

### Added

- **Vendors:** `vendor` post type with one record per business (`app/Support/Vendors/`, `app/Fields/Vendor.php`). Application → manual approval → Stripe payment, returning-vendor magic link, food-vendor rule, CSV exports. Gravity Forms are built in code (`VendorForms.php`); see `resources/gravity-forms/README-vendors.md`.
- **Vendor blocks:** **Vendor Sign-up** (`app/Blocks/VendorSignup.php`) and **Featured Vendors** (`app/Blocks/FeaturedVendors.php`) with their Blade views.
- **Club Show settings on events:** ACF box in the Classic editor (`app/Fields/EventVendorSettings.php`) and a **Club Show** step in MagePeople's Modern event editor (`EventVendorModernEditor.php`).
- **Car show registration:** own Gravity Forms + Stripe form for club car shows (Category "Car Show" + Organizer "Space City Car Club") in `app/Support/CarShows/`. Owner details once, one block per car, price taken from the event's MagePeople tickets (early bird supported), price and quantity enforced on the server. See `resources/gravity-forms/README-car-shows.md`.
- **Registered Cars:** `registered_car` post type with per-show car numbers, show filter and CSV export; shared club edit capability (`AdminAccess`, `OperationalRoles`).
- **MagePeople template overrides:** `mage-event/layout/registration.php` and `seat_status.php` — car shows get the registration form, a "Cars registered" count and a "Vendors wanted" card; other events keep the plugin templates.
- **Theme Settings:** **Car Show Settings** page (`app/Options/CarShowSettings.php`); Vendor Settings extended.
- **Shared form styling:** `app/Support/Forms/GlassForms.php` — neon-glass fields and buttons for the vendor and car forms.
- **Product color dots:** `app/Support/Woo/ProductSwatches.php` resolves a color for each of a product's color options from its name (built-in garment palette, word-based guessing, highlighter shades for "Safety …"), with a **Color dots** box on the product edit screen for manual picks (saved site-wide by color name). Filters `sccc_product_swatches`, `sccc_swatch_palette`.
- **Product gallery:** thumbnails follow the selected color (front, back, neck label), grouped from the gallery order; lightbox on the main image with keyboard and arrow navigation.

### Changed

- **Single product:** larger color dots with a visible ring; unknown color names show a neutral dot instead of the theme gradient.

### Fixed

- **Single product:** the JSON data attributes (variation matrix, color dots) were double-escaped and could not be read by the page script; the script now decodes them, so impossible color/size combinations are disabled as intended.

### Removed

- `resources/gravity-forms/vendor-application.json`, `vendor-payment.json` (forms are now created from code).

- `style.css`: `Version` `1.35` → `1.36`.

---

## [1.35] - 2026-05-15

### Added

- **Thank You ACF:** `app/Fields/ThankYou.php` — sidebar **Visual style** select (**Nebula**, **Aurora**, **Signal**) driving hero vignette, badge, callout rail, orbit, and headline gradient accents in `thank-you.css`.
- Filters `sccc_thank_you_display_headline`, `sccc_thank_you_visual_variation` for the thank-you partial.

### Changed

- **Thank You public title:** H1 and browser/SEO title use **Thank you** (filter `sccc_thank_you_document_title`); the editor **post title** is admin-only (`document_title_parts`, Yoast `wpseo_title`, Rank Math `rank_math/frontend/title`). Title field placeholder clarifies it is not shown on the site.
- **Thank You CTAs:** dedicated **`sccc-thank-you__btn`** pill (cyan→magenta gradient border, dark frosted glass, uppercase label, circular arrow chip); **`sccc-thank-you__btn--secondary`** variant. **Unlayered** link reset above `@layer` in `thank-you.css` so global link underlines cannot override (same cascade strategy as `blog-list.css`).
- **Thank You variations:** read **Visual style** from post meta first (then `get_field`) so the picker applies reliably; **Aurora** / **Signal** now diverge clearly from **Nebula** (photo filters, colour wash layer, layout, badge shape, callout shell, decorative column glow, orbit icon, optional shell clip). **CTAs** use matching fab chips so both buttons share the same footprint. **Rotating radar sweep** (`sccc-thank-you__radar-sweep`) + stronger orbit rings on **Nebula** to match the other styles; removed the secondary “Space City” corner chip as out of context.

- `style.css`: `Version` `1.34` → `1.35`.

---

## [1.34] - 2026-05-13

### Added

- Custom post type **Thank You** (`thank_you`): hierarchical, **block editor** (`show_in_rest`, `editor` + `page-attributes`), optional featured image and excerpt; public URLs under `/thank-you/…`. File: `app/Support/CPT/thank_you.php`. Same shared club edit capability mapping as minutes/sponsors/etc. (`OperationalRoles`).
- **Thank You front template:** `single-thank_you.blade.php` (extends **`layouts.app`**), `partials/content-single-thank-you.blade.php` — Stitch-style **content-area** hero with **featured image as background** (filter `sccc_thank_you_background_image_url`), vignette + glass panels, Space City tokens for **light/dark** (`html[data-theme]` / `.dark`). Styles: `resources/css/layouts/thank-you.css`.

- `style.css`: `Version` `1.33` → `1.34`.

### Changed

- **Thank You singles:** use the **core** site shell (`layouts.app` — header, newsletter, footer) instead of a dedicated minimal layout; removed `resources/views/layouts/thank-you.blade.php`. Hero styling remains scoped to the content partial via `body.single-thank_you` in `thank-you.css`.

- Changelog **Versioning** rules: **one** `Version:` bump per **calendar day**; same-day work **appends** to that version’s section instead of incrementing again (see top of `CHANGELOG.md`).

### Fixed

- **Gravity Forms + Acorn (local):** `require_once …/gravityforms/includes/logging/includes/KLogger.php` failed because `GFLogging::include_logger()` uses a **relative** path resolved from the process **CWD**, not the plugin directory — PHP raised a warning that Laravel promoted to `ErrorException` (e.g. loading sponsor admin after CPT work). Added `app/Support/GravityForms/GravityFormsKLoggerBootstrap.php` to `require_once` the real `KLogger.php` on `after_setup_theme` priority `0` once `GF_PLUGIN_DIR_PATH` is defined, so GF’s `class_exists('KLogger')` short-circuits the broken require.

---

## [1.33] - 2026-05-14

### Fixed

- `SponsorApcSync`: removed the **post-sync** `get_post_meta('sponsor_source')` check that forced **Direct** when meta looked empty in the same request (stale `post_meta` object cache / ACF timing after `update_field`), which contradicted debug logs showing the real value.
- `updateAcfField`: after `update_field`, **flush post meta cache** and, if needed, **`update_post_meta`** for the value and **`_{field}` → field key** so ACF reference keys match what the editor expects (fixes empty `reference_key` in logs for APC/async runs).

- `style.css`: `Version` `1.32` → `1.33`.

---

## [1.32] - 2026-05-14

### Changed

- `SponsorApcSync`: when `sccc_referral_source` is set, it **takes precedence** over the GF hidden field (and the async stash prefers cookie over GF) so marketing attribution matches the cookie. Override with filter `sccc_sponsor_source_prefer_marketing_cookie` (pass `false` for GF-first behaviour). Debug log field `winner` is one of `cookie` | `transient` | `gf` | `direct`.

- `style.css`: `Version` `1.31` → `1.32`.

---

## [1.31] - 2026-05-11

### Fixed

- `SponsorApcSync`: Gravity Forms **queues feeds on shutdown** (loopback/async), so APC often runs **without browser cookies** and sometimes with a slimmer `$form` object — referral/source was falling through to **Direct**. Now: load full form via `GFAPI::get_form`, broader dynamic-parameter matching (`sccc_sponsor_gf_source_input_names`), optional single hidden-field heuristic, **`gform_after_submission` transient stash** (entry value or `sccc_referral_source` cookie), then cookie + transient fallbacks when syncing.

- `style.css`: `Version` `1.30` → `1.31`.

---

## [1.30] - 2026-05-11

### Fixed

- Sponsor **Source** (`sponsor_source`): admin saves no longer wipe the GF-synced value (read-only field omitted from `$_POST` → ACF cleared meta → default **Direct**). `sponsor_preserve_readonly_source_in_acf_post` restores the stored value into `$_POST['acf']` before ACF saves.
- `SponsorApcSync`: read referral/source via `GFFormsModel::get_lead_field_value` (with entry refetch fallback) so dynamically populated hidden fields resolve like other GF APIs; if no field ID constant/filter, auto-detect a **hidden** or **text** field whose dynamic parameter is `referral_source` (aligned with `GravityFormsCookiePopulation`).

- `style.css`: `Version` `1.29` → `1.30`.

---

## [1.29] - 2026-05-13

### Added

- Sponsor CPT: read-only ACF field **Source** (`sponsor_source` / `field_sponsor_source`), default **Direct** when empty on save.
- Gravity Forms APC sync: optional copy of a hidden GF field into **Source** via `GF_FIELD_SPONSOR_SOURCE_ID` or filter `sccc_sponsor_gf_source_field_id`.
- Sponsors admin list: **Source** column, **sortable** by source, **filter** dropdown (“All sources” / values from the database).

- `style.css`: `Version` `1.28` → `1.29`.

---

## [1.28] - 2026-05-13

### Changed

- `GravityFormsCookiePopulation`: removed the `[sccc_request_cookie_value]` shortcode; extension is only via **`defaultMap()`** or the **`sccc_gf_cookie_population_map`** filter (documented in the file header).

- `style.css`: `Version` `1.27` → `1.28`.

---

## [1.27] - 2026-05-13

### Added

- `app/Support/Marketing/GravityFormsCookiePopulation.php`: Gravity Forms dynamic population resolves **URL (GF parameter / extra query keys) → cookie → field default** via `gform_field_value`. Configurable map filter `sccc_gf_cookie_population_map` (default includes `referral_source` + `sccc_referral_source` cookie).

- `style.css`: `Version` `1.26` → `1.27`.

---

## [1.26] - 2026-05-13

### Added

- `TODO.md` at the theme root: conservative-dated roadmap and backlog (members, sponsors, vendors, UX, technical). **Vendor application page** is listed as the first priority.

- `style.css`: `Version` `1.25` → `1.26`.

---

## [1.25] - 2026-05-13

### Changed

- Operational accounts (with `sccc_view_dashboard` / backend access): after **WooCommerce** or **core** login, the default destination is now the **WooCommerce My Account** page instead of wp-admin. If `redirect_to` explicitly points somewhere (including `/wp-admin/…`), that URL is unchanged. wp-admin remains available via the admin bar or direct links.

- `style.css`: `Version` `1.24` → `1.25`.

---

## [1.24] - 2026-05-13

### Changed

- Sponsorship Signup block: tier **price** values are normalized on save (digits only, optional trailing `+`). Front-end shows a **`$` prefix** (always) with **locale-aware thousands separators** (`intl` `NumberFormatter` when available, otherwise a language fallback). Non-numeric legacy text still displays as plain escaped text.

- `style.css`: `Version` `1.23` → `1.24`.

---

## [1.23] - 2026-05-13

### Changed

- Changelog policy: release header dates must match the machine calendar date when the change is authored.

- `style.css`: `Version` `1.22` → `1.23`.

---

## [1.22] - 2026-05-11

### Added

- This `CHANGELOG.md` and the versioning workflow above.

### Changed

- `style.css`: `Version` `1.21` → `1.22`.
