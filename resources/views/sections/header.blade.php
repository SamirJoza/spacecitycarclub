@php
  /**
   * File path + filename: resources/views/sections/header.blade.php
   *
   * Purpose:
   * - Render the site header brand, primary navigation, and right-side auth/member area.
   * - Render the shop-only navigation as a second header row on WooCommerce shop pages.
   *
   * Why this exists:
   * - The main site header is already sticky.
   * - The shop navigation should stick with the header instead of trying to stick
   *   independently underneath it.
   * - Keeping the shop nav inside the header prevents the visual gap that happened
   *   when the shop nav was rendered from the WooCommerce loop area.
   *
   * Notes:
   * - The primary navigation is still handled by the existing x-main-nav component.
   * - The shop navigation only renders when App\Support\Woo\ShopNavigation says
   *   the current request is shop-related.
   */
@endphp

<header class="site-header">
  <div class="header-inner">
    {{-- Brand --}}
    <a href="{{ home_url('/') }}" class="brand flex items-center" aria-label="{{ get_bloginfo('name') }}">
      @svg('resources.images.logo', 'h-[60px] lg:h-[100px] w-auto block')
      <span class="sr-only">{{ get_bloginfo('name') }}</span>
    </a>

    {{-- Primary navigation --}}
    <x-main-nav name="primary_navigation" class="site-header-nav ml-auto" />
  </div>

  {{-- Shop-only second header row --}}
  @if (class_exists(\App\Support\Woo\ShopNavigation::class))
    {!! \App\Support\Woo\ShopNavigation::renderHeaderNavigation() !!}
  @endif
</header>