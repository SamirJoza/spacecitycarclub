# Space City Car Club — WordPress Theme

Custom WordPress theme for **[spacecitycarclub.com](https://spacecitycarclub.com)**, the website of Space City Car Club, a Houston-area open-class car club (all makes, models and years welcome).

The theme powers the public site and much more: memberships, the member dashboard and garage, the member and business directories, sponsors, events, the merch shop and the club's internal admin tools.

| | |
|---|---|
| **Theme version** | `1.35` (source of truth: `style.css` → `Version:`) |
| **Built on** | [Sage 11.0.1](https://roots.io/sage/) by Roots |
| **Author** | Samir Joza — [samirjoza.dev](https://samirjoza.dev) |
| **Repository** | [github.com/SamirJoza/spacecitycarclub](https://github.com/SamirJoza/spacecitycarclub) |
| **Hosting** | SiteGround (deployed via GitHub Actions) |
| **License** | MIT (see `LICENSE.md`) |

---

## Table of contents

1. [Tech stack](#tech-stack)
2. [Requirements](#requirements)
3. [Required and supported plugins](#required-and-supported-plugins)
4. [Local development](#local-development)
5. [Build commands](#build-commands)
6. [Git workflow and deployment](#git-workflow-and-deployment)
7. [Project structure](#project-structure)
8. [Architecture](#architecture)
9. [Design system](#design-system)
10. [Gutenberg blocks](#gutenberg-blocks)
11. [Page templates and layouts](#page-templates-and-layouts)
12. [Content model: post types and taxonomies](#content-model-post-types-and-taxonomies)
13. [Memberships](#memberships)
14. [Member data model](#member-data-model)
15. [Member-facing features](#member-facing-features)
16. [Events](#events)
17. [Shop (WooCommerce + Printify)](#shop-woocommerce--printify)
18. [Sponsors](#sponsors)
19. [Marketing attribution](#marketing-attribution)
20. [Operational roles and admin access](#operational-roles-and-admin-access)
21. [Admin dashboard ("Cockpit") and reports](#admin-dashboard-cockpit-and-reports)
22. [Theme Settings (ACF options pages)](#theme-settings-acf-options-pages)
23. [Security](#security)
24. [Hooks and filters reference](#hooks-and-filters-reference)
25. [Custom URLs and endpoints](#custom-urls-and-endpoints)
26. [Conventions](#conventions)
27. [Troubleshooting](#troubleshooting)
28. [Roadmap](#roadmap)
29. [Credits](#credits)

---

## Tech stack

The theme is based on **Sage 11.0.1**, the Roots starter theme. It uses Laravel Blade templating, a Vite build pipeline and Tailwind CSS, with Laravel services provided by Acorn.

### PHP (Composer)

| Package | Version | Purpose |
|---|---|---|
| `roots/acorn` | 5.0.5 | Laravel container, Blade, view composers (Illuminate 12) |
| `log1x/acf-composer` | 3.4.4 | ACF blocks, field groups and options pages as PHP classes |
| `log1x/navi` | 3.1.1 | Menu rendering for Blade |
| `log1x/sage-svg` | 2.0.2 | `@svg()` directive for inline SVGs |
| `generoi/sage-woocommerce` | 1.1.3 | Blade template overrides for WooCommerce |
| `johnbillion/extended-cpts` | 5.1.0 | Extended post type and taxonomy registration |
| `blade-ui-kit/blade-icons` | 1.8.0 | Blade icon components |
| `owenvoke/blade-fontawesome` | 2.9.1 | Font Awesome icons (`<x-fab-… />`, `<x-fas-… />`) |
| `andreiio/blade-remix-icon` | 3.6.0 | Remix icons |
| `laravel/pint` *(dev)* | 1.24.0 | PHP code style |

### Front end (npm)

| Package | Version | Purpose |
|---|---|---|
| `vite` | 6.3.5 | Dev server and bundler |
| `tailwindcss` / `@tailwindcss/vite` | 4.1.12 | Utility CSS (Tailwind v4, CSS-first config) |
| `laravel-vite-plugin` | 1.3.0 | Entry points, manifest, `public/hot` |
| `@roots/vite-plugin` | 1.1.0 | WordPress externals and `theme.json` generation |
| `gsap` | 3.13.0 | Hero block scroll animation |
| `material-symbols` | 0.40.2 | Material Symbols icon font |
| `fast-glob` | 3.3.3 | Auto-discovers per-block JS/CSS entries |

**Fonts:** Space Grotesk (display) and Inter (body), loaded from Google Fonts.

---

## Requirements

- **PHP** 8.2 or newer
- **WordPress** 6.6 or newer
- **Node.js** 20 or newer (only needed for building assets)
- **Composer** 2 (only needed when changing PHP dependencies)

`vendor/` and `public/build/` are **committed to the repository**, so the theme runs as soon as it's deployed. No build step is required on the server.

---

## Required and supported plugins

The theme expects these plugins. Versions are the ones currently used in local development.

| Plugin | Version | Used for |
|---|---|---|
| Advanced Custom Fields PRO | 6.8.5 | **Required.** Blocks, field groups, options pages |
| Paid Memberships Pro | 3.8.1 | **Required.** Membership levels, checkout, renewals |
| WooCommerce | 10.9.3 | Shop, My Account shell, event tickets checkout |
| Event Booking Manager for WooCommerce (MagePeople, `mage-eventpress`) | 5.3.4 | Events (`mep_events`) |
| Gravity Forms | 2.10.5 | Sponsor sign-up and other forms |
| Gravity Forms Advanced Post Creation | 1.6.1 | Creates Sponsor posts from form entries |
| Printify for WooCommerce | 3.2 | Pushes merch products and prices into WooCommerce |
| SiteGround Speed Optimizer | — | Cache purged after each deploy (`wp sg purge`) |

Every integration checks that its plugin is present (`function_exists` / `class_exists`), so the site degrades gracefully if one is disabled.

The theme also filters titles for Yoast SEO and Rank Math on Thank You pages, if either is installed.

---

## Local development

The local site runs in **[Local](https://localwp.com/)** as `local.spacecitycarclub.com`. The theme lives at:

```
app/public/wp-content/themes/spacecitycarclub
```

### Two command contexts

| Where | Use it for |
|---|---|
| **Mac terminal**, in the theme folder | `npm` commands: installing packages, dev server, builds |
| **Local → "Site shell"** (site must be running) | `composer`, `php` and `wp` (WP-CLI) commands |

Composer, PHP and WP-CLI are not assumed to be installed globally on the Mac. Run them from Local's Site shell.

### First-time setup

```bash
# In the theme folder (Mac terminal)
npm install
npm run build
```

`composer install` is only needed after changing `composer.json`, because `vendor/` is already committed. Run it from Local's Site shell.

### Dev server (hot reload)

```bash
npm run dev
```

Vite writes a `public/hot` file while the dev server runs, and WordPress then loads assets from Vite instead of `public/build`.

`public/hot` is git-ignored and excluded from deploys. **Always stop the dev server and run `npm run build` before committing.**

### Test data

`seed-members.php` is a WP-CLI script that creates and rolls back dummy members with PMPro memberships, vehicles and profile data. It is local-only and excluded from deploys.

```bash
wp eval-file seed-members.php -- seed 150       # create 150 test members
wp eval-file seed-members.php -- rollback       # remove the latest seed run
wp eval-file seed-members.php -- rollback <id>  # remove a specific run
```

---

## Build commands

| Command | What it does |
|---|---|
| `npm run dev` | Starts the Vite dev server with hot reload |
| `npm run build` | Production build into `public/build/`. Vite empties the folder first, so no stale hashed files remain |
| `postbuild` *(automatic)* | `scripts/prune-theme-json.mjs` locks down editor color controls in the generated `theme.json` |
| `npm run translate` | Generates the `.pot` file and updates `.po` files (needs WP-CLI) |
| `npm run translate:compile` | Compiles `.mo` and JSON translation files |

### Build output

- Global entries: `resources/css/app.css`, `resources/js/app.js`, `resources/css/editor.css`, `resources/js/editor.js`.
- Per-block entries are auto-discovered from `resources/js/blocks/*.js` and `resources/css/blocks/*.css`, and written to `public/build/blocks/<name>/`.
- `public/build/manifest.json` maps source files to hashed build files.
- `public/build/assets/theme.json` is generated from the Tailwind tokens. `app/setup.php` points WordPress at it instead of the root `theme.json`.

---

## Git workflow and deployment

### Branches

| Branch | Purpose |
|---|---|
| `main` | Day-to-day development |
| `production` | What is live. **Every push deploys to SiteGround.** |

### Everyday flow

```bash
# 1. Work on main, then build
npm run build

# 2. Commit everything, including deletions of old build files
git add -A
git commit -m "Describe the change"
git push origin main

# 3. Release
git checkout production
git merge main
git push origin production
git checkout main
```

Always stage with `git add -A` after a build. That records the removal of old hashed files in `public/build/assets`.

### What's in git and what isn't

**Committed:** all theme code, `vendor/` (in full) and `public/build/`.

**Ignored** (`.gitignore`): `node_modules/`, `public/hot`, macOS/Windows system files, IDE folders, `.env*`, `*.log`, `*.sql`.

The repository contains **only the theme**. WordPress core, plugins, uploads, `wp-config.php` and database dumps live outside the theme folder and are never tracked.

### GitHub Actions deploy (`.github/workflows/deploy-production.yml`)

On each push to `production`, or when run manually, the workflow:

1. Checks that the required secrets exist. If any are missing, it skips with a warning instead of failing.
2. Loads the SiteGround SSH key. SiteGround keys are passphrase-protected, so the passphrase is removed on the throwaway runner copy only.
3. **Safety check:** confirms `wp-config.php` and the `wp-content/themes/spacecitycarclub` folder exist on the server. If either is missing, it stops.
4. **rsyncs** the theme into `wp-content/themes/spacecitycarclub` on the server:
   - Only new and changed files are transferred, and only the changed parts of each.
   - `--delay-updates` makes all changed files, including the Vite manifest and its hashed assets, go live together at the end.
   - `--delete-after` then removes files that no longer exist in git, such as old hashed build assets. This happens **only inside the theme folder**.
5. Purges the SiteGround cache (`wp sg purge` and `wp cache flush`).

**Never deployed:** `.git/`, `.github/`, `.cursor/`, `.cursorignore`, `.editorconfig`, `.gitignore`, `node_modules/`, `public/hot`, `.DS_Store`, `seed-members.php`, `TODO.md`, `TAILWIND_*.md`.

**Manual runs:** Actions → *Deploy to production (SiteGround)* → *Run workflow*. Manual runs default to a **dry run**, which lists every change without copying anything. Lines starting with `*deleting` are files that would be removed from the server.

#### Required repository secrets

Set these under **Settings → Secrets and variables → Actions**:

| Secret | Value |
|---|---|
| `SG_SSH_HOST` | SiteGround SSH hostname (Site Tools → Devs → SSH Keys Manager → key ⋮ → SSH Credentials) |
| `SG_SSH_USER` | SiteGround SSH username (e.g. `u123-abcdefgh1234`) |
| `SG_SSH_PORT` | SSH port, usually `18765` |
| `SG_SSH_PRIVATE_KEY` | Full private key text (SSH Keys Manager → key ⋮ → Private Key) |
| `SG_SSH_PASSPHRASE` | Passphrase chosen when the key was generated |
| `SG_WP_PATH` | WordPress root on the server, e.g. `/home/customer/www/spacecitycarclub.com/public_html` |

Take a SiteGround backup (Site Tools → Security → Backups) before the first deploy and before large releases.

---

## Project structure

```
spacecitycarclub/
├── .github/workflows/     GitHub Actions (production deploy)
├── .cursor/               Cursor rules, project settings and AI skills
├── app/
│   ├── Blocks/            ACF Composer Gutenberg blocks (PHP)
│   │   └── Memberships/   Membership-specific blocks
│   ├── Fields/            ACF field groups (CPTs, users, templates, settings)
│   ├── Options/           ACF options pages under "Theme Settings"
│   ├── Providers/         ThemeServiceProvider (Acorn)
│   ├── Support/           Feature modules, loaded by setup.php (see Architecture)
│   ├── View/Composers/    Blade view composers
│   ├── woocommerce/       Woo checkout glue
│   ├── setup.php          Theme boot file: supports, menus, assets, module loader
│   └── filters.php        Small global filters
├── mage-event/themes/     Template overrides for the MagePeople event plugin
├── public/build/          Compiled assets + manifest (committed)
├── resources/
│   ├── css/               Tailwind entry, tokens, components, layouts, utilities
│   ├── js/                app.js, editor.js, components, block scripts
│   ├── images/            Logo, admin dashboard background
│   ├── fonts/
│   └── views/             Blade templates (layouts, sections, partials, blocks,
│                          components, woocommerce, page templates)
├── scripts/               Build helpers (prune-theme-json.mjs)
├── vendor/                Composer dependencies (committed)
├── functions.php          Composer autoload + Acorn boot + setup/filters loader
├── header.php / footer.php  Shims for plugin templates that call get_header()
├── style.css              Theme header (version source of truth)
├── theme.json             Base theme.json (the generated build copy is used)
├── vite.config.js
├── CHANGELOG.md           Release notes
└── TODO.md                Roadmap and backlog
```

---

## Architecture

### Boot sequence

1. `functions.php` loads Composer's autoloader, boots Acorn with `App\Providers\ThemeServiceProvider`, then includes `app/setup.php` and `app/filters.php`.
2. `app/setup.php` does two jobs:
   - Registers core theme features: menus, theme supports, sidebars, editor assets, fonts and the generated `theme.json`.
   - Acts as the **module loader**. It `require_once`s every feature file in `app/Support/`, grouped by concern.
3. ACF Composer autoloads everything in `app/Blocks`, `app/Fields` and `app/Options`. These don't need to be required.

### Support modules

Each file in `app/Support/` is **self-booting**: it registers its own hooks at the bottom (e.g. `ClassName::register();`). Adding a feature means creating the file and adding one `require_once` line to `setup.php`.

| Folder | Responsibility |
|---|---|
| `Access/` | Protected frontend paths |
| `Admin/Dashboard/` | Dashboard rename, widgets, reports |
| `Admin/UserRoles/` | Operational roles, capabilities, admin access, admin bar |
| `Admin/Users/` | Users list columns, bulk password reset email, referral source field |
| `Admin/` | WordPress branding (login/admin) |
| `Auth/` | `/logout/` route |
| `Blog/` | Content Type taxonomy (required, single term) |
| `CPT/` | Custom post types and their admin UX |
| `Dev/` | Local-only SSL/notice workarounds |
| `FeaturedVehicles/` | Monthly featured vehicle resolver, archive CPT, repository |
| `GravityForms/` | KLogger fix, Sponsor post sync |
| `Integrations/` | PMPro ↔ WooCommerce glue, member lifecycle status |
| `Marketing/` | Referral tracking, Gravity Forms cookie population |
| `Media/` | Force HTTPS media URLs |
| `Members/` | Member context, directory, garage, bio, avatar, veteran toggle, role sync |
| `Memberships/` | Founding member cap, abandoned signup cleanup |
| `Navigation/` | Per-menu-item role visibility |
| `PMPro/` | Event (kiosk) signup flow |
| `Security/` | Strong password policy |
| `Taxonomies/` | Business directory vocabularies (categories/services) |
| `Woo/` | Product access, markup, shop nav and filters, event access, account extras |

Several files are present but intentionally **not loaded**: `Members/MemberRole.php`, `Admin/Users/ManualRoleAssignment.php`, `Security/PmproCheckoutNoAutoLogin.php` and `CPT/LeadershipUserPicker.php`. The `setup.php` comments explain which ones were superseded.

### View composers

| Composer | Feeds |
|---|---|
| `App` | Site name (all views) |
| `Post`, `ContentSingle`, `Comments` | Blog singles and comments |
| `ArchiveMinutes`, `SingleMinutes` | Meeting minutes archive and single |
| `BusinessDirectory` | Business Directory template (search, filters, paging) |
| `Faq` | FAQ Layout template |
| `WooMyAccountComposer` | WooCommerce My Account shell (avatar, member data) |

---

## Design system

### Tokens (`resources/css/tokens/`)

Colors are defined as Tailwind v4 `@theme` variables in `colors.css`, with dark-mode overrides under `.dark`.

| Token | Light | Dark |
|---|---|---|
| `--color-canvas` | `#f6f6f8` | `#101622` |
| `--color-surface` | `#ffffff` | `#1a2336` |
| `--color-surface-2` | `#ffffff` | `#1c2433` |
| `--color-text` | `#0f172a` | `#ffffff` |
| `--color-muted` | `#64748b` | `#9da6b9` |
| `--color-line` | `rgba(15,23,42,.12)` | `#232a3c` |
| `--color-primary-500` (Space City Blue) | `#135bec` | `#135bec` |

Semantic aliases (`--color-card-bg`, `--color-input-*`, `--color-chip-*`, `--color-control-*` …) sit on top of the core tokens. **Components should use tokens, not hard-coded hex values.**

`typography.css` defines a fluid Major Third type scale (`--text-h1` … `--text-h6`, `--text-body*`). It sets Space Grotesk for headings and Inter for body text.

`utilities/gradients.css` is the single source for the hero and section gradients, in light and dark variants.

### Dark mode

- Driven by the `.dark` class and a `data-theme` attribute on `<html>`, stored in `localStorage` under `scc-theme`.
- An inline no-flash script in `layouts/app.blade.php` (mirrored in `header.php`) applies the theme before first paint.
- `resources/js/components/theme.js` handles the toggle.

### CSS organization (`resources/css/`)

- `tokens/`: colors and typography.
- `utilities/`: gradients and text gradients.
- `components/`: buttons, cards, header nav, footer nav, comments, WooCommerce account and shop.
- `layouts/`: page-level styles (blog, single, minutes, events, checkout, order received, membership confirmation, thank-you, PMPro login).
- `tailwind-safelist.css`: classes generated dynamically in PHP.

Block-specific and template-specific CSS is scoped to a wrapper class. Some larger features keep their CSS inside the Blade partial (e.g. `partials/directory/styles.blade.php`).

### Editor

- Core block patterns and block templates (FSE) are disabled.
- Custom colors and gradients are locked to the theme palette.
- Custom blocks appear under the **Theme Blocks** category (and a few under Design, Layout and Widgets).

---

## Gutenberg blocks

All blocks are ACF Composer classes in `app/Blocks/`, with Blade views in `resources/views/blocks/`.

| Block | Purpose |
|---|---|
| **Hero** | Hero with optional GSAP 6-slice scroll wall, dual CTAs, configurable height, highlighted headline words |
| **Container** | Section wrapper with presets, content width, backgrounds, overlay, parallax image, InnerBlocks |
| **Section Header** | Eyebrow + headline + optional highlight + description |
| **Card Grid** | Manual cards with chip badges, meta line, Material Symbols icons |
| **CTA Block** | Callout with layout, spacing and theme options and one or two buttons |
| **Image / Text Split** | Image on one side, nested blocks on the other |
| **Benefits + Proof** | Benefits list alongside an image/proof collage |
| **Core Values** | Heading plus repeatable value cards |
| **Journey Timeline** | Timeline with a curated Material Symbols icon picker |
| **Action Steps** | Intro copy plus numbered next-step rows |
| **Event Impact Flow** | How events and programs create community support |
| **SCCC Accordion** | Theme-styled accordion |
| **Testimonial** | Pick from the Testimonial CPT or enter manually; contained or full width |
| **Trust Block** | Proof-point cards with optional auto counts |
| **Leadership Band** | Leadership by category, with profile cards and bio modals |
| **Masthead** | Editorial masthead for the directory and internal landing pages |
| **Feature** | This month's featured member vehicle, with owner modal |
| **Garage Showcase** | Random mosaic of member vehicles from different members |
| **Latest Posts Grid** | Latest three posts as cards |
| **Next Upcoming Event** | Next MagePeople event the visitor is allowed to see |
| **Membership Cta** | Sign-up prompt, hidden automatically for members |
| **Memberships: Founding Hero** | Founding membership hero with live "Only XX spots available" badge |
| **Memberships: Tiers Grid** | Pricing grid driven by selected PMPro levels |
| **Sponsor Showcase** | Active sponsors as a static rail or weighted scroller |
| **Sponsor Directory** | Active sponsors grouped by tier |
| **Sponsorship Signup** | Sponsor tier cards that hand off to a Gravity Form |
| **Merch Product Promo** | Sidebar promo card for a selected WooCommerce product |
| **Discord Invite** | Compact community invite card for sidebars |
| **Browse Tags Widget** | Sidebar tag browser, most popular first, expandable |

---

## Page templates and layouts

### Page templates (selectable in the editor)

| Template | File | Notes |
|---|---|---|
| Business Directory | `template-business-directory.blade.php` | Member-owned businesses with search, category/service filters, pagination and detail modal |
| Member Directory | `template-member-directory.blade.php` | Members-only directory with search, make filter, sort and garage mosaic |
| FAQ Layout | `template-faq.blade.php` | FAQ CPT grouped by category, with search and a help CTA |
| Legal Document | `template-legal.blade.php` | Legal pages; hero, meta and closing note come from ACF |
| Event Signup | `template-event-signup.blade.php` | Onsite tablet signup (kiosk layout) |
| Event Signup Complete | `template-event-signup-complete.blade.php` | Kiosk completion screen, auto-returns |
| Event Membership Checkout | `page-event-membership-checkout.blade.php` | PMPro checkout in kiosk or normal layout |
| Members Page | `page-members.blade.php` | Members layout |
| Video Background Page | `template-video-background.blade.php` | Page with an ACF-selected background video |
| Typography Test | `template-typography.blade.php` | Type scale preview |
| Custom Template | `template-custom.blade.php` | Basic Sage template |

### Other key templates

- **Blog:** `home`, `category`, `tag`, `taxonomy-content_type` (shared `partials/blog-archive-feed`), `single`, `author`, `search`, `404`.
- **Custom post types:** `archive-minutes`, `single-sponsor`, `single-thank_you`.
- **WooCommerce:** archive, product card, single product, checkout, and My Account (dashboard, membership, event bookings, addresses).

### Layouts (`resources/views/layouts/`)

| Layout | Used by |
|---|---|
| `app` | Default: eyebrow bar, sticky header, main, newsletter, footer |
| `blog` | Blog archives with sidebar |
| `single` | Blog single, with optional sidebar |
| `members` | Members page (modal-safe stacking) |
| `kiosk` | Minimal full-screen layout for event tablets |
| `video-background` | Pages with a background video |

### Menus and sidebars

- **Menus:** `primary_navigation` and `legal_navigation`. Menu items support per-role visibility.
- **Sidebars:** `sidebar-primary`, `blog-sidebar`, `sidebar-footer`, `author-archive-sidebar`, `faq_sidebar_below_categories`.

---

## Content model: post types and taxonomies

| Post type | Slug | Notes |
|---|---|---|
| Sponsors | `sponsor` | Tiers (`sponsor_tier`); start/end dates with a daily expiration cron; read-only **Source** attribution field; admin columns, filter and sort |
| Minutes | `minutes` | Meeting minutes; `meeting_type` (Board / Committee / Membership) chosen by an ACF radio synced to the taxonomy; members-only single view |
| Leadership | `leadership` | Linked user, category (`leadership_group`: Leadership Team, Officer, Board Member, Committee), position titles, display order, bio override |
| FAQ | `faq` | Question = title, answer = ACF; grouped by `faq_category` |
| Testimonials | `testimonial` | Name = title, photo = featured image, quote + meta line in ACF |
| Thank You | `thank_you` | Hierarchical landing pages at `/thank-you/…`; three visual styles (Nebula, Aurora, Signal) |
| Featured Vehicle archive | `sccc_feature_vehicle` | Admin-only. One record per month, created automatically by the resolver; supports force reset |

**Additional taxonomies:**

- `content_type` on posts: required, exactly one term, and terms are managed by admins only.
- `sccc_event_flag` on events: see [Events](#events).

---

## Memberships

Memberships run on **Paid Memberships Pro**.

| Level ID | Level | Notes |
|---|---|---|
| 2 | Founding | Capped at **25 active seats** (`FoundingCap`); checkout blocked and level hidden when full |
| 3 | Member | |
| 4 | Veteran / First Responder | |
| 5 | Junior | |

### Who counts as a member

- **True member:** has the `sccc_member` role **and** an active PMPro membership. `App\Support\Members\MemberContext::isTrueMember()` is the single source of truth.
- `MemberRoleSync` adds or removes `sccc_member` automatically when PMPro membership changes.
- WooCommerce shoppers are `customer` users and are **not** members.
- **Abandoned signups:** users whose only role is `subscriber` are failed PMPro checkouts. `AbandonedSubscriberCleanup` deletes them on a 15-minute cron once they are at least 15 minutes old.

### Lifecycle status

`ScccMemberLifecycleAdmin` adds a Membership Status column to the Users list, based on the PMPro end date:

| Status | Rule |
|---|---|
| Active | Active, with the end date in the future or no end date |
| Grace | 0–29 days past the end date |
| Past Due | 30–59 days past |
| Abandoned | 60+ days past; excluded from the PMPro members list |
| Paused | Manual override (`sccc_membership_paused`) |

Community reports count **Active + Grace** members only.

### Other membership behavior

- The Renew link appears from **90 days** before expiration.
- PMPro billing addresses are copied into WooCommerce billing/shipping user meta at checkout.
- PMPro profile links are redirected to WooCommerce My Account.

---

## Member data model

Member data is stored as user meta, mostly through ACF user fields (`app/Fields/MemberProfile.php`).

| Key | Contents |
|---|---|
| `membership_number`, `membership_issued_at` | Generated on join; immutable unless reset |
| `birth_date` | `Ymd` |
| `is_veteran_first_responder`, `veteran_type`, `agency_branch` | Service status |
| `sccc_member_phone`, `sccc_member_website`, `sccc_member_social_links` | Contact and social details (phone is never shown publicly) |
| `sccc_hide_in_member_directory` | Directory opt-out |
| `sccc_profile_image_id` | Cropped profile photo; also replaces Gravatar across the site and admin |
| `description` | Bio (plain text, max 1,500 characters) |
| `vehicles` (repeater) | `vehicle_id`, `year`, `make_select` / `make_other` / `make_raw`, `model`, `nickname`, `notes` (≤1,000 characters), `image` |
| `vehicle_index` | Computed search index (`make model` strings) |
| `sccc_business_*` | Business listing: opt-in, name, description, categories, services, city, phone, email, website, logo |
| `sccc_business_index` | Computed business search index |
| `sccc_referral_source` | How the member found the club (read-only in admin) |
| `sccc_membership_paused` | Manual pause flag |

Business categories and services are controlled vocabularies in `app/Support/Taxonomies/BusinessDirectoryTaxonomies.php`. They can be extended with filters.

---

## Member-facing features

WooCommerce **My Account** is the member dashboard:

- **Dashboard:** profile summary, membership info, veteran toggle, bio editor (saved over AJAX), and the **Garage**. Members can add, edit and delete vehicles with photos (`Members/Garage.php`).
- **Account details:** phone, website, social links, birthdate, service status, directory opt-out and business listing, plus a profile photo and logo cropper (CropperJS).
- **Membership tab** (`/my-account/membership/`): PMPro status and invoices.
- **Event bookings:** the event plugin's bookings, wrapped in the theme's styling.
- The Downloads tab is removed, and logout skips the WordPress confirmation screen.

Other member features:

- **Member Directory:** members only; respects the opt-out; searchable by name and vehicle, with a make filter.
- **Business Directory:** listings for member-owned businesses.
- **Featured vehicle of the month:** a fair rotation that avoids repeats within a cycle and back-to-back months. It prefers vehicles not featured before and is saved once per month.

---

## Events

Events come from **MagePeople Event Booking Manager** (`mep_events`), with tickets through WooCommerce.

The **Event Flags** taxonomy (`sccc_event_flag`) controls behavior:

| Flag | Effect |
|---|---|
| `sccc-event-info` | Info only; no registration or checkout |
| `sccc-event-rsvp` | Free registration through checkout ($0 ticket) |
| `sccc-event-ticketed` | Paid registration |
| `sccc-event-members-only` | Visible only to `sccc_member` users; hidden from queries, REST and single pages for everyone else |

Other event pieces:

- Front-end cleanup: empty registration notices are hidden, and seat counts show only when enabled per event.
- Event grid cleanup JS hides "Price: Free" labels.
- Template overrides live in `mage-event/themes/`.

### Onsite (kiosk) event signup

The kiosk flow lets visitors join on shared tablets at shows:

1. Enable it in **Settings → Event Signup** and set the event name, label, logo and access key.
2. Visitors start at `/event-signup/` and choose a membership level (levels 2, 3 and 4 by default).
3. PMPro handles checkout at `/event-membership-checkout/`.
4. The new member is **not** left logged in. The tablet goes to `/event-signup-complete/`, which returns to a fresh signup screen.
5. The event is recorded as the member's signup source (`sccc_signup_source`).

---

## Shop (WooCommerce + Printify)

- **Product access by tag:**
  - `sccc-member` / `member`: visible and purchasable only by `sccc_member` users.
  - `sccc-supporter` / `supporter`: only for guests and non-member shoppers.
  - Enforced across queries, REST/Store API, related products, cart and single product pages.
- **Markup:** Theme Settings → Shop Settings (`sccc_shop_markup_percent`) marks up Printify prices on simple products and variations.
  - Prices are rounded **up** to the next $0.50.
  - The original base price is stored, so republishing from Printify doesn't compound the markup.
  - Opt out per product with `_sccc_disable_shop_markup`.
- **Navigation:** a category nav row inside the sticky header on shop pages, plus styled breadcrumbs.
- **Filters:** search, department, price, and color/size filters that work with Printify variation meta (`sccc_filter_colors`, `sccc_filter_sizes`).
- **Templates:** custom archive, product card, single product (with a gallery fix for Printify), checkout, order received, and membership checkout fields.

---

## Sponsors

- **Sponsor CPT** with tiers, contact details, company info, assets, internal notes and date-based status. A daily cron expires sponsors past their end date.
- **Gravity Forms → Sponsor:** the sponsor application form (**form ID 2**) creates a Sponsor post through Advanced Post Creation. `SponsorApcSync` then:
  - sets the title and content;
  - maps the form fields into the ACF sponsor fields;
  - records the marketing **Source**. The referral cookie wins over the form's hidden field by default.
- **Front end:**
  - Sponsor Showcase, Sponsor Directory, Sponsorship Signup and Trust blocks.
  - A styled sponsor single page with a global CTA (Theme Settings → Sponsor Single CTA).
- An **Active Sponsors** dashboard widget shows counts per tier.

---

## Marketing attribution

1. Any URL with `?referral_source=VALUE` sets the `sccc_referral_source` cookie, which lasts 10 years. Without it, the source is inferred from the HTTP referrer (Google, Facebook, …) or recorded as `Direct`.
2. On PMPro checkout, the source is saved to the member's `sccc_referral_source` user meta. The Users list shows it as a filterable, sortable column.
3. Gravity Forms fields with dynamic parameter `referral_source` are filled from the URL first, then the cookie. More keys can be added with the `sccc_gf_cookie_population_map` filter.

---

## Operational roles and admin access

Club officers get focused staff roles instead of full admin. These are separate from `sccc_member`.

| Role | Slug |
|---|---|
| Club Site Manager | `sccc_site_manager` |
| Secretary | `sccc_secretary` |
| Membership Manager | `sccc_membership_manager` |
| Treasurer | `sccc_treasurer` |
| Event Manager | `sccc_event_manager` |
| Content Manager | `sccc_content_manager` |
| Report Viewer | `sccc_report_viewer` |

**Custom capabilities:**

- Dashboard and reports: `sccc_view_dashboard`, `sccc_use_dashboard_widgets`, `sccc_view_reports`.
- Content: `sccc_manage_content`, `sccc_manage_minutes`, `sccc_edit_shared_club_content` (Minutes, Sponsors, Testimonials, Leadership, Thank You), `sccc_manage_featured_vehicles`, `sccc_manage_faq`.
- Members, finance and shop: `sccc_manage_members`, `sccc_view_finance`, `sccc_manage_shop`.
- Events: `sccc_manage_events`, `sccc_view_events`, `sccc_edit_events`, `sccc_create_events`.

**How access is enforced:**

- **Secondary roles:** Users → Edit User has a "Secondary operational roles" checklist. Member and customer roles are protected from being dropped.
- **Menus and URLs:** restricted staff see a trimmed admin menu (`AdminMenus`). Direct URL access to hidden screens is blocked (`AdminAccess`).
- **Members stay out of wp-admin:** pure members can't reach wp-admin and don't see the admin bar. Staff land on My Account after login unless `redirect_to` says otherwise.
- **Admin bar:** colored by the user's highest operational role, with a mode label.
- **Default dashboard layout:** a new staff member's Dashboard layout is copied once from a template user (option `sccc_dashboard_layout_template_user_id`, default user 1).
- **Menu visibility:** each menu item can be limited to one role (Appearance → Menus).

---

## Admin dashboard ("Cockpit") and reports

The wp-admin Dashboard is renamed **Cockpit**. Default WordPress widgets are removed and a branded background is added.

Widgets require `sccc_use_dashboard_widgets`:

| Widget | Shows |
|---|---|
| Members Overview | Active members, renewals needed, breakdown by level |
| Active Sponsors | Active sponsors per tier |
| Birthdays | Members with birthdays this month (age included) |
| Anniversaries | Membership anniversaries this month (years included) |
| Car Search | Find members by make / model / year |
| Veterans / First Responders | Military first, then first responders |
| SCCC Car Park | Top 10 makes, with drill-down into models; cached 6 hours |
| Membership Trends | Active vs inactive, signups and expirations over time (Chart.js) |

Report widgets have **Print list** views, clean HTML you can save as PDF from the browser.

**Users-screen tools:**

- Phone, Membership Status and Referral Source columns.
- A bulk **Send password reset** action that uses a branded email template (Settings → Bulk password reset).
- Lifecycle bulk actions.

---

## Theme Settings (ACF options pages)

The **Theme Settings** menu in wp-admin has these child pages:

| Page | Controls |
|---|---|
| Social Connections | Social profile links used in the eyebrow bar and footer |
| Newsletter Section | On/off, where it shows, copy, form action, disclaimer |
| Protected Frontend Pages | Path rules, allowed roles and redirects (prefix match; logged-out visitors are always blocked) |
| Business Directory CTA | "Own a business? Get listed" section |
| FAQ Help CTA | "Still have questions?" section |
| Sponsor Single CTA | CTA under sponsor single pages |
| Shop Settings | Merch markup percentage |

**Also in wp-admin:**

- **Settings → Event Signup:** kiosk signup flow settings.
- **Settings → Bulk password reset:** email template for the bulk reset action.

The ACF field-group editor UI is hidden (`acf/settings/show_admin`). Field groups live in code.

---

## Security

- **Password policy** (`StrongPasswords`):
  - At least 8 characters (12+ recommended), with upper, lower, number and special characters, and no spaces.
  - Enforced server-side for WooCommerce, PMPro, WordPress registration, password reset and profile updates, with a live strength helper.
- **Access control:** protected frontend paths, members-only events and products, members-only minutes and directory, and role-based menu items.
- **Coding standards:**
  - All output is escaped and all input sanitized.
  - Nonces and capability checks guard every admin action, AJAX call and form post.
  - WordPress APIs are used instead of raw SQL, except for read-only report queries against PMPro tables.
- **HTTPS:** media, attachment, srcset and avatar URLs are forced to HTTPS (`ForceHttpsMediaUrls`).
- **Local-only workarounds:** `Dev/LocalSslStreamBypass.php` runs only when the environment is `local` or `development` **and** the host looks local.

---

## Hooks and filters reference

All custom hooks use the `sccc_` prefix.

| Filter | Purpose |
|---|---|
| `sccc_is_true_member` | Override the true-member check |
| `sccc_pmpro_club_level_ids` | Club PMPro level IDs (`app/filters.php`) |
| `sccc_event_signup_level_ids` | Levels offered in the kiosk flow (default `[2, 3, 4]`) |
| `sccc_event_signup_entry_path` / `_complete_path` / `_checkout_path` | Kiosk URLs |
| `sccc_pmpro_checkout_confirmation_url` / `sccc_pmpro_checkout_restart_url` | PMPro checkout redirects |
| `sccc_pmpro_to_woo_overwrite` / `sccc_pmpro_to_woo_debug` | PMPro → Woo address sync |
| `sccc_pure_member_admin_redirect` | Where pure members are sent when they hit wp-admin |
| `sccc_dashboard_layout_template_user_id` | Template user for the default Dashboard layout |
| `sccc_admin_dashboard_background_blur` | Dashboard background blur |
| `sccc_gf_cookie_population_map` | Gravity Forms URL/cookie population keys |
| `sccc_sponsor_gf_source_field_id` / `sccc_sponsor_gf_source_input_names` | Sponsor form source field detection |
| `sccc_sponsor_source_prefer_marketing_cookie` | Cookie vs form field priority (default: cookie) |
| `sccc_business_directory_categories` / `sccc_business_directory_services` | Extend business vocabularies |
| `sccc_garage_showcase_member_role` | Role used by Garage Showcase |
| `sccc/featured_vehicles/eligible_user_query_args` / `sccc/featured_vehicles/user_is_active` | Featured vehicle eligibility |
| `sccc_thank_you_document_title`, `_display_headline`, `_visual_variation`, `_badge_text`, `_callout_title`, `_callout_text`, `_secondary_cta_label`, `_secondary_cta_url` | Thank You page output |

**AJAX actions:**

- `sccc_member_bio_save`
- `sccc_check_password_strength`
- `sccc_membership_trends_data`
- `sccc_user_vehicles`

**Admin-post actions:**

- `sccc_print_birthdays`, `sccc_print_anniversaries`, `sccc_print_cars`, `sccc_print_veterans`
- `sccc_car_park_refresh` (plus the Car Park print action)
- `sccc_force_reset_featured_vehicle`

**Cron events:**

- `sccc_sponsor_expiration_daily`
- `sccc_cleanup_abandoned_subscriber_users` (every 15 minutes)

---

## Custom URLs and endpoints

| URL | Source |
|---|---|
| `/logout/` | Nonce-safe logout that redirects home (`Auth/PrettyLogout.php`) |
| `/my-account/membership/` | PMPro membership tab in My Account |
| `/event-signup/`, `/event-signup-complete/`, `/event-membership-checkout/` | Kiosk signup flow |
| `/thank-you/…` | Thank You CPT |
| `?referral_source=…` | Marketing attribution |

After adding or changing rewrite rules, **flush permalinks**: Settings → Permalinks → Save.

---

## Conventions

### Versioning and changelog

- The version lives in `style.css` → `Version:`. `CHANGELOG.md` must always match it.
- **One version bump per calendar day** of theme work. The minor number goes up by `0.01` (e.g. `1.35` → `1.36`).
  - Later changes on the same day are added to that day's changelog section without bumping again.
  - Fundamental or breaking changes get a major bump.
- Changelog entries use Keep a Changelog sections: Added, Changed, Fixed, Removed, Security.

### Code style

- **PHP:** PSR-4 under `App\`, 4-space indentation.
- **Blade, CSS and JS:** 2-space indentation (see `.editorconfig`).
- Every file starts with a header comment explaining **what** it does and **why**. Keep these up to date.
- Keep business logic in `app/` (Support classes, composers, block classes) and markup in Blade.
- Use design tokens instead of hard-coded colors, and scope component CSS to a wrapper class.
- Use `sccc_` / `SCCC` prefixes for hooks, options, meta keys, CSS classes and handles.
- New Support modules must be self-booting and added to `app/setup.php`.
- Don't add new frameworks (jQuery, Alpine, React…) without discussion.

### AI tooling

The `.cursor/` folder contains the project rules, commands and task skills used with AI coding assistants. It is excluded from deploys.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| Styles/JS missing or pointing at `localhost:5173` | A stale `public/hot` file exists. Stop `npm run dev` or delete `public/hot`, then `npm run build` |
| New block not appearing | Check that the class is in `app/Blocks/` and run `wp acorn optimize:clear` in the Site shell |
| `/logout/` or `/my-account/membership/` returns 404 | Flush permalinks |
| `KLogger.php` error with Gravity Forms locally | Already handled by `GravityForms/GravityFormsKLoggerBootstrap.php` |
| `getimagesize()` SSL errors locally | Handled by `Dev/LocalSslStreamBypass.php` (local environment only) |
| Tailwind class used only in PHP isn't styled | Add it to `resources/css/tailwind-safelist.css` |
| Deploy skipped with "missing secrets" | Add the `SG_*` secrets in GitHub |
| Deploy stopped at "Remote path check failed" | `SG_WP_PATH` is wrong or the theme folder doesn't exist on the server |
| Live site not showing changes | Check the Action log, then purge the cache in SiteGround Speed Optimizer |

---

## Roadmap

See **[`TODO.md`](TODO.md)** for the prioritized backlog. Top priority: the **vendor application page**.

See **[`CHANGELOG.md`](CHANGELOG.md)** for release history.

---

## Credits

- Theme development: **Samir Joza**, SJ Web Development — [samirjoza.dev](https://samirjoza.dev)
- Built on [Sage](https://roots.io/sage/) and [Acorn](https://roots.io/acorn/) by [Roots](https://roots.io/). Sage is MIT licensed; the original license is kept in `LICENSE.md`.
