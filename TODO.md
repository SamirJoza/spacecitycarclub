# Space City Car Club — theme roadmap & backlog

This file lives in the **theme root** next to `CHANGELOG.md`. It is a **living backlog** for improvements that help the club **stand out**, **grow attendance**, and **convert** visitors into **members** or **sponsors**. Dates are **intentionally conservative** (wide windows, minimal overlap assumed with a day job): treat them as *not before / target complete by*, not aggressive sprint estimates.

**How to use**

- Adjust start/end when you pick up or finish work.
- Shippped theme work should still be recorded in `CHANGELOG.md` with a `style.css` version bump (project convention).
- Code references below point at this repo’s `app/` and `resources/` trees unless noted.

**Sources**

- In-repo: blocks (`SponsorshipSignup`, `SponsorShowcase`, `SponsorDirectory`), WooCommerce / PMPro templates, admin operational roles, marketing/referral helpers.
- Public site review: [https://spacecitycarclub.com](https://spacecitycarclub.com) (hero, membership paths, events, blog, shop, sponsor visibility).

---

## Priority 1 — must do first

| Item | Notes | Start | Target end |
|------|--------|-------|------------|
| **Vendor application page** | Dedicated page (or template + content) for **food/merch/service vendors** at events: what you offer, insurance/health permit expectations, fees/deposit, load-in windows, contact, and a **Gravity Form** (or existing form pattern) with clear routing to ops. Link from event pages and footer/nav when ready. *No vendor flow exists in the codebase today* (sponsor flows are separate). | **2026-06-01** | **2026-09-30** |

---

## Members & community (conversion & retention)

| Item | Notes | Start | Target end |
|------|--------|-------|------------|
| **Founding member scarcity UX** | Homepage shows “only N spots” — add honest inventory sync (PMPro level cap or transient) so the number cannot drift from reality; document how staff updates it. | 2026-07-01 | 2026-10-31 |
| **Membership decision helper** | Short comparison (Founding vs Member vs Veteran): who each is for + single primary CTA; consider reusing block patterns or a small custom block. | 2026-08-01 | 2026-11-30 |
| **Post-signup onboarding** | Email sequence or My Account welcome panel: next event, Discord/social links, member directory, code of conduct. Woo templates under `resources/views/woocommerce/myaccount/`. | 2026-09-01 | 2027-01-31 |
| **Event → member CTA loop** | After each big event, templated recap post + “join before next one” block; tie into existing events content type if present. | 2026-10-01 | ongoing |

---

## Sponsors & partners (revenue & credibility)

| Item | Notes | Start | Target end |
|------|--------|-------|------------|
| **Sponsor journey audit** | Trace: `SponsorShowcase` / `SponsorDirectory` / single `single-sponsor` → `SponsorshipSignup` + GF. Fix any dead ends; add one persistent “Partner with us” entry in header or footer (`resources/views/components/`). | 2026-07-01 | 2026-11-30 |
| **Sponsor media kit page** | PDF or page: audience demographics (Houston + automotive), event foot traffic, logo placement specs, tier table aligned with `SponsorshipSignup` tiers. | 2026-08-01 | 2027-01-31 |
| **Sponsor proof / logos** | `SponsorShowcase` v1 uses featured image only (`SponsorShowcase.php` comments) — optional light/dark logos, or WebP variants for CLS. | 2026-09-01 | 2027-02-28 |
| **“Why sponsor” storytelling** | Case study posts (before/after event exposure); link from sponsor CTA block. | 2026-10-01 | ongoing |

---

## Site experience & trust (from public homepage review)

| Item | Notes | Start | Target end |
|------|--------|-------|------------|
| **Social proof above the fold** | Member count, upcoming event date, or testimonial snippet near hero CTAs (`home.blade.php` / front page blocks). | 2026-07-15 | 2026-10-31 |
| **Clear “Visit an event” path** | Visitors who are not ready to pay: free/open meet RSVP or calendar subscribe; reduces bounce from `Join us` only. | 2026-08-01 | 2026-12-31 |
| **Shop ↔ club story** | Homepage links to `/shop/` — add copy bridge (“member discounts”, “supports the club”) so shop feels connected, not bolted on. | 2026-08-15 | 2026-11-30 |
| **Newsletter / wait list** | Section partial `resources/views/sections/newsletter.blade.php` — confirm double opt-in, lead magnet (e.g. event calendar PDF), and GDPR-friendly copy. | 2026-07-01 | 2026-10-31 |
| **Referral tracking** | `app/Support/Marketing/ReferralSourceTracker.php` + admin referral field — document UTM conventions for campaigns; add dashboard note for staff. | 2026-09-01 | 2026-12-31 |

---

## Technical & maintainability (theme / PHP)

| Item | Notes | Start | Target end |
|------|--------|-------|------------|
| **Operational roles docs** | Single markdown or wiki page for caps (`OperationalRoles`, `AdminAccess`, `AdminMenus`) so new chairs know what screens exist. | 2026-06-15 | 2026-09-30 |
| **GF + sponsorship field IDs** | Document Gravity Form IDs and dynamic population param (`SponsorshipSignup`) in `TODO` appendix or internal doc to avoid editor breakage. | 2026-07-01 | 2026-08-31 |
| **Performance pass** | Images, font subsetting, lazy below-fold sponsor marquee; measure mobile LCP on homepage and sponsor templates. | 2026-10-01 | 2027-02-28 |
| **Accessibility sweep** | Focus order on tier cards, form errors, contrast on dark sections; align with WCAG AA where practical. | 2026-11-01 | 2027-03-31 |
| **i18n / locale** | Theme text domains mix `sage` / `sccc` — gradual alignment if you ever translate. | 2027-01-01 | ongoing |

---

## Optional / later (high upside, lower urgency)

| Item | Notes | Start | Target end |
|------|--------|-------|------------|
| **Business directory ↔ sponsors** | If `template-business-directory` should surface paid partners differently from free listings, define rules. | 2027-02-01 | 2027-06-30 |
| **Video backgrounds** | `PageVideoBackground` / `video-background` templates — use sparingly for sponsor or flagship event landings; keep performance budget. | 2027-03-01 | 2027-08-31 |
| **Mobile nav sponsor peek** | One sponsor or “Become a sponsor” in mobile drawer (`main-nav.blade.php`) — avoid clutter. | 2027-04-01 | 2027-07-31 |

---

## Completed (move rows here when done)

| Item | Completed |
|------|-----------|
| *— none yet —* | |

---

*Last reviewed: 2026-05-13*
