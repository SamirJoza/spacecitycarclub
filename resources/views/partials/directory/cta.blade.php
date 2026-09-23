{{--
  File path + filename: resources/views/partials/directory/cta.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render the global “Own a Business? Get Listed!” CTA section below the
    member business directory.

  Why this file exists:
  - The CTA is site-managed rather than page-authored, so it belongs in a
    reusable partial fed by Theme Settings options.
  - Keeping it separate from the listing partial avoids mixing directory query
    logic with global marketing/configuration content.
  - The section is only included by the business directory template, so all
    styles are scoped to the directory template root and cannot bleed into
    unrelated pages.

  Data source:
  - All values are loaded from the Theme Settings options page with
    `get_field('field_name', 'option')`.

  Notes:
  - The section renders nothing when the master toggle is off.
  - A missing button URL hides that button automatically.
  - The icon uses Material Symbols by name and falls back gracefully if that
    icon font is already available in the theme.
  - The CTA now renders as a full-width band so it visually separates itself
    from the directory container above.
  - The buttons intentionally reuse the same neon bordered button system used
    in the directory listing so the page feels consistent.
  - PMPro access is checked here because this partial can be included separately
    from the listing partial and should not render on restricted views.
--}}

@php
  /**
   * Gate the CTA with the same PMPro page access used by the directory listing.
   *
   * Why:
   * The CTA is not sensitive like the business cards, but rendering it below a
   * restricted PMPro message makes the page feel broken and scrollable. Keeping
   * it hidden for non-members lets the PMPro message behave like a true modal.
   *
   * Safe fallback:
   * If PMPro is unavailable for any reason, the CTA keeps using its normal ACF
   * settings instead of causing a fatal error during local development.
   */
  $scccDirectoryHasMembershipAccess = true;
  $scccDirectoryPostId = get_queried_object_id();

  if (function_exists('pmpro_has_membership_access') && $scccDirectoryPostId > 0) {
      $scccDirectoryHasMembershipAccess = (bool) pmpro_has_membership_access($scccDirectoryPostId);
  }
@endphp

@if ($scccDirectoryHasMembershipAccess)
  @php
    $ctaEnabled = (bool) get_field('business_directory_cta_enabled', 'option');

    $ctaIcon = trim((string) (get_field('business_directory_cta_icon', 'option') ?: 'storefront'));
    $ctaHeading = trim((string) (get_field('business_directory_cta_heading', 'option') ?: 'Own a Business? Get Listed!'));
    $ctaBody = (string) (get_field('business_directory_cta_body', 'option') ?: '');
    $ctaPrimaryLabel = trim((string) (get_field('business_directory_cta_primary_label', 'option') ?: 'Edit Profile'));
    $ctaPrimaryUrl = trim((string) (get_field('business_directory_cta_primary_url', 'option') ?: ''));
    $ctaSecondaryLabel = trim((string) (get_field('business_directory_cta_secondary_label', 'option') ?: 'Contact Support'));
    $ctaSecondaryUrl = trim((string) (get_field('business_directory_cta_secondary_url', 'option') ?: ''));

    $hasPrimary = $ctaPrimaryUrl !== '';
    $hasSecondary = $ctaSecondaryUrl !== '';
  @endphp

  @if ($ctaEnabled && ($ctaHeading !== '' || $ctaBody !== '' || $hasPrimary || $hasSecondary))
  <section class="sccc-business-directory-cta" aria-labelledby="sccc-business-directory-cta-heading">
    <div class="sccc-business-directory-cta__inner">
      <div class="sccc-business-directory-cta__content">
        @if ($ctaIcon !== '')
          <div class="sccc-business-directory-cta__icon-wrap" aria-hidden="true">
            <span class="material-symbols-outlined sccc-business-directory-cta__icon">{{ esc_html($ctaIcon) }}</span>
          </div>
        @endif

        @if ($ctaHeading !== '')
          <h2 id="sccc-business-directory-cta-heading" class="sccc-business-directory-cta__heading">
            {{ esc_html($ctaHeading) }}
          </h2>
        @endif

        @if ($ctaBody !== '')
          <div class="sccc-business-directory-cta__body">{!! nl2br(e($ctaBody)) !!}</div>
        @endif

        @if ($hasPrimary || $hasSecondary)
          <div class="sccc-business-directory-cta__actions">
            @if ($hasPrimary)
              <a
                class="sccc-business-directory__neo-button sccc-business-directory__neo-button--primary"
                href="{{ esc_url($ctaPrimaryUrl) }}"
              >
                <span class="sccc-business-directory__neo-button-face">
                  <span class="sccc-business-directory__neo-button-label">{{ esc_html($ctaPrimaryLabel) }}</span>
                </span>
              </a>
            @endif

            @if ($hasSecondary)
              <a
                class="sccc-business-directory__neo-button sccc-business-directory__neo-button--ghost"
                href="{{ esc_url($ctaSecondaryUrl) }}"
              >
                <span class="sccc-business-directory__neo-button-face">
                  <span class="sccc-business-directory__neo-button-label">{{ esc_html($ctaSecondaryLabel) }}</span>
                </span>
              </a>
            @endif
          </div>
        @endif
      </div>
    </div>

    <style>
      /* ----------------------------------------------------------------------
         Full-width CTA band
         - This section intentionally breaks out of narrower inner containers so
           it can read as its own horizontal band below the directory listing.
         ---------------------------------------------------------------------- */
      .sccc-business-directory-template .sccc-business-directory-cta {
        position: relative;
        left: 50%;
        width: 100vw;
        margin-left: -50vw;
        margin-right: -50vw;
        margin-top: clamp(2rem, 4vw, 3rem);
        padding: clamp(3rem, 6vw, 4.5rem) 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        background:
          radial-gradient(circle at 18% 18%, rgba(67, 190, 255, 0.08), transparent 18%),
          radial-gradient(circle at 82% 20%, rgba(174, 113, 255, 0.08), transparent 18%),
          linear-gradient(180deg, rgba(7, 17, 34, 0.92) 0%, rgba(5, 13, 28, 0.98) 100%);
        overflow: hidden;
      }

      html[data-theme="light"] .sccc-business-directory-template .sccc-business-directory-cta {
        border-top-color: rgba(26, 39, 68, 0.08);
        border-bottom-color: rgba(26, 39, 68, 0.06);
        background:
          radial-gradient(circle at 18% 18%, rgba(67, 190, 255, 0.10), transparent 18%),
          radial-gradient(circle at 82% 20%, rgba(174, 113, 255, 0.10), transparent 18%),
          linear-gradient(180deg, rgba(236, 242, 251, 0.96) 0%, rgba(229, 236, 247, 0.98) 100%);
      }

      .sccc-business-directory-template .sccc-business-directory-cta__inner {
        width: min(100% - 2rem, 1280px);
        margin-inline: auto;
      }

      /* ----------------------------------------------------------------------
         Content wrapper
         - Kept centered and slightly narrower for readability, while the outer
           band remains full-width.
         ---------------------------------------------------------------------- */
      .sccc-business-directory-template .sccc-business-directory-cta__content {
        max-width: 56rem;
        margin: 0 auto;
        text-align: center;
      }

      .sccc-business-directory-template .sccc-business-directory-cta__icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 4.75rem;
        height: 4.75rem;
        margin: 0 auto 1.15rem;
        border-radius: 999px;
        border: 1px solid rgba(113, 215, 255, 0.14);
        background:
          radial-gradient(circle at 35% 35%, rgba(67, 190, 255, 0.16), transparent 55%),
          rgba(53, 70, 129, 0.24);
        color: #8cc8ff;
        box-shadow:
          0 14px 36px rgba(67, 190, 255, 0.10),
          inset 0 1px 0 rgba(255, 255, 255, 0.06);
      }

      html[data-theme="light"] .sccc-business-directory-template .sccc-business-directory-cta__icon-wrap {
        border-color: rgba(67, 190, 255, 0.18);
        background:
          radial-gradient(circle at 35% 35%, rgba(67, 190, 255, 0.14), transparent 55%),
          rgba(46, 87, 255, 0.08);
        color: #2e57ff;
        box-shadow:
          0 14px 30px rgba(46, 87, 255, 0.08),
          inset 0 1px 0 rgba(255, 255, 255, 0.52);
      }

      .sccc-business-directory-template .sccc-business-directory-cta__icon {
        font-size: 2.05rem;
        line-height: 1;
      }

      .sccc-business-directory-template .sccc-business-directory-cta__heading {
        margin: 0 0 0.85rem;
        font-family: var(--font-headline, inherit);
        font-size: clamp(2rem, 4vw, 3.15rem);
        font-weight: 800;
        line-height: 1.04;
        letter-spacing: -0.02em;
        color: #f8faff;
      }

      html[data-theme="light"] .sccc-business-directory-template .sccc-business-directory-cta__heading {
        color: #1c2433;
      }

      .sccc-business-directory-template .sccc-business-directory-cta__body {
        max-width: 44rem;
        margin: 0 auto;
        font-size: clamp(1rem, 1.35vw, 1.16rem);
        line-height: 1.74;
        color: #b0b7c7;
      }

      html[data-theme="light"] .sccc-business-directory-template .sccc-business-directory-cta__body {
        color: #5f6c82;
      }

      .sccc-business-directory-template .sccc-business-directory-cta__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.85rem;
        margin-top: 1.6rem;
      }

      /* ----------------------------------------------------------------------
         Button tuning
         - Reuses the existing directory neon button system, but adds a little
           more prominence inside the CTA so the actions read clearly against
           the full-width background band.
         ---------------------------------------------------------------------- */
      .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button {
        box-shadow:
          0 0 0 1px rgba(113, 215, 255, 0.18),
          0 14px 34px rgba(67, 190, 255, 0.12),
          0 10px 24px rgba(174, 113, 255, 0.10);
      }

      .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button::after {
        opacity: 0.24;
      }

      .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button:hover {
        box-shadow:
          0 0 0 1px rgba(113, 215, 255, 0.24),
          0 18px 42px rgba(67, 190, 255, 0.16),
          0 14px 28px rgba(174, 113, 255, 0.14);
      }

      .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button:hover::after {
        opacity: 0.34;
      }

      .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button-face {
        min-height: 2.45rem;
        padding-inline: 1.15rem;
      }

      html[data-theme="light"] .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button-face {
        background: rgba(255, 255, 255, 0.84);
      }

      html[data-theme="light"] .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button:hover .sccc-business-directory__neo-button-face {
        background: rgba(255, 255, 255, 0.94);
      }

      @media (max-width: 767px) {
        .sccc-business-directory-template .sccc-business-directory-cta {
          padding: 2.5rem 0;
        }

        .sccc-business-directory-template .sccc-business-directory-cta__actions {
          flex-direction: column;
          align-items: stretch;
        }

        .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button {
          width: 100%;
        }

        .sccc-business-directory-template .sccc-business-directory-cta__actions .sccc-business-directory__neo-button-face {
          width: 100%;
        }
      }
    </style>
  </section>
  @endif
@endif