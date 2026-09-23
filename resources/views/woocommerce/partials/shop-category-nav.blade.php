{{--
  File path + filename: resources/views/woocommerce/partials/shop-category-nav.blade.php

  Purpose:
  - Render the shop-only header navigation.
  - Display WooCommerce product categories as a second row inside the sticky site
    header.
  - Keep the shop navigation visually consistent with the main header nav.
  - Apply the shared submenu design language: subtle glass row, tiny left neon
    bar, accent hover state, and clearer active state.

  Why this file exists:
  - The shop navigation belongs visually with the sticky header.
  - The PHP support class builds the product category tree.
  - This Blade partial only handles markup, styling, and small UI behavior.

  Current behavior:
  - Desktop/tablet: centered secondary shop nav.
  - Desktop top-level links borrow the main header nav behavior:
    muted text, accent hover, rounded focus states, and neon underline.
  - Desktop subnav opens directly below the shop row.
  - Mobile: compact accordion-style shop menu.
  - Product categories support parent / child / grandchild levels.
  - The shop submenu links now match the main-nav submenu treatment.

  Important:
  - This does not include member-only filtering yet.
  - This does not include Printify pricing logic yet.
--}}

@php
  $nodes = $nodes ?? [];
  $shopUrl = $shopUrl ?? '';
  $isShopLanding = !empty($isShopLanding);

  $displayName = static function ($name): string {
    return html_entity_decode((string) $name, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
  };
@endphp

@if (!empty($nodes))
  @once
    <style>
      /*
       * File path + filename: resources/views/woocommerce/partials/shop-category-nav.blade.php
       *
       * Scoped header shop navigation styles.
       */

      body.woocommerce-shop .woocommerce-products-header__title,
      body.tax-product_cat .woocommerce-products-header__title,
      body.tax-product_tag .woocommerce-products-header__title {
        display: none !important;
      }

      .sccc-shop-header-nav {
        --sccc-shop-nav-container: 80rem;
        --sccc-shop-nav-panel-bg:
          radial-gradient(520px 210px at 12% 0%, color-mix(in oklab, var(--color-primary-500) 10%, transparent), transparent 62%),
          color-mix(in oklab, var(--color-surface) 90%, transparent);
        --sccc-shop-nav-panel-bg-strong: color-mix(in oklab, var(--color-surface) 94%, transparent);
        --sccc-shop-nav-border: var(--color-line);
        --sccc-shop-nav-text: var(--color-text);
        --sccc-shop-nav-muted: var(--color-muted);
        --sccc-shop-nav-accent: var(--color-accent-500);
        --sccc-shop-submenu-indicator-gradient: var(
          --sccc-header-submenu-indicator-gradient,
          linear-gradient(180deg, rgba(113, 215, 255, 0.96), rgba(174, 113, 255, 0.88))
        );
        --sccc-shop-submenu-indicator-shadow: var(
          --sccc-header-submenu-indicator-shadow,
          0 0 10px rgba(113, 215, 255, 0.36), 0 0 18px rgba(174, 113, 255, 0.18)
        );
        --sccc-shop-submenu-row-hover-bg: var(
          --sccc-header-submenu-row-hover-bg,
          color-mix(in oklab, var(--color-line) 45%, transparent)
        );
        --sccc-shop-submenu-row-active-bg: var(
          --sccc-header-submenu-row-active-bg,
          color-mix(in oklab, var(--color-surface) 80%, transparent)
        );

        position: relative;
        border-top: 1px solid var(--sccc-shop-nav-border);
        background: color-mix(in oklab, var(--color-surface) 72%, transparent);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
      }

      .dark .sccc-shop-header-nav,
      html[data-theme="dark"] .sccc-shop-header-nav {
        background: color-mix(in oklab, var(--color-surface) 68%, transparent);
      }

      .sccc-shop-header-nav,
      .sccc-shop-header-nav * {
        box-sizing: border-box;
      }

      .sccc-shop-header-nav a {
        text-decoration: none;
      }

      .sccc-shop-header-nav__inner {
        position: relative;
        max-width: var(--sccc-shop-nav-container);
        margin: 0 auto;
        padding: 0 1rem;
      }

      .sccc-shop-header-nav__desktop {
        display: none;
      }

      .sccc-shop-header-nav__bar {
        min-height: 3.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .sccc-shop-header-nav__nav {
        min-width: 0;
        max-width: 100%;
      }

      .sccc-shop-header-nav__top-list {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        min-width: 0;
        max-width: 100%;
        margin: 0;
        padding: 0;
        list-style: none;
        overflow-x: auto;
        scrollbar-width: none;
      }

      .sccc-shop-header-nav__top-list::-webkit-scrollbar {
        display: none;
      }

      .sccc-shop-header-nav__top-item {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
      }

      .sccc-shop-header-nav__top-link,
      .sccc-shop-header-nav__top-summary {
        position: relative;
        isolation: isolate;
        min-height: 3.25rem;
        display: inline-flex;
        align-items: center;
        gap: 0.42rem;
        border: 0;
        border-radius: 0.375rem;
        padding: 0.75rem 0.6rem;
        color: var(--sccc-shop-nav-muted);
        background: transparent;
        font: inherit;
        font-size: 0.875rem;
        line-height: 1;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
        cursor: pointer;
        outline: none;
        transition: color 0.18s ease;
      }

      .sccc-shop-header-nav__top-summary {
        list-style: none;
      }

      .sccc-shop-header-nav__top-summary::-webkit-details-marker {
        display: none;
      }

      .sccc-shop-header-nav__top-link::after,
      .sccc-shop-header-nav__top-summary::after {
        content: "";
        position: absolute;
        left: 0.35rem;
        right: 0.35rem;
        bottom: 0.45rem;
        height: 2px;
        border-radius: 9999px;
        background: var(--sccc-header-underline-gradient, linear-gradient(90deg, transparent, var(--sccc-shop-nav-accent), transparent));
        box-shadow: var(--sccc-header-underline-shadow, 0 0 10px color-mix(in oklab, var(--sccc-shop-nav-accent) 35%, transparent));
        opacity: 0;
        transform: scaleX(0.35);
        transform-origin: left center;
        transition: opacity 0.22s ease, transform 0.22s ease;
        pointer-events: none;
      }

      .sccc-shop-header-nav__top-link:hover,
      .sccc-shop-header-nav__top-link:focus-visible,
      .sccc-shop-header-nav__top-summary:hover,
      .sccc-shop-header-nav__top-summary:focus-visible,
      .sccc-shop-header-nav__top-details[open] > .sccc-shop-header-nav__top-summary {
        color: var(--sccc-shop-nav-accent);
      }

      .sccc-shop-header-nav__top-link:hover::after,
      .sccc-shop-header-nav__top-link:focus-visible::after,
      .sccc-shop-header-nav__top-link.is-active::after,
      .sccc-shop-header-nav__top-summary:hover::after,
      .sccc-shop-header-nav__top-summary:focus-visible::after,
      .sccc-shop-header-nav__top-summary.is-active::after,
      .sccc-shop-header-nav__top-details[open] > .sccc-shop-header-nav__top-summary::after {
        opacity: 1;
        transform: scaleX(1);
      }

      .sccc-shop-header-nav__top-link.is-active,
      .sccc-shop-header-nav__top-summary.is-active {
        color: var(--sccc-shop-nav-accent);
      }

      .sccc-shop-header-nav__top-link:focus-visible,
      .sccc-shop-header-nav__top-summary:focus-visible {
        box-shadow: 0 0 0 2px color-mix(in oklab, var(--sccc-shop-nav-accent) 45%, transparent);
      }

      .sccc-shop-header-nav__chevron {
        width: 1rem;
        height: 1rem;
        opacity: 0.78;
        transition: transform 0.18s ease;
      }

      .sccc-shop-header-nav__top-details[open] .sccc-shop-header-nav__chevron {
        transform: rotate(180deg);
      }

      .sccc-shop-header-nav__mega {
        position: absolute;
        left: 1rem;
        right: 1rem;
        top: 100%;
        z-index: 80;
        display: none;
        border: 1px solid var(--sccc-shop-nav-border);
        border-radius: 0 0 1rem 1rem;
        background: var(--sccc-shop-nav-panel-bg);
        box-shadow:
          0 24px 60px rgb(0 0 0 / 0.28),
          inset 0 1px 0 rgb(255 255 255 / 0.08);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        overflow: hidden;
      }

      .sccc-shop-header-nav__top-details[open] .sccc-shop-header-nav__mega {
        display: block;
      }

      .sccc-shop-header-nav__mega-inner {
        max-height: min(18rem, 58vh);
        overflow: auto;
        padding: 1rem;
      }

      .sccc-shop-header-nav__mega-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding-bottom: 0.8rem;
        margin-bottom: 0.85rem;
        border-bottom: 1px solid var(--sccc-shop-nav-border);
      }

      .sccc-shop-header-nav__mega-title {
        margin: 0;
        color: var(--sccc-shop-nav-text);
        font-size: 1rem;
        line-height: 1.15;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
      }

      .sccc-shop-header-nav__mega-all {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        color: var(--sccc-shop-nav-muted);
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        transition:
          color 0.18s ease,
          transform 0.18s ease;
      }

      .sccc-shop-header-nav__mega-all:hover,
      .sccc-shop-header-nav__mega-all:focus-visible {
        color: var(--sccc-shop-nav-accent);
        transform: translateX(2px);
        outline: none;
      }

      .sccc-shop-header-nav__mega-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.15rem;
      }

      .sccc-shop-header-nav__mega-column {
        min-width: 0;
      }

      .sccc-shop-header-nav__mega-link {
        position: relative;
        isolation: isolate;
        display: block;
        overflow: hidden;
        border-radius: 0.5rem;
        padding: 0.44rem 0.55rem 0.44rem 1rem;
        color: var(--sccc-shop-nav-muted);
        font-size: 0.8125rem;
        line-height: 1.25;
        font-weight: 500;
        text-transform: capitalize;
        transform: translateX(0);
        transition:
          color 0.18s ease,
          background-color 0.18s ease,
          transform 0.18s ease;
      }

      .sccc-shop-header-nav__mega-link::before {
        content: "";
        position: absolute;
        left: 0.4rem;
        top: 0.52rem;
        bottom: 0.52rem;
        width: 2px;
        border-radius: 999px;
        background: var(--sccc-shop-submenu-indicator-gradient);
        box-shadow: var(--sccc-shop-submenu-indicator-shadow);
        opacity: 0;
        transform: scaleY(0.35);
        transform-origin: center;
        transition: opacity 0.18s ease, transform 0.18s ease;
        pointer-events: none;
      }

      .sccc-shop-header-nav__mega-link:hover,
      .sccc-shop-header-nav__mega-link:focus-visible,
      .sccc-shop-header-nav__mega-link.is-active {
        color: var(--sccc-shop-nav-accent);
        background: var(--sccc-shop-submenu-row-hover-bg);
        transform: translateX(2px);
        outline: none;
      }

      .sccc-shop-header-nav__mega-link:hover::before,
      .sccc-shop-header-nav__mega-link:focus-visible::before,
      .sccc-shop-header-nav__mega-link.is-active::before {
        opacity: 1;
        transform: scaleY(1);
      }

      .sccc-shop-header-nav__mega-link.is-active {
        background: var(--sccc-shop-submenu-row-active-bg);
      }

      .sccc-shop-header-nav__mega-link--parent {
        margin-bottom: 0.34rem;
        padding-bottom: 0.56rem;
        color: var(--sccc-shop-nav-text);
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        border-bottom: 1px solid var(--sccc-shop-nav-border);
      }

      .sccc-shop-header-nav__mega-link--parent:hover,
      .sccc-shop-header-nav__mega-link--parent:focus-visible,
      .sccc-shop-header-nav__mega-link--parent.is-active {
        color: var(--sccc-shop-nav-accent);
      }

      .sccc-shop-header-nav__mega-sublist {
        display: grid;
        gap: 0.04rem;
        margin: 0;
        padding: 0;
        list-style: none;
      }

      .sccc-shop-header-nav__mobile {
        display: block;
      }

      .sccc-shop-header-nav__mobile-main {
        border: 0;
        background: transparent;
        overflow: hidden;
      }

      .sccc-shop-header-nav__mobile-summary {
        min-height: 3.7rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 0;
        cursor: pointer;
        list-style: none;
      }

      .sccc-shop-header-nav__mobile-summary::-webkit-details-marker {
        display: none;
      }

      .sccc-shop-header-nav__mobile-title {
        display: block;
        color: var(--sccc-shop-nav-text);
        font-size: 0.875rem;
        line-height: 1;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .sccc-shop-header-nav__mobile-icon {
        width: 2.2rem;
        height: 2.2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: 1px solid var(--sccc-shop-nav-border);
        border-radius: 999px;
        color: var(--sccc-shop-nav-muted);
        background: rgb(255 255 255 / 0.04);
        transition: transform 0.18s ease, color 0.18s ease;
      }

      .sccc-shop-header-nav__mobile-main[open] .sccc-shop-header-nav__mobile-icon {
        color: var(--sccc-shop-nav-accent);
        transform: rotate(180deg);
      }

      .sccc-shop-header-nav__mobile-panel {
        max-height: min(28rem, 68vh);
        overflow: auto;
        border-top: 1px solid var(--sccc-shop-nav-border);
        padding: 0.8rem 0;
      }

      .sccc-shop-header-nav__mobile-link,
      .sccc-shop-header-nav__mobile-group-summary {
        position: relative;
        isolation: isolate;
        overflow: hidden;
      }

      .sccc-shop-header-nav__mobile-link {
        display: block;
        border-radius: 0.85rem;
        padding: 0.68rem 0.75rem 0.68rem 1.08rem;
        color: var(--sccc-shop-nav-muted);
        font-size: 0.88rem;
        line-height: 1.2;
        font-weight: 600;
        text-transform: capitalize;
        transform: translateX(0);
        transition:
          color 0.18s ease,
          background-color 0.18s ease,
          transform 0.18s ease;
      }

      .sccc-shop-header-nav__mobile-link::before {
        content: "";
        position: absolute;
        left: 0.44rem;
        top: 0.58rem;
        bottom: 0.58rem;
        width: 2px;
        border-radius: 999px;
        background: var(--sccc-shop-submenu-indicator-gradient);
        box-shadow: var(--sccc-shop-submenu-indicator-shadow);
        opacity: 0;
        transform: scaleY(0.35);
        transform-origin: center;
        transition: opacity 0.18s ease, transform 0.18s ease;
        pointer-events: none;
      }

      .sccc-shop-header-nav__mobile-link:hover,
      .sccc-shop-header-nav__mobile-link:focus-visible,
      .sccc-shop-header-nav__mobile-link.is-active {
        color: var(--sccc-shop-nav-accent);
        background: var(--sccc-shop-submenu-row-hover-bg);
        transform: translateX(2px);
        outline: none;
      }

      .sccc-shop-header-nav__mobile-link:hover::before,
      .sccc-shop-header-nav__mobile-link:focus-visible::before,
      .sccc-shop-header-nav__mobile-link.is-active::before {
        opacity: 1;
        transform: scaleY(1);
      }

      .sccc-shop-header-nav__mobile-group {
        margin-top: 0.5rem;
        border: 1px solid var(--sccc-shop-nav-border);
        border-radius: 0.95rem;
        background: rgb(255 255 255 / 0.03);
        overflow: hidden;
      }

      .sccc-shop-header-nav__mobile-group-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
        padding: 0.75rem 0.82rem 0.75rem 1.08rem;
        color: var(--sccc-shop-nav-text);
        font-size: 0.88rem;
        line-height: 1.2;
        font-weight: 700;
        cursor: pointer;
        list-style: none;
        text-transform: capitalize;
        transition:
          color 0.18s ease,
          background-color 0.18s ease,
          transform 0.18s ease;
      }

      .sccc-shop-header-nav__mobile-group-summary::before {
        content: "";
        position: absolute;
        left: 0.44rem;
        top: 0.58rem;
        bottom: 0.58rem;
        width: 2px;
        border-radius: 999px;
        background: var(--sccc-shop-submenu-indicator-gradient);
        box-shadow: var(--sccc-shop-submenu-indicator-shadow);
        opacity: 0;
        transform: scaleY(0.35);
        transform-origin: center;
        transition: opacity 0.18s ease, transform 0.18s ease;
        pointer-events: none;
      }

      .sccc-shop-header-nav__mobile-group[open] > .sccc-shop-header-nav__mobile-group-summary,
      .sccc-shop-header-nav__mobile-group-summary:hover,
      .sccc-shop-header-nav__mobile-group-summary:focus-visible {
        color: var(--sccc-shop-nav-accent);
        background: var(--sccc-shop-submenu-row-hover-bg);
        transform: translateX(2px);
        outline: none;
      }

      .sccc-shop-header-nav__mobile-group[open] > .sccc-shop-header-nav__mobile-group-summary::before,
      .sccc-shop-header-nav__mobile-group-summary:hover::before,
      .sccc-shop-header-nav__mobile-group-summary:focus-visible::before {
        opacity: 1;
        transform: scaleY(1);
      }

      .sccc-shop-header-nav__mobile-group-summary::-webkit-details-marker {
        display: none;
      }

      .sccc-shop-header-nav__mobile-group-panel {
        border-top: 1px solid var(--sccc-shop-nav-border);
        padding: 0.5rem;
      }

      .sccc-shop-header-nav__mobile-sublist {
        display: grid;
        gap: 0.05rem;
        margin: 0.25rem 0 0 0.55rem;
        padding: 0 0 0 0.55rem;
        border-left: 1px solid var(--sccc-shop-nav-border);
        list-style: none;
      }

      .sccc-shop-breadcrumbs {
        max-width: 80rem;
        margin: 1rem auto 0.95rem;
        padding: 0 1rem;
        color: var(--color-muted, #a0a7b2);
        font-size: 0.92rem;
        line-height: 1.4;
      }

      .sccc-shop-breadcrumbs__container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
      }

      .sccc-shop-breadcrumbs a {
        color: var(--color-muted, #a0a7b2);
        text-decoration: none;
      }

      .sccc-shop-breadcrumbs a:hover,
      .sccc-shop-breadcrumbs a:focus-visible {
        color: var(--color-accent-500, #ff1744);
        text-decoration: none;
      }

      .sccc-shop-breadcrumbs__separator {
        opacity: 0.6;
      }

      @media (min-width: 1024px) {
        .sccc-shop-header-nav__desktop {
          display: block;
        }

        .sccc-shop-header-nav__mobile {
          display: none;
        }
      }

      @media (max-width: 1180px) {
        .sccc-shop-header-nav__mega-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 767px) {
        .sccc-shop-header-nav__inner,
        .sccc-shop-breadcrumbs {
          padding-left: 1rem;
          padding-right: 1rem;
        }
      }
    </style>

    <script>
      /*
       * File path + filename: resources/views/woocommerce/partials/shop-category-nav.blade.php
       *
       * Purpose:
       * - Make the desktop shop mega menu behave like a controlled click menu.
       *
       * Behavior:
       * - Only one desktop mega panel can be open at a time.
       * - Clicking outside closes the open panel.
       * - Pressing Escape closes the open panel.
       */
      document.addEventListener('DOMContentLoaded', function () {
        const navs = document.querySelectorAll('.sccc-shop-header-nav');

        navs.forEach(function (nav) {
          const desktopDetails = nav.querySelectorAll('.sccc-shop-header-nav__top-details');

          desktopDetails.forEach(function (detail) {
            detail.addEventListener('toggle', function () {
              if (!detail.open) {
                return;
              }

              desktopDetails.forEach(function (otherDetail) {
                if (otherDetail !== detail) {
                  otherDetail.open = false;
                }
              });
            });
          });

          document.addEventListener('click', function (event) {
            if (nav.contains(event.target)) {
              return;
            }

            desktopDetails.forEach(function (detail) {
              detail.open = false;
            });
          });

          document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
              return;
            }

            desktopDetails.forEach(function (detail) {
              detail.open = false;
            });
          });
        });
      });
    </script>
  @endonce

  <div class="sccc-shop-header-nav" aria-label="{{ esc_attr__('Shop categories', 'sage') }}">
    <div class="sccc-shop-header-nav__inner">
      {{-- Mobile accordion navigation --}}
      <div class="sccc-shop-header-nav__mobile">
        <details class="sccc-shop-header-nav__mobile-main">
          <summary class="sccc-shop-header-nav__mobile-summary">
            <span class="sccc-shop-header-nav__mobile-title">
              {{ __('Browse Merchandise', 'sage') }}
            </span>

            <span class="sccc-shop-header-nav__mobile-icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
          </summary>

          <div class="sccc-shop-header-nav__mobile-panel">
            <a
              href="{{ esc_url($shopUrl) }}"
              class="sccc-shop-header-nav__mobile-link {{ $isShopLanding ? 'is-active' : '' }}"
              @if ($isShopLanding) aria-current="page" @endif
            >
              {{ __('All Merch', 'sage') }}
            </a>

            @foreach ($nodes as $node)
              @php
                $nodeName = $displayName($node['name'] ?? '');
                $hasChildren = !empty($node['children']);
                $isActiveTrail = !empty($node['isActiveTrail']);
                $isCurrent = !empty($node['isCurrent']);
              @endphp

              @if ($hasChildren)
                <details class="sccc-shop-header-nav__mobile-group" @if ($isActiveTrail) open @endif>
                  <summary class="sccc-shop-header-nav__mobile-group-summary">
                    <span>{{ $nodeName }}</span>
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                      <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </summary>

                  <div class="sccc-shop-header-nav__mobile-group-panel">
                    <a
                      href="{{ esc_url($node['url']) }}"
                      class="sccc-shop-header-nav__mobile-link {{ $isCurrent ? 'is-active' : '' }}"
                      @if ($isCurrent) aria-current="page" @endif
                    >
                      {{ sprintf(__('View all %s', 'sage'), $nodeName) }}
                    </a>

                    @foreach ($node['children'] as $child)
                      @php
                        $childName = $displayName($child['name'] ?? '');
                        $childHasChildren = !empty($child['children']);
                        $childIsActiveTrail = !empty($child['isActiveTrail']);
                        $childIsCurrent = !empty($child['isCurrent']);
                      @endphp

                      <a
                        href="{{ esc_url($child['url']) }}"
                        class="sccc-shop-header-nav__mobile-link {{ $childIsActiveTrail ? 'is-active' : '' }}"
                        @if ($childIsCurrent) aria-current="page" @endif
                      >
                        {{ $childName }}
                      </a>

                      @if ($childHasChildren)
                        <ul class="sccc-shop-header-nav__mobile-sublist">
                          @foreach ($child['children'] as $grandchild)
                            @php
                              $grandchildName = $displayName($grandchild['name'] ?? '');
                              $grandchildIsCurrent = !empty($grandchild['isCurrent']);
                            @endphp

                            <li>
                              <a
                                href="{{ esc_url($grandchild['url']) }}"
                                class="sccc-shop-header-nav__mobile-link {{ $grandchildIsCurrent ? 'is-active' : '' }}"
                                @if ($grandchildIsCurrent) aria-current="page" @endif
                              >
                                {{ $grandchildName }}
                              </a>
                            </li>
                          @endforeach
                        </ul>
                      @endif
                    @endforeach
                  </div>
                </details>
              @else
                <a
                  href="{{ esc_url($node['url']) }}"
                  class="sccc-shop-header-nav__mobile-link {{ $isActiveTrail ? 'is-active' : '' }}"
                  @if ($isCurrent) aria-current="page" @endif
                >
                  {{ $nodeName }}
                </a>
              @endif
            @endforeach
          </div>
        </details>
      </div>

      {{-- Desktop secondary shop navigation --}}
      <div class="sccc-shop-header-nav__desktop">
        <div class="sccc-shop-header-nav__bar">
          <nav class="sccc-shop-header-nav__nav" aria-label="{{ esc_attr__('Merchandise categories', 'sage') }}">
            <ul class="sccc-shop-header-nav__top-list">
              <li class="sccc-shop-header-nav__top-item">
                <a
                  href="{{ esc_url($shopUrl) }}"
                  class="sccc-shop-header-nav__top-link {{ $isShopLanding ? 'is-active' : '' }}"
                  @if ($isShopLanding) aria-current="page" @endif
                >
                  {{ __('All Merch', 'sage') }}
                </a>
              </li>

              @foreach ($nodes as $node)
                @php
                  $nodeName = $displayName($node['name'] ?? '');
                  $hasChildren = !empty($node['children']);
                  $isActiveTrail = !empty($node['isActiveTrail']);
                  $isCurrent = !empty($node['isCurrent']);
                @endphp

                <li class="sccc-shop-header-nav__top-item">
                  @if ($hasChildren)
                    <details class="sccc-shop-header-nav__top-details">
                      <summary class="sccc-shop-header-nav__top-summary {{ $isActiveTrail ? 'is-active' : '' }}">
                        <span>{{ $nodeName }}</span>

                        <svg class="sccc-shop-header-nav__chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                          <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                      </summary>

                      <div class="sccc-shop-header-nav__mega">
                        <div class="sccc-shop-header-nav__mega-inner">
                          <div class="sccc-shop-header-nav__mega-header">
                            <h3 class="sccc-shop-header-nav__mega-title">
                              {{ $nodeName }}
                            </h3>

                            <a
                              href="{{ esc_url($node['url']) }}"
                              class="sccc-shop-header-nav__mega-all"
                              @if ($isCurrent) aria-current="page" @endif
                            >
                              <span>{{ sprintf(__('View all %s', 'sage'), $nodeName) }}</span>
                              <span aria-hidden="true">→</span>
                            </a>
                          </div>

                          <div class="sccc-shop-header-nav__mega-grid">
                            @foreach ($node['children'] as $child)
                              @php
                                $childName = $displayName($child['name'] ?? '');
                                $childHasChildren = !empty($child['children']);
                                $childIsActiveTrail = !empty($child['isActiveTrail']);
                                $childIsCurrent = !empty($child['isCurrent']);
                              @endphp

                              <div class="sccc-shop-header-nav__mega-column">
                                <a
                                  href="{{ esc_url($child['url']) }}"
                                  class="sccc-shop-header-nav__mega-link sccc-shop-header-nav__mega-link--parent {{ $childIsActiveTrail ? 'is-active' : '' }}"
                                  @if ($childIsCurrent) aria-current="page" @endif
                                >
                                  {{ $childName }}
                                </a>

                                @if ($childHasChildren)
                                  <ul class="sccc-shop-header-nav__mega-sublist">
                                    @foreach ($child['children'] as $grandchild)
                                      @php
                                        $grandchildName = $displayName($grandchild['name'] ?? '');
                                        $grandchildIsCurrent = !empty($grandchild['isCurrent']);
                                      @endphp

                                      <li>
                                        <a
                                          href="{{ esc_url($grandchild['url']) }}"
                                          class="sccc-shop-header-nav__mega-link {{ $grandchildIsCurrent ? 'is-active' : '' }}"
                                          @if ($grandchildIsCurrent) aria-current="page" @endif
                                        >
                                          {{ $grandchildName }}
                                        </a>
                                      </li>
                                    @endforeach
                                  </ul>
                                @endif
                              </div>
                            @endforeach
                          </div>
                        </div>
                      </div>
                    </details>
                  @else
                    <a
                      href="{{ esc_url($node['url']) }}"
                      class="sccc-shop-header-nav__top-link {{ $isActiveTrail ? 'is-active' : '' }}"
                      @if ($isCurrent) aria-current="page" @endif
                    >
                      {{ $nodeName }}
                    </a>
                  @endif
                </li>
              @endforeach
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>
@endif