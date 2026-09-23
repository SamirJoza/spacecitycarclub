{{-- File: resources/views/components/main-nav.blade.php --}}
{{--
  Main navigation component.

  Why this file exists:
  - Renders the primary navigation for desktop and the off-canvas drawer for mobile/tablet.
  - Keeps guest/member account actions inside the same component so header auth UI stays consistent.
  - Adds small, scoped helper classes so the header CSS can style the nav hover state and auth actions
    without changing any of the underlying menu/account logic.

  Label output note:
  - WordPress/Navi menu labels can arrive already encoded, for example:
    "Terms &amp; Conditions".
  - Blade's {{ }} output is intentionally escaped for safety, but that can double-encode
    an already-encoded label.
  - This component decodes menu labels once with wp_specialchars_decode(), then still outputs
    with {{ }} so Blade can safely escape the final rendered text.
--}}
@props([
  'name'    => null,
  'inactive'=> 'text-muted hover:text-accent-500',
  'active'  => 'text-accent-500',
])

@php
  $menu = Navi::build($name);

  /**
   * Decode WordPress/Navi menu labels before Blade escapes them.
   *
   * Why this exists:
   * - WordPress menu titles may be stored or returned with HTML entities.
   * - Blade's escaped output is still the correct final output method.
   * - Decoding first prevents visible labels like "Terms &amp; Conditions"
   *   while avoiding unsafe raw output.
   */
  $decodeMenuLabel = static function ($label): string {
    return wp_specialchars_decode((string) $label, ENT_QUOTES);
  };

  /**
   * Account UI data for desktop + mobile drawer.
   *
   * Why this exists:
   * - Keeps the member/guest actions inside the nav component that actually
   *   renders the desktop navigation and mobile drawer.
   * - Avoids splitting auth UI between header.blade.php and main-nav.blade.php.
   */
  $isLoggedIn = is_user_logged_in();
  $currentUser = $isLoggedIn ? wp_get_current_user() : null;

  $displayName = $currentUser
    ? ($currentUser->display_name ?: $currentUser->user_login)
    : '';

  $avatarUrl = $currentUser
    ? get_avatar_url($currentUser->ID, [
        'size'    => 72,
        'default' => 'initials',
      ])
    : '';

  $accountUrl = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('myaccount')
    : site_url('/my-account');
@endphp

@if ($menu->isNotEmpty())
  <nav {{ $attributes->class('flex-none site-header-nav') }} aria-label="Primary" data-offcanvas="push-left">
    <div class="flex items-center gap-6">
      {{-- Desktop nav (lg and up) --}}
      <ul class="hidden lg:flex items-center gap-6 text-sm uppercase tracking-[0.08em]" role="menubar">
        @foreach ($menu->all() as $item)
          @php
            $itemLabel      = $decodeMenuLabel($item->label ?? '');
            $hasChildren    = !empty($item->children);
            $hasActiveChild = false;

            if ($hasChildren) {
              foreach ($item->children as $c) {
                if (!empty($c->active)) {
                  $hasActiveChild = true;
                  break;
                }
              }
            }

            $isActive  = !empty($item->active) || $hasActiveChild;

            $wpClasses = is_array($item->classes) ? implode(' ', $item->classes) : (string)($item->classes ?? '');
            $needle    = strtolower(trim($wpClasses.' '.$itemLabel));
            $isCTA     = (strpos($needle, 'cta') !== false) || (strpos($needle, 'signup') !== false) || (strpos($needle, 'button') !== false);

            $liClasses = trim('relative group '.$wpClasses.' '.($isActive ? $active : $inactive));

            $linkBase  = 'site-header__nav-link relative inline-flex items-center gap-2 px-2 py-3 font-medium rounded-md transition-colors
                          focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500
                          no-underline visited:no-underline hover:no-underline focus:no-underline';

            $linkClass = $isCTA
              ? 'site-header__menu-cta inline-flex items-center gap-2 rounded-full px-4 py-2 font-semibold text-white
                 transition will-change-transform no-underline visited:no-underline hover:no-underline focus:no-underline'
              : $linkBase;
          @endphp

          <li class="{{ $liClasses }}" role="none">
            <a href="{{ $item->url }}"
               class="{{ $linkClass }}"
               role="menuitem"
               @if($isActive) aria-current="page" @endif
               @if($hasChildren && !$isCTA) aria-haspopup="true" aria-expanded="false" @endif>
              {{ $itemLabel }}
              @if ($hasChildren && ! $isCTA)
                <svg class="ml-1 h-3 w-3 text-muted group-hover:text-accent-500 transition-transform duration-200 group-hover:rotate-180"
                     viewBox="0 0 10 6" fill="none" aria-hidden="true">
                  <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span class="sr-only">submenu</span>
              @endif
            </a>

            {{-- Desktop Dropdown (2nd level) --}}
            @if ($hasChildren && ! $isCTA)
              <ul role="menu"
                  class="submenu invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100
                         transition ease-out duration-150 absolute left-0 top-full mt-3 min-w-56 rounded-xl border p-2 shadow-xl z-50 rule
                         bg-surface/90 backdrop-blur-md">
                @foreach ($item->children as $child)
                  @php
                    $childLabel       = $decodeMenuLabel($child->label ?? '');
                    $childHasChildren = !empty($child->children);
                    $childWp          = is_array($child->classes) ? implode(' ', $child->classes) : (string)($child->classes ?? '');
                    $childActive      = !empty($child->active);

                    $childLink  = 'flex items-center justify-between gap-2 px-3 py-2 rounded-md text-[.8125rem] tracking-normal transition-colors
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40
                                   no-underline visited:no-underline hover:no-underline focus:no-underline';
                    $childState = $childActive ? $active.' bg-surface/70' : 'text-muted';
                  @endphp

                  <li role="none" class="relative group {{ $childWp }}">
                    <a role="menuitem"
                       class="{{ $childLink }} {{ $childState }}"
                       href="{{ $child->url }}"
                       @if($childHasChildren) aria-haspopup="true" aria-expanded="false" @endif>
                      <span>{{ $childLabel }}</span>
                      @if ($childHasChildren)
                        <svg class="h-3 w-3 shrink-0 text-muted" viewBox="0 0 8 12" fill="none" aria-hidden="true">
                          <path d="M1.5 1.5L6.5 6l-5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <span class="sr-only">submenu</span>
                      @endif
                    </a>

                    {{-- Desktop Flyout (3rd level) --}}
                    @if ($childHasChildren)
                      <ul role="menu"
                          class="invisible opacity-0 group-hover:visible group-hover:opacity-100 focus-within:visible focus-within:opacity-100
                                 transition ease-out duration-150 absolute left-full top-0 ml-2 min-w-56 rounded-xl border p-2 shadow-xl z-50 rule
                                 bg-surface/90 backdrop-blur-md">
                        @foreach ($child->children as $grand)
                          @php
                            $grandLabel = $decodeMenuLabel($grand->label ?? '');
                            $gActive    = !empty($grand->active);
                          @endphp

                          <li role="none" class="block">
                            <a role="menuitem"
                               class="block px-3 py-2 rounded-md text-[.8125rem] tracking-normal transition-colors
                                      {{ $gActive ? $active.' bg-surface/70' : 'text-muted' }}
                                      no-underline visited:no-underline hover:no-underline focus:no-underline"
                               href="{{ $grand->url }}">
                              {{ $grandLabel }}
                            </a>
                          </li>
                        @endforeach
                      </ul>
                    @endif
                  </li>
                @endforeach
              </ul>
            @endif
          </li>
        @endforeach
      </ul>

      {{-- Desktop account actions (lg and up) --}}
      <div class="hidden lg:flex items-center gap-3 ml-2 shrink-0">
        @if ($isLoggedIn && $currentUser)
          <a
            href="{{ esc_url($accountUrl) }}"
            class="site-header__welcome-pill max-h-[44px]"
            aria-label="{{ esc_attr('Go to My Account for ' . $displayName) }}"
            title="{{ esc_attr('Go to My Account') }}"
          >
            <img
              src="{{ esc_url($avatarUrl) }}"
              alt="{{ esc_attr($displayName) }}"
              class="h-10 w-10 rounded-full object-cover ring-2 ring-white/80 shrink-0"
              width="40"
              height="40"
            >

            <span class="min-w-0 flex items-baseline gap-1 text-sm font-medium whitespace-nowrap">
              <span class="opacity-90">Welcome</span>
              <span class="inline-block max-w-[10rem] truncate font-semibold">{{ esc_html($displayName) }}</span>
            </span>
          </a>
        @else
          <a
            href="{{ esc_url(wp_login_url()) }}"
            class="site-header__auth-link"
          >
            Log in
          </a>
          <a
            href="{{ esc_url(site_url('/signup')) }}"
            class="site-header__auth-button"
          >
            Sign up
          </a>
        @endif
      </div>

      {{-- Mobile hamburger (shows until lg) --}}
      <button
        class="lg:hidden ml-auto inline-flex items-center justify-center rounded-md p-2 text-muted hover:text-accent-500
               focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        data-offcanvas-toggle
        aria-controls="mobile-drawer"
        aria-expanded="false"
        aria-label="Open menu">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    {{-- Overlay (ported to <body> by JS) --}}
    <div class="lg:hidden fixed inset-0 bg-black/40 hidden z-[90]" data-offcanvas-overlay></div>

    {{-- LEFT DRAWER (ported to <body> by JS) --}}
    <aside
      id="mobile-drawer"
      class="lg:hidden fixed inset-y-0 left-0 w-[min(20rem,85vw)] max-h-dvh overflow-y-auto -translate-x-full
             bg-surface border-r rule shadow-2xl p-4 z-[100] transition-transform duration-300 ease-out"
      data-offcanvas-drawer
      aria-hidden="true"
      style="--drawer-w: 20rem"
    >
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm uppercase tracking-[0.12em] text-muted">Menu</span>

        <div class="flex items-center gap-1">
          {{-- Mobile theme toggle --}}
          {{-- <button
            class="inline-flex items-center justify-center rounded-md p-2 text-muted hover:text-accent-500
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            type="button"
            data-theme-toggle
            aria-pressed="false"
            aria-label="Toggle dark mode">
            <svg data-icon="sun" class="h-5 w-5" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5"/>
              <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <svg data-icon="moon" class="h-5 w-5 hidden" viewBox="0 0 24 24" fill="none">
              <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 1 0 9.8 9.8Z"
                    stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            </svg>
          </button> --}}

          {{-- Close drawer --}}
          <button
            class="inline-flex items-center justify-center rounded-md p-2 text-muted hover:text-accent-500
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            data-offcanvas-close
            aria-label="Close menu">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </div>

      @if ($isLoggedIn && $currentUser)
        <a
          href="{{ esc_url($accountUrl) }}"
          class="mb-4 flex items-center gap-3 rounded-2xl border border-amber-300/60 bg-amber-100 px-3 py-3 text-amber-900 shadow-sm transition-colors hover:bg-amber-200/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 dark:border-amber-200/30 dark:bg-amber-300/90 dark:text-amber-950 no-underline visited:no-underline hover:no-underline focus:no-underline"
          aria-label="{{ esc_attr('Go to My Account for ' . $displayName) }}"
          title="{{ esc_attr('Go to My Account') }}"
        >
          <img
            src="{{ esc_url($avatarUrl) }}"
            alt="{{ esc_attr($displayName) }}"
            class="h-10 w-10 rounded-full object-cover ring-2 ring-white/80 shrink-0"
            width="40"
            height="40"
          >

          <span class="min-w-0 flex items-baseline gap-1 text-sm font-medium whitespace-nowrap">
            <span class="opacity-90">Welcome</span>
            <span class="inline-block max-w-[10rem] truncate font-semibold">{{ esc_html($displayName) }}</span>
          </span>
        </a>
      @else
        <div class="mb-4 grid gap-2">
          <a
            href="{{ esc_url(wp_login_url()) }}"
            class="site-header__auth-link site-header__drawer-auth-link"
          >
            Log in
          </a>
          <a
            href="{{ esc_url(site_url('/signup')) }}"
            class="site-header__auth-button site-header__drawer-auth-button"
          >
            Sign up
          </a>
        </div>
      @endif

      {{-- Mobile list (unchanged structurally; CTA items get the same premium pill treatment) --}}
      <ul class="space-y-1">
        @foreach ($menu->all() as $item)
          @php
            $itemLabel   = $decodeMenuLabel($item->label ?? '');
            $hasChildren = !empty($item->children);
            $isActive    = !empty($item->active);
            $wpClasses   = is_array($item->classes) ? implode(' ', $item->classes) : (string)($item->classes ?? '');
            $needle      = strtolower(trim($wpClasses.' '.$itemLabel));
            $isCTA       = (strpos($needle, 'cta') !== false) || (strpos($needle, 'signup') !== false) || (strpos($needle, 'button') !== false);
          @endphp

          <li class="py-1">
            <a href="{{ $item->url }}"
               class="{{ $isCTA
                        ? 'site-header__menu-cta inline-flex items-center gap-2 rounded-full px-4 py-2 font-semibold text-white transition no-underline visited:no-underline hover:no-underline focus:no-underline'
                        : 'flex items-center justify-between px-2 py-2 rounded-md font-medium '.($isActive ? $active : $inactive.' hover:text-accent-500').' no-underline visited:no-underline hover:no-underline focus:no-underline' }}"
               @if($isActive) aria-current="page" @endif
               @if($hasChildren && !$isCTA) aria-haspopup="true" aria-expanded="false" @endif>
              <span>{{ $itemLabel }}</span>
              @if ($hasChildren && ! $isCTA)
                <svg class="ml-1 h-2.5 w-2.5 text-muted shrink-0" viewBox="0 0 8 12" fill="none" aria-hidden="true">
                  <path d="M1.5 1.5L6.5 6l-5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span class="sr-only">submenu</span>
              @endif
            </a>

            @if ($hasChildren && ! $isCTA)
              <ul class="mt-1 ml-3 space-y-1">
                @foreach ($item->children as $child)
                  @php
                    $childLabel = $decodeMenuLabel($child->label ?? '');
                  @endphp

                  <li>
                    <a href="{{ $child->url }}"
                       class="block px-2 py-1 rounded-md text-[0.95rem]
                              {{ !empty($child->active) ? $active : $inactive.' hover:text-accent-500' }}
                              no-underline visited:no-underline hover:no-underline focus:no-underline"
                       @if(!empty($child->active)) aria-current="page" @endif>
                      {{ $childLabel }}
                    </a>
                  </li>
                @endforeach
              </ul>
            @endif
          </li>
        @endforeach
      </ul>
    </aside>
  </nav>
@endif