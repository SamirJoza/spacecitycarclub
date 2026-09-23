{{-- 
  File: resources/views/partials/legal-nav.blade.php

  Purpose:
  Renders a small legal/footer-style navigation menu from a WordPress menu via Log1x Navi.

  Notes:
  - Navi / WordPress can return menu labels with encoded entities, for example:
    "Terms &amp; Conditions"
  - Blade's {{ }} output escapes content again for safety.
  - To avoid showing "&amp;" visibly in the browser, we decode WordPress entities first,
    then still output with {{ }} so Blade can safely escape the final text.

  Important:
  Do not replace {{ $label }} with {!! $item->label !!}.
  That would avoid the visual issue, but it would also bypass Blade escaping.
--}}

@props([
  'name'        => null,     // WP menu name/slug, e.g. 'legal_navigation'
  'separator'   => '•',      // set to null to remove separators
  'flatten'     => true,     // flatten up to 3 levels
  'wrap'        => true,     // allow wrapping on small screens
  'hashAsSpan'  => false,    // render "#" as <span>
  'gap'         => 'gap-3',  // spacing between items
])

@php
  /*
   * Build the requested WordPress menu through Navi.
   * The component accepts a menu name/slug so it can be reused for legal,
   * footer, utility, or other compact navigation menus.
   */
  $menu  = Navi::build($name);
  $items = collect();

  /*
   * Flatten up to three menu levels when requested.
   * This keeps legal/footer navigation visually simple even if the WP menu
   * contains parent, child, and grandchild items.
   */
  if ($menu && $menu->isNotEmpty()) {
    foreach ($menu->all() as $parent) {
      if (! $flatten) {
        $items->push($parent);
        continue;
      }

      $items->push($parent);

      foreach ($parent->children ?? [] as $child) {
        $items->push($child);

        foreach ($child->children ?? [] as $grand) {
          $items->push($grand);
        }
      }
    }
  }

  /*
   * Remove empty labels before rendering.
   * This keeps separators from appearing around empty menu items.
   */
  $items = $items
    ->filter(fn ($n) => ! empty($n->label))
    ->values();

  /*
   * Keep the existing wrapping behavior controlled by the component prop.
   */
  $wrapClass = $wrap ? 'flex-wrap' : 'flex-nowrap';
@endphp

@if ($items->isNotEmpty())
  <ul {{ $attributes->merge(['class' => "legal-nav flex items-center $wrapClass $gap"]) }}>
    @foreach ($items as $i => $item)
      @php
        /*
         * Normalize menu item data for rendering.
         */
        $href     = $item->url ?? '#';
        $isHash   = $hashAsSpan && ($href === '#' || $href === '' || $href === null);
        $isActive = (bool) ($item->active ?? false);

        /*
         * Fix double-encoded WordPress/Navi labels.
         *
         * Example:
         * Raw label from menu: Terms &amp; Conditions
         * Decoded here:        Terms & Conditions
         * Blade output:        Terms &amp; Conditions in HTML source
         * Browser displays:    Terms & Conditions
         */
        $label = wp_specialchars_decode((string) $item->label, ENT_QUOTES);
      @endphp

      <li class="inline-flex items-center">
        @if ($isHash)
          <span>{{ $label }}</span>
        @else
          <a href="{{ $href }}" @if ($isActive) aria-current="page" @endif>{{ $label }}</a>
        @endif
      </li>

      @if (! is_null($separator) && $i < $items->count() - 1)
        <li aria-hidden="true" class="px-1 text-[0.9em]" style="color: var(--muted)">
          {{ $separator }}
        </li>
      @endif
    @endforeach
  </ul>
@endif