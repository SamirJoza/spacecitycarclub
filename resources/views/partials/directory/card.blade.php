{{--
  File path + filename: resources/views/partials/directory/card.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render one member business card.

  Why this file exists:
  - The directory can reuse a single presentation for each member-owned business.
  - Keeping the card separate makes future reuse possible for spotlight modules,
    related business sections, or filtered subsets.

  Card contract:
  - Business logo
  - Business name
  - Primary industry badge
  - Short, clamped description
  - Owner avatar + owner name
  - Business details modal trigger

  Important note:
  - Website, phone, and email actions intentionally live inside the business
    details modal so the card does not repeat the same contact information.
  - Button markup uses an outer shell + inner face so the existing neon button
    treatment remains consistent with the rest of the directory.
--}}

@php
  $businessId = absint($card['business_id'] ?? 0);
@endphp

<article class="sccc-business-directory__card">
  <div class="sccc-business-directory__card-glow" aria-hidden="true"></div>

  <div class="sccc-business-directory__card-body">
    <header class="sccc-business-directory__brand-row">
      <div class="sccc-business-directory__logo" aria-hidden="true">
        @if (!empty($card['business_logo_url']))
          <img src="{{ esc_url($card['business_logo_url']) }}" alt="{{ esc_attr($card['business_logo_alt']) }}" loading="lazy">
        @else
          <div class="sccc-business-directory__logo-fallback">
            {{ esc_html(function_exists('mb_substr') ? mb_substr($card['business_name'], 0, 1) : substr($card['business_name'], 0, 1)) }}
          </div>
        @endif
      </div>

      <div>
        <h3 class="sccc-business-directory__business-name">{{ esc_html($card['business_name']) }}</h3>

        @if (!empty($card['primary_category_label']))
          <span class="sccc-business-directory__category">{{ esc_html($card['primary_category_label']) }}</span>
        @endif
      </div>
    </header>

    @if (!empty($card['business_description']))
      <p class="sccc-business-directory__description">{{ esc_html($card['business_description']) }}</p>
    @endif

    <div class="sccc-business-directory__owner">
      <div class="sccc-business-directory__owner-avatar">
        {!! $card['owner_avatar'] !!}
      </div>

      <div>
        <span class="sccc-business-directory__owner-label">{{ __('Member Owner', 'sage') }}</span>
        <span class="sccc-business-directory__owner-name">{{ esc_html($card['owner_name']) }}</span>
      </div>
    </div>

    @if ($businessId > 0)
      <div class="sccc-business-directory__details-action">
        <button
          class="sccc-business-directory__neo-button sccc-business-directory__neo-button--primary sccc-business-directory__neo-button--block"
          type="button"
          data-directory-modal-open
          data-business-id="{{ esc_attr((string) $businessId) }}"
          aria-haspopup="dialog"
          aria-controls="sccc-business-directory-modal"
        >
          <span class="sccc-business-directory__neo-button-face">
            <span class="sccc-business-directory__neo-button-label">{{ __('View Business Details', 'sage') }}</span>
          </span>
        </button>
      </div>
    @endif
  </div>
</article>