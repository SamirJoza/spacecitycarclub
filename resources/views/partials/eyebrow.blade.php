{{--
|--------------------------------------------------------------------------
| Blade Partial
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/eyebrow.blade.php
|
| Purpose:
| - Render the top "eyebrow" navigation row.
| - Output social icons from Theme Settings.
| - Output WooCommerce account and cart icons when Woo is active.
| - Keep the eyebrow row at a stable height so all elements can be
|   vertically centered consistently.
| - Position the cart count badge above the cart icon instead of letting
|   it visually collide with the cart glyph.
|
| Notes:
| - All CSS is scoped to this partial only.
| - The main change here is structural spacing discipline:
|   1) the eyebrow row now has an intentional minimum height
|   2) each of the 3 grid columns is stretched and vertically centered
|   3) icon links now have a predictable square hit area
|   4) the cart badge is positioned relative to that hit area
| - This file now also defensively normalizes the Theme Settings socials value
|   before iterating, so the frontend does not fatal if ACF returns a scalar
|   instead of repeater rows for any reason.
|
| Why this approach:
| - A stable row height gives the badge actual room to sit above the cart.
| - Explicit icon hit areas make the layout feel cleaner and more premium.
| - Normalizing the `socials` option to an array keeps the eyebrow safe even
|   when the option is empty, stale, or unexpectedly malformed.
| - This keeps the change minimal and safe without changing the overall
|   header structure or introducing unrelated refactors.
|--------------------------------------------------------------------------
--}}

@props([])

@php
  /**
   * -------------------------------------------------------------------------
   * Theme Settings → Social Connections
   * -------------------------------------------------------------------------
   * Why this block exists:
   * - The Social Connections option is expected to be an ACF repeater.
   * - In practice, option values can occasionally come back as a scalar
   *   instead of an array, which would hard-fail inside `@foreach`.
   * - We normalize to an array here so the partial stays resilient and the
   *   frontend keeps rendering even if the option payload is malformed.
   */
  $rows = function_exists('get_field') ? get_field('socials', 'option') : [];

  if (! is_array($rows)) {
    $rows = [];
  }

  /**
   * -------------------------------------------------------------------------
   * WooCommerce (safe checks)
   * -------------------------------------------------------------------------
   * Why this block exists:
   * - The eyebrow should not assume WooCommerce is active.
   * - All Woo-dependent URLs and counts are guarded so this partial remains
   *   safe on environments where WooCommerce is disabled or not loaded yet.
   */
  $hasWoo       = function_exists('WC');
  $myAccountUrl = ($hasWoo && function_exists('wc_get_page_permalink')) ? wc_get_page_permalink('myaccount') : null;
  $cartUrl      = ($hasWoo && function_exists('wc_get_cart_url')) ? wc_get_cart_url() : null;
  $cartCount    = ($hasWoo && WC() && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
@endphp

@once
  <style>
    /* ---------------------------------------------------------------------
     | Scoped just to this partial
     | ---------------------------------------------------------------------
     | Why this exists:
     | - Keeps hover/focus, row sizing, icon sizing, and badge positioning
     |   isolated to the eyebrow nav only.
     | - Prevents styling bleed into other header or icon components.
     * ------------------------------------------------------------------- */

    .eyebrow-nav {
      /* -------------------------------------------------------------------
       | Eyebrow sizing tokens
       | -------------------------------------------------------------------
       | Why these exist:
       | - The eyebrow now gets a real row height instead of relying only
       |   on vertical padding.
       | - A stable height gives the cart badge room to sit above the icon
       |   while keeping all elements visually centered.
       * ----------------------------------------------------------------- */
      --eyebrow-row-height: 2.875rem;
      --eyebrow-icon-hit-size: 2rem;

      /* -------------------------------------------------------------------
       | Cart badge tokens
       | -------------------------------------------------------------------
       | Why these exist:
       | - Centralized variables make the badge easier to fine tune later.
       | - A warm accent is more noticeable against the darker header tones.
       | - The ring uses the eyebrow background so the badge feels separate
       |   from the cart icon instead of overlapping it visually.
       * ----------------------------------------------------------------- */
      --eyebrow-cart-badge-bg:
        linear-gradient(
          135deg,
          color-mix(in srgb, var(--secondary) 82%, white 18%) 0%,
          var(--secondary) 58%,
          color-mix(in srgb, var(--secondary) 84%, black 16%) 100%
        );
      --eyebrow-cart-badge-text: #ffffff;
      --eyebrow-cart-badge-ring: var(--eyebrow-bg, var(--surface));
      --eyebrow-cart-badge-shadow:
        0 0 0 1px color-mix(in srgb, var(--secondary) 35%, transparent),
        0 6px 14px color-mix(in srgb, var(--secondary) 40%, transparent),
        0 0 16px color-mix(in srgb, var(--secondary) 26%, transparent);
    }

    @media (min-width: 768px) {
      .eyebrow-nav {
        /* Slightly taller on md+ so the row breathes more cleanly */
        --eyebrow-row-height: 3rem;
      }
    }

    /* ---------------------------------------------------------------------
     | Shared eyebrow row sizing
     | ---------------------------------------------------------------------
     | Why this exists:
     | - The row gets a real minimum height.
     | - Children stretch to the full row height so their internal flex
     |   alignment actually centers content vertically.
     * ------------------------------------------------------------------- */
    .eyebrow-nav .eyebrow-nav__row {
      min-height: var(--eyebrow-row-height);
      align-items: stretch;
    }

    .eyebrow-nav .eyebrow-nav__row > * {
      min-width: 0;
    }

    .eyebrow-nav .eyebrow-nav__group {
      min-height: 100%;
      display: flex;
      align-items: center;
    }

    .eyebrow-nav .eyebrow-nav__group--left {
      justify-content: flex-start;
    }

    .eyebrow-nav .eyebrow-nav__group--center {
      justify-content: center;
      text-align: center;
    }

    .eyebrow-nav .eyebrow-nav__group--right {
      justify-content: flex-end;
    }

    /* ---------------------------------------------------------------------
     | Icon links
     | ---------------------------------------------------------------------
     | Why this exists:
     | - Each icon now lives inside a predictable square hit area.
     | - That makes centering more consistent and gives the cart badge a
     |   reliable box to anchor against.
     * ------------------------------------------------------------------- */
    .eyebrow-nav a.icon-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: var(--eyebrow-icon-hit-size);
      min-height: var(--eyebrow-icon-hit-size);
      color: var(--muted);
      line-height: 1;
      transition: color .15s ease, transform .15s ease;
    }

    .eyebrow-nav a.icon-link:hover,
    .eyebrow-nav a.icon-link:focus-visible {
      color: var(--secondary);
    }

    .eyebrow-nav a.icon-link:hover {
      transform: translateY(-1px);
    }

    /* ---------------------------------------------------------------------
     | Force FA SVGs to follow link color
     | ---------------------------------------------------------------------
     | Why this exists:
     | - Ensures the icon inherits the link state color consistently.
     * ------------------------------------------------------------------- */
    .eyebrow-nav a.icon-link svg {
      width: 1rem;
      height: 1rem;
    }

    .eyebrow-nav a.icon-link svg,
    .eyebrow-nav a.icon-link svg * {
      fill: currentColor !important;
      stroke: currentColor !important;
    }

    /* ---------------------------------------------------------------------
     | Cart badge positioning
     | ---------------------------------------------------------------------
     | Why this exists:
     | - The badge is now anchored to the cart link's square hit area,
     |   not just a tight icon box.
     | - That makes it much easier to place the badge above the cart
     |   without colliding with the glyph itself.
     | - The translate pushes it outward into the top-right corner in a
     |   more intentional "floating" position.
     * ------------------------------------------------------------------- */
    .eyebrow-nav a.cart-link {
      position: relative;
      overflow: visible;
    }

    .eyebrow-nav .cart-badge {
      position: absolute;
      top: 0;
      right: 0;
      z-index: 10;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 18px;
      height: 18px;
      padding-inline: 4px;
      border-radius: 9999px;
      transform: translate(46%, -30%);
      background: var(--eyebrow-cart-badge-bg);
      color: var(--eyebrow-cart-badge-text);
      font-size: 10px;
      font-weight: 700;
      line-height: 1;
      letter-spacing: 0.01em;
      border: 2px solid var(--eyebrow-cart-badge-ring);
      box-shadow: var(--eyebrow-cart-badge-shadow);
      pointer-events: none;
      color: red;
    }
  </style>
@endonce

<div class="eyebrow-nav border-b text-[var(--muted)]"
     style="--eyebrow-bg: color-mix(in srgb, var(--surface) 92%, transparent);
            --eyebrow-border: color-mix(in srgb, var(--line) 45%, transparent);
            background: var(--eyebrow-bg);
            border-color: var(--eyebrow-border);">
  <div class="max-w-7xl mx-auto px-4">
    <div class="eyebrow-nav__row grid grid-cols-3 gap-4 text-xs">
      {{-- LEFT: Social icons from Theme Settings --}}
      <div class="eyebrow-nav__group eyebrow-nav__group--left gap-3">
        @foreach ($rows as $row)
          @php
            /**
             * ---------------------------------------------------------------
             * Defensive row normalization
             * ---------------------------------------------------------------
             * Why this block exists:
             * - Even when the outer option is an array, an individual row can
             *   still be malformed.
             * - Skipping non-array rows keeps the loop safe and prevents
             *   notices/fatals from bad payloads.
             */
            if (! is_array($row)) {
              continue;
            }

            $key = strtolower(trim((string) ($row['social'] ?? '')));
            $url = trim((string) ($row['socialURL'] ?? ''));
          @endphp

          @if ($url !== '')
            @switch($key)
              @case('fb')
                <a href="{{ esc_url($url) }}" target="_blank" rel="noopener"
                   class="icon-link rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_srgb,var(--secondary)_45%,transparent)] focus-visible:ring-offset-1 focus-visible:ring-offset-[var(--surface)]"
                   aria-label="Facebook">
                  <x-fab-facebook-f />
                </a>
              @break

              @case('ig')
                <a href="{{ esc_url($url) }}" target="_blank" rel="noopener"
                   class="icon-link rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_srgb,var(--secondary)_45%,transparent)] focus-visible:ring-offset-1 focus-visible:ring-offset-[var(--surface)]"
                   aria-label="Instagram">
                  <x-fab-instagram />
                </a>
              @break

              @case('yt')
                <a href="{{ esc_url($url) }}" target="_blank" rel="noopener"
                   class="icon-link rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_srgb,var(--secondary)_45%,transparent)] focus-visible:ring-offset-1 focus-visible:ring-offset-[var(--surface)]"
                   aria-label="YouTube">
                  <x-fab-youtube />
                </a>
              @break

              @case('tiktok')
                <a href="{{ esc_url($url) }}" target="_blank" rel="noopener"
                   class="icon-link rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_srgb,var(--secondary)_45%,transparent)] focus-visible:ring-offset-1 focus-visible:ring-offset-[var(--surface)]"
                   aria-label="TikTok">
                  <x-fab-tiktok />
                </a>
              @break

              @case('x')
                <a href="{{ esc_url($url) }}" target="_blank" rel="noopener"
                   class="icon-link rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_srgb,var(--secondary)_45%,transparent)] focus-visible:ring-offset-1 focus-visible:ring-offset-[var(--surface)]"
                   aria-label="X (Twitter)">
                  <x-fab-x-twitter />
                </a>
              @break

              @default
                {{-- Unknown select value; skip --}}
            @endswitch
          @endif
        @endforeach
      </div>

      {{-- CENTER: Optional slot (notice/tagline) --}}
      <div class="eyebrow-nav__group eyebrow-nav__group--center text-[var(--muted)]">
        {{ $slot ?? '' }}
      </div>

      {{-- RIGHT: Woo account + cart (if WooCommerce is active) --}}
      <div class="eyebrow-nav__group eyebrow-nav__group--right gap-2.5">
        @if ($hasWoo)
          @if ($myAccountUrl)
            <a href="{{ $myAccountUrl }}"
               class="icon-link rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_srgb,var(--secondary)_45%,transparent)] focus-visible:ring-offset-1 focus-visible:ring-offset-[var(--surface)]"
               aria-label="My account">
              <x-fas-user />
            </a>
          @endif

          @if ($cartUrl)
            <a href="{{ $cartUrl }}"
               class="cart-link icon-link rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_srgb,var(--secondary)_45%,transparent)] focus-visible:ring-offset-1 focus-visible:ring-offset-[var(--surface)]"
               aria-label="Shopping cart">
              <x-fas-cart-shopping />

              @if ($cartCount > 0)
                <span class="cart-badge" aria-hidden="true">
                  {{ $cartCount }}
                </span>
              @endif
            </a>
          @endif
        @endif
      </div>
    </div>
  </div>
</div>