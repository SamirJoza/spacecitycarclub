{{--
  File: resources/views/partials/member-directory-card.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Renders one Member Directory card.

  This partial expects one variable:
  - $member

  The $member array is prepared by:
  - app/Support/Members/MemberDirectory.php

  Display rules
  ------------------------------------------------------------------------------
  Shows:
  - profile photo / avatar fallback
  - display name
  - member since label
  - short bio preview
  - full bio modal trigger
  - garage mosaic
  - website link
  - social links as icon + @username
  - optional service/responder badge

  Does NOT show:
  - email
  - phone
  - birthdate
  - membership number
  - admin roles

  Garage mosaic behavior
  ------------------------------------------------------------------------------
  - First vehicle gets the larger hero tile.
  - Secondary vehicles render in a square-tile mosaic on the right.
  - If the number of secondary slots is odd, the last tile spans the full width.
  - This creates a more deliberate stepped layout instead of the earlier
    generic 2x2 feel.
  - Color is intentionally ignored because the current vehicle data model does
    not store vehicle color.

  Modal behavior
  ------------------------------------------------------------------------------
  This card includes a hidden <template> with the member’s full bio/modal content.
  The page template includes the shared modal shell + JavaScript that reads
  this template and injects it into the modal.

  Important modal decision
  ------------------------------------------------------------------------------
  The garage is intentionally NOT repeated inside the modal. The card already
  shows the garage mosaic, so the modal stays focused on the full member bio and
  profile links.
--}}

@php
  /*
  |--------------------------------------------------------------------------
  | Safe Card Defaults
  |--------------------------------------------------------------------------
  |
  | The service class should always provide these keys, but defaults keep the
  | partial from throwing notices if a member has incomplete profile data.
  |
  */

  $member = is_array($member ?? null) ? $member : [];

  $memberId = (int) ($member['id'] ?? 0);
  $displayName = trim((string) ($member['display_name'] ?? __('Member', 'sccc')));
  $profileImageUrl = (string) ($member['profile_image_url'] ?? '');
  $initial = trim((string) ($member['initial'] ?? 'M'));
  $memberSinceLabel = (string) ($member['member_since_label'] ?? __('Active member', 'sccc'));
  $bio = trim((string) ($member['bio'] ?? ''));
  $bioExcerpt = trim((string) ($member['bio_excerpt'] ?? ''));
  $website = (string) ($member['website'] ?? '');
  $vehicles = is_array($member['vehicles'] ?? null) ? $member['vehicles'] : [];
  $vehicleCount = (int) ($member['vehicle_count'] ?? count($vehicles));
  $garageSummary = (string) ($member['garage_summary'] ?? __('No vehicles listed yet', 'sccc'));
  $socialLinks = is_array($member['social_links'] ?? null) ? $member['social_links'] : [];
  $serviceBadge = is_array($member['service_badge'] ?? null) ? $member['service_badge'] : [];

  /*
  |--------------------------------------------------------------------------
  | Template IDs
  |--------------------------------------------------------------------------
  |
  | Each member card stores its own modal content in a unique template.
  | The global modal JavaScript uses these IDs when opening the full bio.
  |
  */

  $modalTemplateId = 'sccc-member-modal-template-' . $memberId;
  $modalTitleId = 'sccc-member-modal-title-' . $memberId;

  /*
  |--------------------------------------------------------------------------
  | Garage Mosaic Vehicles
  |--------------------------------------------------------------------------
  |
  | Layout intention:
  | - Vehicle 1 = large hero tile.
  | - Secondary vehicles = smaller square tiles in a right-side grid.
  | - If the secondary slot count is odd, the last tile spans full width.
  |
  | Overflow handling:
  | - We reserve a maximum of 6 secondary slots on the right.
  | - If more vehicles exist, the last available slot becomes a +N tile.
  |
  */

  $primaryVehicle = $vehicles[0] ?? null;

  $maxSecondarySlots = 6;
  $secondaryVehicleLimit = $vehicleCount > ($maxSecondarySlots + 1)
    ? ($maxSecondarySlots - 1)
    : $maxSecondarySlots;

  $secondaryVehicles = array_slice($vehicles, 1, $secondaryVehicleLimit);

  $overflowCount = max(0, $vehicleCount - 1 - count($secondaryVehicles));
  $showOverflowTile = $overflowCount > 0;

  $secondarySlotCount = count($secondaryVehicles) + ($showOverflowTile ? 1 : 0);
  $lastSecondaryShouldSpan = $secondarySlotCount > 0 && ($secondarySlotCount % 2 === 1);

  /*
  |--------------------------------------------------------------------------
  | Bio Empty State
  |--------------------------------------------------------------------------
  |
  | We still show a compact message if there is no bio, but only show the modal
  | button when a real bio exists.
  |
  */

  $hasBio = $bio !== '';
@endphp

<article class="sccc-member-card" data-sccc-member-card="{{ esc_attr((string) $memberId) }}">
  {{--
    ========================================================================
    Card Header
    ========================================================================
    Compact identity area. The profile image is intentionally smaller than a
    full author hero so the directory feels like a roster, not a blog archive.
  --}}
  <header class="sccc-member-card__head">
    <div class="sccc-member-card__avatar-wrap" aria-hidden="true">
      @if ($profileImageUrl)
        <img
          src="{{ esc_url($profileImageUrl) }}"
          alt=""
          class="sccc-member-card__avatar"
          loading="lazy"
        >
      @else
        <span class="sccc-member-card__avatar sccc-member-card__avatar--fallback">
          {{ $initial }}
        </span>
      @endif
    </div>

    <div class="sccc-member-card__identity">
      <p class="sccc-member-card__eyebrow">
        {{ __('Member', 'sccc') }}
      </p>

      <h2 class="sccc-member-card__name">
        {{ $displayName }}
      </h2>

      <p class="sccc-member-card__since">
        {{ $memberSinceLabel }}
      </p>
    </div>
  </header>

  @if (! empty($serviceBadge['label']))
    <div class="sccc-member-card__badges" aria-label="{{ esc_attr__('Member badges', 'sccc') }}">
      <span class="sccc-member-card__badge">
        {{ $serviceBadge['label'] }}
      </span>
    </div>
  @endif

  {{--
    ========================================================================
    Bio Preview
    ========================================================================
    The preview stays short so cards remain equal-ish in height. The full bio
    is available in the modal template below.
  --}}
  <div class="sccc-member-card__bio">
    @if ($hasBio)
      <p>
        {{ $bioExcerpt }}
      </p>

      <button
        type="button"
        class="sccc-member-card__bio-trigger"
        data-sccc-member-modal-open="1"
        data-sccc-template-id="{{ esc_attr($modalTemplateId) }}"
        aria-haspopup="dialog"
      >
        {{ __('Read full bio', 'sccc') }}
        <span aria-hidden="true">→</span>
      </button>
    @else
      <p class="sccc-member-card__muted">
        {{ __('No bio added yet.', 'sccc') }}
      </p>
    @endif
  </div>

  {{--
    ========================================================================
    Garage Mosaic
    ========================================================================
    Vehicle 1 = large hero tile.
    Secondary vehicles = smaller right-side tiles.
    If the final secondary slot count is odd, the last slot spans the full width.
  --}}
  <section class="sccc-member-card__garage" aria-label="{{ esc_attr(sprintf(__('%s garage preview', 'sccc'), $displayName)) }}">
    <div class="sccc-member-card__garage-head">
      <span>{{ __('Garage', 'sccc') }}</span>
      <span>{{ $vehicleCount > 0 ? sprintf(_n('%d vehicle', '%d vehicles', $vehicleCount, 'sccc'), $vehicleCount) : __('Empty', 'sccc') }}</span>
    </div>

    @if (is_array($primaryVehicle))
      @php
        $primaryLabel = (string) ($primaryVehicle['label'] ?? __('Vehicle', 'sccc'));
        $primaryTooltip = (string) ($primaryVehicle['tooltip'] ?? $primaryLabel);
        $primaryImage = (string) ($primaryVehicle['image_url_large'] ?? '');
      @endphp

      <div class="sccc-member-mosaic">
        <figure
          class="sccc-member-mosaic__tile sccc-member-mosaic__tile--primary"
          data-tooltip="{{ esc_attr($primaryTooltip) }}"
          tabindex="0"
          title="{{ esc_attr($primaryTooltip) }}"
        >
          @if ($primaryImage)
            <img
              src="{{ esc_url($primaryImage) }}"
              alt="{{ esc_attr($primaryLabel) }}"
              loading="lazy"
            >
          @else
            <div class="sccc-member-mosaic__placeholder">
              <span>{{ $primaryLabel }}</span>
            </div>
          @endif
        </figure>

        @if (! empty($secondaryVehicles) || $showOverflowTile)
          <div class="sccc-member-mosaic__side">
            @foreach ($secondaryVehicles as $vehicle)
              @php
                $vehicleLabel = (string) ($vehicle['label'] ?? __('Vehicle', 'sccc'));
                $vehicleTooltip = (string) ($vehicle['tooltip'] ?? $vehicleLabel);
                $vehicleImage = (string) ($vehicle['image_url_medium'] ?? '');
                $tileClasses = 'sccc-member-mosaic__tile sccc-member-mosaic__tile--small';

                if (! $showOverflowTile && $loop->last && $lastSecondaryShouldSpan) {
                  $tileClasses .= ' sccc-member-mosaic__tile--span-2';
                }
              @endphp

              <figure
                class="{{ $tileClasses }}"
                data-tooltip="{{ esc_attr($vehicleTooltip) }}"
                tabindex="0"
                title="{{ esc_attr($vehicleTooltip) }}"
              >
                @if ($vehicleImage)
                  <img
                    src="{{ esc_url($vehicleImage) }}"
                    alt="{{ esc_attr($vehicleLabel) }}"
                    loading="lazy"
                  >
                @else
                  <div class="sccc-member-mosaic__placeholder">
                    <span>{{ $vehicleLabel }}</span>
                  </div>
                @endif
              </figure>
            @endforeach

            @if ($showOverflowTile)
              @php
                $overflowTileClasses = 'sccc-member-mosaic__tile sccc-member-mosaic__tile--small sccc-member-mosaic__tile--more';

                if ($lastSecondaryShouldSpan) {
                  $overflowTileClasses .= ' sccc-member-mosaic__tile--span-2';
                }
              @endphp

              <div
                class="{{ $overflowTileClasses }}"
                data-tooltip="{{ esc_attr(sprintf(__('%d more vehicles listed', 'sccc'), $overflowCount)) }}"
                tabindex="0"
                title="{{ esc_attr(sprintf(__('%d more vehicles listed', 'sccc'), $overflowCount)) }}"
              >
                <span>+{{ $overflowCount }}</span>
              </div>
            @endif
          </div>
        @endif
      </div>
    @else
      <div class="sccc-member-mosaic sccc-member-mosaic--empty">
        <div class="sccc-member-mosaic__empty">
          <span>{{ __('No garage photos yet', 'sccc') }}</span>
        </div>
      </div>
    @endif

    <p class="sccc-member-card__garage-summary">
      {{ $garageSummary }}
    </p>
  </section>

  {{--
    ========================================================================
    Website + Social Links
    ========================================================================
    Socials render icon + @username so multiple accounts on the same platform
    remain clear.
  --}}
  @if ($website || ! empty($socialLinks))
    <footer class="sccc-member-card__links" aria-label="{{ esc_attr(sprintf(__('%s profile links', 'sccc'), $displayName)) }}">
      @if ($website)
        <a
          href="{{ esc_url($website) }}"
          class="sccc-member-card__link sccc-member-card__link--website"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="{{ esc_attr(sprintf(__('%s website', 'sccc'), $displayName)) }}"
        >
          <span aria-hidden="true">↗</span>
          <span>{{ __('Website', 'sccc') }}</span>
        </a>
      @endif

      @foreach ($socialLinks as $social)
        @php
          $socialUrl = (string) ($social['url'] ?? '');
          $socialLabel = (string) ($social['label'] ?? __('Social profile', 'sccc'));
          $socialIcon = (string) ($social['icon'] ?? '');
          $socialUsername = trim((string) ($social['username'] ?? ''));
        @endphp

        @if ($socialUrl)
          <a
            href="{{ esc_url($socialUrl) }}"
            class="sccc-member-card__link sccc-member-card__link--social"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="{{ esc_attr($socialLabel . ($socialUsername !== '' ? ': @' . $socialUsername : '')) }}"
            title="{{ esc_attr($socialLabel . ($socialUsername !== '' ? ': @' . $socialUsername : '')) }}"
          >
            {!! $socialIcon !!}

            <span class="sccc-member-card__handle">
              @if ($socialUsername !== '')
                {{ '@' . $socialUsername }}
              @else
                {{ $socialLabel }}
              @endif
            </span>
          </a>
        @endif
      @endforeach
    </footer>
  @endif
</article>

{{--
  ============================================================================
  Hidden Modal Template
  ============================================================================
  The page-level modal JavaScript reads this template when the card’s
  "Read full bio" button is clicked.

  The garage is not repeated here because the garage mosaic already lives on
  the card itself.
--}}
@if ($hasBio)
  <template id="{{ esc_attr($modalTemplateId) }}" data-sccc-member-modal-template="1">
    <article class="sccc-member-modal-content">
      <header class="sccc-member-modal-content__head">
        <div class="sccc-member-modal-content__avatar-wrap" aria-hidden="true">
          @if ($profileImageUrl)
            <img
              src="{{ esc_url($profileImageUrl) }}"
              alt=""
              class="sccc-member-modal-content__avatar"
              loading="lazy"
            >
          @else
            <span class="sccc-member-modal-content__avatar sccc-member-modal-content__avatar--fallback">
              {{ $initial }}
            </span>
          @endif
        </div>

        <div>
          <p class="sccc-member-modal-content__eyebrow">
            {{ __('Member Bio', 'sccc') }}
          </p>

          <h2 class="sccc-member-modal-content__title" id="{{ esc_attr($modalTitleId) }}">
            {{ $displayName }}
          </h2>

          <p class="sccc-member-modal-content__meta">
            {{ $memberSinceLabel }}
          </p>

          @if (! empty($serviceBadge['label']))
            <span class="sccc-member-card__badge sccc-member-modal-content__badge">
              {{ $serviceBadge['label'] }}
            </span>
          @endif
        </div>
      </header>

      <div class="sccc-member-modal-content__body">
        <h3>{{ __('Full Bio', 'sccc') }}</h3>
        <p>{!! nl2br(e($bio)) !!}</p>
      </div>

      @if ($website || ! empty($socialLinks))
        <footer class="sccc-member-modal-content__links">
          @if ($website)
            <a
              href="{{ esc_url($website) }}"
              class="sccc-member-card__link sccc-member-card__link--website"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span aria-hidden="true">↗</span>
              <span>{{ __('Website', 'sccc') }}</span>
            </a>
          @endif

          @foreach ($socialLinks as $social)
            @php
              $socialUrl = (string) ($social['url'] ?? '');
              $socialLabel = (string) ($social['label'] ?? __('Social profile', 'sccc'));
              $socialIcon = (string) ($social['icon'] ?? '');
              $socialUsername = trim((string) ($social['username'] ?? ''));
            @endphp

            @if ($socialUrl)
              <a
                href="{{ esc_url($socialUrl) }}"
                class="sccc-member-card__link sccc-member-card__link--social"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="{{ esc_attr($socialLabel . ($socialUsername !== '' ? ': @' . $socialUsername : '')) }}"
              >
                {!! $socialIcon !!}

                <span class="sccc-member-card__handle">
                  @if ($socialUsername !== '')
                    {{ '@' . $socialUsername }}
                  @else
                    {{ $socialLabel }}
                  @endif
                </span>
              </a>
            @endif
          @endforeach
        </footer>
      @endif
    </article>
  </template>
@endif