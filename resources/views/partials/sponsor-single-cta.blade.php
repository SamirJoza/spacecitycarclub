{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/sponsor-single-cta.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the configurable CTA section shown beneath the sponsor content area
|   on Sponsor single pages.
|
| Why this file exists:
| - Sponsor singles need a reusable CTA that editors can control globally from
|   Theme Settings instead of hardcoding links and copy into the sponsor
|   content template.
|
| Notes:
| - This partial is intended to be included only from the Sponsor single
|   content partial.
| - It relies on the Sponsor single CSS variables already defined by the parent
|   template for color consistency across light and dark themes.
| - Buttons are rendered only when both a label and a URL are present.
|--------------------------------------------------------------------------
--}}

@php
  $ctaEnabled = (bool) get_field('sponsor_single_cta_enabled', 'option');

  $ctaAlignment = trim((string) get_field('sponsor_single_cta_alignment', 'option'));
  $ctaAlignment = in_array($ctaAlignment, ['left', 'center'], true) ? $ctaAlignment : 'left';

  $ctaEyebrow = trim((string) get_field('sponsor_single_cta_eyebrow', 'option'));
  $ctaHeading = trim((string) get_field('sponsor_single_cta_heading', 'option'));
  $ctaBody = trim((string) get_field('sponsor_single_cta_body', 'option'));

  $primaryLabel = trim((string) get_field('sponsor_single_cta_primary_label', 'option'));
  $primaryUrl = trim((string) get_field('sponsor_single_cta_primary_url', 'option'));
  $primaryNewTab = (bool) get_field('sponsor_single_cta_primary_new_tab', 'option');

  $secondaryLabel = trim((string) get_field('sponsor_single_cta_secondary_label', 'option'));
  $secondaryUrl = trim((string) get_field('sponsor_single_cta_secondary_url', 'option'));
  $secondaryNewTab = (bool) get_field('sponsor_single_cta_secondary_new_tab', 'option');

  $hasPrimaryButton = $primaryLabel !== '' && $primaryUrl !== '';
  $hasSecondaryButton = $secondaryLabel !== '' && $secondaryUrl !== '';

  $hasRenderableContent = $ctaEnabled && (
    $ctaEyebrow !== '' ||
    $ctaHeading !== '' ||
    $ctaBody !== '' ||
    $hasPrimaryButton ||
    $hasSecondaryButton
  );
@endphp

@if ($hasRenderableContent)
  @once
    <style>
      .sccc-sponsor-single-cta {
        position: relative;
        overflow: hidden;
        padding: clamp(1.4rem, 2.6vw, 2.1rem);
        border: 1px solid var(--sccc-ss-border);
        border-radius: var(--sccc-ss-radius);
        background:
          radial-gradient(circle at top right, color-mix(in oklab, var(--sccc-ss-primary) 16%, transparent), transparent 34%),
          linear-gradient(
            180deg,
            color-mix(in oklab, var(--sccc-ss-surface-strong) 92%, transparent),
            color-mix(in oklab, var(--sccc-ss-surface) 97%, transparent)
          );
        box-shadow: var(--sccc-ss-shadow);
        backdrop-filter: blur(14px);
      }

      .sccc-sponsor-single-cta::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background:
          linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.03) 52%, transparent 100%);
      }

      .sccc-sponsor-single-cta__inner {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 1rem;
      }

      .sccc-sponsor-single-cta.is-align-center .sccc-sponsor-single-cta__inner {
        justify-items: center;
        text-align: center;
      }

      .sccc-sponsor-single-cta__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        width: fit-content;
        margin: 0;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: color-mix(in oklab, var(--sccc-ss-primary) 12%, transparent);
        color: color-mix(in oklab, var(--sccc-ss-primary) 60%, var(--sccc-ss-heading));
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .sccc-sponsor-single-cta__heading {
        margin: 0;
        color: var(--sccc-ss-heading);
        font-size: clamp(1.5rem, 2.1vw, 2rem);
        font-weight: 800;
        line-height: 1.1;
      }

      .sccc-sponsor-single-cta__body {
        margin: 0;
        max-width: 60ch;
        color: var(--sccc-ss-muted);
        font-size: 1rem;
        line-height: 1.75;
      }

      .sccc-sponsor-single-cta__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.85rem;
        padding-top: 0.35rem;
      }

      .sccc-sponsor-single-cta.is-align-center .sccc-sponsor-single-cta__actions {
        justify-content: center;
      }

      .sccc-sponsor-single-cta__actions .sccc-sponsor-single__button,
      .sccc-sponsor-single-cta__actions .sccc-sponsor-single__button:visited {
        border: 1px solid color-mix(in oklab, var(--sccc-ss-primary) 28%, var(--sccc-ss-border));
        background:
          linear-gradient(180deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.02)),
          color-mix(in oklab, var(--sccc-ss-surface-strong) 92%, transparent);
        color: var(--sccc-ss-heading);
        box-shadow:
          0 0 0 1px color-mix(in oklab, var(--sccc-ss-primary) 6%, transparent),
          0 0 26px color-mix(in oklab, var(--sccc-ss-primary) 16%, transparent),
          inset 0 1px 0 rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px);
      }

      .sccc-sponsor-single-cta__actions .sccc-sponsor-single__button:hover,
      .sccc-sponsor-single-cta__actions .sccc-sponsor-single__button:focus-visible {
        border-color: color-mix(in oklab, var(--sccc-ss-primary) 44%, var(--sccc-ss-border));
        color: var(--sccc-ss-heading);
        box-shadow:
          0 0 0 1px color-mix(in oklab, var(--sccc-ss-primary) 12%, transparent),
          0 0 34px color-mix(in oklab, var(--sccc-ss-primary) 24%, transparent),
          inset 0 1px 0 rgba(255, 255, 255, 0.16);
      }

      .sccc-sponsor-single-cta__actions .sccc-sponsor-single__button--primary {
        box-shadow:
          0 0 0 1px color-mix(in oklab, var(--sccc-ss-primary) 14%, transparent),
          0 0 38px color-mix(in oklab, var(--sccc-ss-primary) 28%, transparent),
          inset 0 1px 0 rgba(255, 255, 255, 0.16);
      }
    </style>
  @endonce

  <section class="sccc-sponsor-single-cta {{ $ctaAlignment === 'center' ? 'is-align-center' : 'is-align-left' }}" aria-label="Sponsor page call to action">
    <div class="sccc-sponsor-single-cta__inner">
      @if ($ctaEyebrow !== '')
        <p class="sccc-sponsor-single-cta__eyebrow">{{ $ctaEyebrow }}</p>
      @endif

      @if ($ctaHeading !== '')
        <h2 class="sccc-sponsor-single-cta__heading">{{ $ctaHeading }}</h2>
      @endif

      @if ($ctaBody !== '')
        <p class="sccc-sponsor-single-cta__body">{!! nl2br(e($ctaBody)) !!}</p>
      @endif

      @if ($hasPrimaryButton || $hasSecondaryButton)
        <div class="sccc-sponsor-single-cta__actions">
          @if ($hasPrimaryButton)
            <a
              class="sccc-sponsor-single__button sccc-sponsor-single__button--primary"
              href="{{ esc_url($primaryUrl) }}"
              @if ($primaryNewTab)
                target="_blank"
                rel="noopener noreferrer"
              @endif
            >
              <span>{{ $primaryLabel }}</span>
            </a>
          @endif

          @if ($hasSecondaryButton)
            <a
              class="sccc-sponsor-single__button sccc-sponsor-single__button--ghost"
              href="{{ esc_url($secondaryUrl) }}"
              @if ($secondaryNewTab)
                target="_blank"
                rel="noopener noreferrer"
              @endif
            >
              <span>{{ $secondaryLabel }}</span>
            </a>
          @endif
        </div>
      @endif
    </div>
  </section>
@endif