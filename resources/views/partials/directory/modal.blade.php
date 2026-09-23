{{--
  File path + filename: resources/views/partials/directory/modal.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render one reusable business-details dialog for the directory page.
  - Keep the modal CSS and JavaScript local to this Blade partial so neither is
    loaded globally on unrelated pages.

  Why this file exists:
  - Directory cards intentionally clamp their descriptions to preserve a clean,
    consistent grid.
  - The modal exposes the complete business description, logo, member owner, and
    any available website, phone, or email information without duplicating one
    dialog for every card.

  Phone formatting:
  - Every non-digit character is removed from the stored phone value.
  - Ten-digit numbers display as `(xxx) xxx-xxxx`.
  - Eleven-digit U.S. numbers beginning with `1` display as
    `+1 (xxx) xxx-xxxx`.
  - Other lengths remain digits-only so no number is silently discarded.

  Security and behavior notes:
  - Business data is encoded once as JSON with HTML-sensitive characters escaped.
  - JavaScript inserts all dynamic text with `textContent`, never `innerHTML`.
  - URLs and email addresses are sanitized before entering the JSON payload.
  - The native `<dialog>` element provides modal focus containment and Escape-key
    behavior through `showModal()` and `close()`.
  - The script is rendered only when this PMPro-protected directory is rendered.
--}}

@php
  /**
   * Build a minimal, sanitized payload for the reusable dialog.
   *
   * The regular card data remains unchanged. This payload contains only the
   * values the dialog needs, keyed by the stable WordPress user ID already added
   * to each normalized directory card.
   */
  $directoryModalBusinesses = [];

  foreach (($directoryCards ?? []) as $modalCard) {
      $modalBusinessId = absint($modalCard['business_id'] ?? 0);

      if ($modalBusinessId <= 0) {
          continue;
      }

      $modalBusinessName = trim((string) ($modalCard['business_name'] ?? ''));
      $modalWebsite = esc_url_raw((string) ($modalCard['business_website'] ?? ''));
      $modalWebsiteHost = $modalWebsite !== ''
          ? wp_parse_url($modalWebsite, PHP_URL_HOST)
          : '';
      $modalWebsiteLabel = is_string($modalWebsiteHost) && $modalWebsiteHost !== ''
          ? preg_replace('/^www\./i', '', $modalWebsiteHost)
          : $modalWebsite;

      $modalPhoneDigits = preg_replace(
          '/\D+/',
          '',
          (string) ($modalCard['business_phone'] ?? '')
      );
      $modalPhoneDigits = is_string($modalPhoneDigits) ? $modalPhoneDigits : '';
      $modalPhoneDisplay = $modalPhoneDigits;
      $modalPhoneHref = $modalPhoneDigits;

      if (strlen($modalPhoneDigits) === 10) {
          $modalPhoneDisplay = sprintf(
              '(%s) %s-%s',
              substr($modalPhoneDigits, 0, 3),
              substr($modalPhoneDigits, 3, 3),
              substr($modalPhoneDigits, 6, 4)
          );
      } elseif (strlen($modalPhoneDigits) === 11 && substr($modalPhoneDigits, 0, 1) === '1') {
          $modalPhoneDisplay = sprintf(
              '+1 (%s) %s-%s',
              substr($modalPhoneDigits, 1, 3),
              substr($modalPhoneDigits, 4, 3),
              substr($modalPhoneDigits, 7, 4)
          );
          $modalPhoneHref = '+' . $modalPhoneDigits;
      }

      $ownerName = trim((string) ($modalCard['owner_name'] ?? ''));

      $directoryModalBusinesses[(string) $modalBusinessId] = [
          'name' => $modalBusinessName,
          'description' => trim((string) ($modalCard['business_description'] ?? '')),
          'category' => trim((string) ($modalCard['primary_category_label'] ?? '')),
          'logoUrl' => esc_url_raw((string) ($modalCard['business_logo_url'] ?? '')),
          'logoAlt' => trim((string) ($modalCard['business_logo_alt'] ?? '')),
          'logoFallback' => $modalBusinessName !== ''
              ? (function_exists('mb_substr') ? mb_substr($modalBusinessName, 0, 1) : substr($modalBusinessName, 0, 1))
              : '',
          'ownerName' => $ownerName,
          'ownerAvatarUrl' => esc_url_raw((string) ($modalCard['owner_avatar_url'] ?? '')),
          'ownerAvatarAlt' => trim((string) ($modalCard['owner_avatar_alt'] ?? $ownerName)),
          'ownerFallback' => $ownerName !== ''
              ? (function_exists('mb_substr') ? mb_substr($ownerName, 0, 1) : substr($ownerName, 0, 1))
              : '',
          'website' => $modalWebsite,
          'websiteLabel' => is_string($modalWebsiteLabel) ? $modalWebsiteLabel : $modalWebsite,
          'phoneDisplay' => $modalPhoneDisplay,
          'phoneHref' => $modalPhoneHref,
          'email' => sanitize_email((string) ($modalCard['business_email'] ?? '')),
      ];
  }

  $directoryModalJson = wp_json_encode(
      $directoryModalBusinesses,
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  );

  if (!is_string($directoryModalJson)) {
      $directoryModalJson = '{}';
  }
@endphp

<dialog
  id="sccc-business-directory-modal"
  class="sccc-business-directory__modal"
  aria-labelledby="sccc-business-directory-modal-title"
  aria-describedby="sccc-business-directory-modal-description"
  data-directory-modal
>
  <div class="sccc-business-directory__modal-shell">
    <div class="sccc-business-directory__modal-accent" aria-hidden="true"></div>
    <div class="sccc-business-directory__modal-orbit" aria-hidden="true"></div>

    <button
      class="sccc-business-directory__modal-close"
      type="button"
      aria-label="{{ esc_attr(__('Close business details', 'sage')) }}"
      data-directory-modal-close
      autofocus
    >
      <span aria-hidden="true">×</span>
    </button>

    <header class="sccc-business-directory__modal-header">
      <div class="sccc-business-directory__modal-logo" aria-hidden="true">
        <img
          src=""
          alt=""
          loading="lazy"
          hidden
          data-directory-modal-logo
        >

        <div
          class="sccc-business-directory__modal-logo-fallback"
          data-directory-modal-logo-fallback
        ></div>
      </div>

      <div class="sccc-business-directory__modal-heading">
        <span class="sccc-business-directory__modal-eyebrow">
          {{ __('Member Business', 'sage') }}
        </span>

        <h2
          id="sccc-business-directory-modal-title"
          class="sccc-business-directory__modal-title"
          data-directory-modal-title
        ></h2>

        <span
          class="sccc-business-directory__category sccc-business-directory__modal-category"
          hidden
          data-directory-modal-category
        ></span>
      </div>
    </header>

    <div class="sccc-business-directory__modal-layout">
      <main class="sccc-business-directory__modal-main">
        <section
          class="sccc-business-directory__modal-panel sccc-business-directory__modal-about"
          data-directory-modal-description-section
        >
          <div class="sccc-business-directory__modal-section-heading">
            <span class="sccc-business-directory__modal-section-kicker">
              {{ __('About', 'sage') }}
            </span>

            <h3 class="sccc-business-directory__modal-section-title">
              {{ __('About the Business', 'sage') }}
            </h3>
          </div>

          <p
            id="sccc-business-directory-modal-description"
            class="sccc-business-directory__modal-description"
            data-directory-modal-description
          ></p>
        </section>
      </main>

      <aside class="sccc-business-directory__modal-sidebar">
        <section data-directory-modal-owner-section>
          <h3 class="sccc-business-directory__modal-sidebar-title">
            {{ __('Business Owner', 'sage') }}
          </h3>

          <div class="sccc-business-directory__owner sccc-business-directory__modal-owner-card">
            <div class="sccc-business-directory__owner-avatar">
              <img
                class="sccc-business-directory__owner-avatar-image"
                src=""
                alt=""
                loading="lazy"
                hidden
                data-directory-modal-owner-avatar
              >

              <div
                class="sccc-business-directory__modal-owner-fallback"
                hidden
                data-directory-modal-owner-fallback
              ></div>
            </div>

            <div>
              <span class="sccc-business-directory__owner-label">
                {{ __('Member Owner', 'sage') }}
              </span>

              <span
                class="sccc-business-directory__owner-name"
                data-directory-modal-owner-name
              ></span>
            </div>
          </div>
        </section>

        <section
          class="sccc-business-directory__modal-contact"
          hidden
          data-directory-modal-contact-section
        >
          <h3 class="sccc-business-directory__modal-sidebar-title">
            {{ __('Contact Information', 'sage') }}
          </h3>

          <div class="sccc-business-directory__modal-contact-list">
            <a
              class="sccc-business-directory__modal-contact-card"
              href=""
              target="_blank"
              rel="noopener noreferrer"
              hidden
              data-directory-modal-website-row
            >
              <span class="sccc-business-directory__modal-contact-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <circle cx="12" cy="12" r="9"></circle>
                  <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>
                </svg>
              </span>

              <span class="sccc-business-directory__modal-contact-copy">
                <span class="sccc-business-directory__modal-contact-label">
                  {{ __('Website', 'sage') }}
                </span>

                <span
                  class="sccc-business-directory__modal-contact-value"
                  data-directory-modal-website-value
                ></span>
              </span>

              <span class="sccc-business-directory__modal-contact-arrow" aria-hidden="true">↗</span>
            </a>

            <a
              class="sccc-business-directory__modal-contact-card"
              href=""
              hidden
              data-directory-modal-phone-row
            >
              <span class="sccc-business-directory__modal-contact-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M7.2 3.8 9.7 8l-2 2a15.5 15.5 0 0 0 6.3 6.3l2-2 4.2 2.5-.8 3.4c-.2.8-.9 1.3-1.7 1.3C9.3 21.5 2.5 14.7 2.5 6.3c0-.8.5-1.5 1.3-1.7l3.4-.8Z"></path>
                </svg>
              </span>

              <span class="sccc-business-directory__modal-contact-copy">
                <span class="sccc-business-directory__modal-contact-label">
                  {{ __('Phone', 'sage') }}
                </span>

                <span
                  class="sccc-business-directory__modal-contact-value"
                  data-directory-modal-phone-value
                ></span>
              </span>

              <span class="sccc-business-directory__modal-contact-arrow" aria-hidden="true">›</span>
            </a>

            <a
              class="sccc-business-directory__modal-contact-card"
              href=""
              hidden
              data-directory-modal-email-row
            >
              <span class="sccc-business-directory__modal-contact-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                  <path d="m4 7 8 6 8-6"></path>
                </svg>
              </span>

              <span class="sccc-business-directory__modal-contact-copy">
                <span class="sccc-business-directory__modal-contact-label">
                  {{ __('Email', 'sage') }}
                </span>

                <span
                  class="sccc-business-directory__modal-contact-value"
                  data-directory-modal-email-value
                ></span>
              </span>

              <span class="sccc-business-directory__modal-contact-arrow" aria-hidden="true">›</span>
            </a>
          </div>
        </section>
      </aside>
    </div>
  </div>
</dialog>

<script type="application/json" data-directory-modal-data>{!! $directoryModalJson !!}</script>

<style>
  /*
   * The details trigger is the only action remaining on each card.
   */
  .sccc-business-directory-template .sccc-business-directory__details-action,
  .sccc-business-directory-template .sccc-business-directory__details-action .sccc-business-directory__neo-button {
    width: 100%;
  }

  /*
   * Prevent the page from scrolling behind an open dialog.
   */
  html.sccc-directory-modal-open,
  body.sccc-directory-modal-open {
    overflow: hidden;
  }

  /*
   * Main dialog window.
   *
   * The dialog inherits the existing directory design tokens, so it follows the
   * site's light and dark directory themes without introducing new global tokens.
   */
  .sccc-business-directory-template .sccc-business-directory__modal {
    width: min(calc(100% - 2rem), 55rem);
    max-width: 55rem;
    max-height: min(92svh, 58rem);
    margin: auto;
    padding: 0;
    border: 1px solid color-mix(
      in srgb,
      var(--sccc-directory-primary) 28%,
      var(--sccc-directory-border)
    );
    border-radius: 1.75rem;
    background: var(--sccc-directory-surface);
    color: var(--sccc-directory-text);
    box-shadow:
      0 42px 130px rgba(0, 0, 0, 0.46),
      0 0 0 1px color-mix(
        in srgb,
        var(--sccc-directory-primary) 9%,
        transparent
      ),
      0 0 70px color-mix(
        in srgb,
        var(--sccc-directory-primary) 12%,
        transparent
      );
    overflow: hidden;
  }

  .sccc-business-directory-template .sccc-business-directory__modal[open] {
    animation: sccc-directory-modal-enter 220ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
  }

  .sccc-business-directory__modal::backdrop {
    background:
      radial-gradient(circle at 25% 15%, rgba(67, 190, 255, 0.14), transparent 32%),
      radial-gradient(circle at 78% 18%, rgba(174, 113, 255, 0.12), transparent 34%),
      rgba(3, 8, 18, 0.78);
    backdrop-filter: blur(14px) saturate(120%);
    -webkit-backdrop-filter: blur(14px) saturate(120%);
  }

  .sccc-business-directory-template .sccc-business-directory__modal-shell {
    position: relative;
    max-height: min(92svh, 58rem);
    overflow-y: auto;
    overscroll-behavior: contain;
    background:
      radial-gradient(
        circle at 92% 2%,
        color-mix(in srgb, var(--sccc-directory-primary) 16%, transparent),
        transparent 30%
      ),
      linear-gradient(
        180deg,
        color-mix(in srgb, var(--sccc-directory-surface-alt) 58%, transparent) 0%,
        var(--sccc-directory-surface) 42%
      );
  }

  .sccc-business-directory-template .sccc-business-directory__modal-accent {
    position: absolute;
    inset: 0 0 auto;
    z-index: 2;
    height: 3px;
    background: var(--sccc-header-underline-gradient);
    box-shadow:
      0 0 16px rgba(67, 190, 255, 0.35),
      0 0 24px rgba(174, 113, 255, 0.22);
    pointer-events: none;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-orbit {
    position: absolute;
    inset-block-start: -7rem;
    inset-inline-end: -5rem;
    width: 17rem;
    height: 17rem;
    border: 1px solid color-mix(
      in srgb,
      var(--sccc-directory-primary) 18%,
      transparent
    );
    border-radius: 999px;
    box-shadow:
      0 0 0 2.5rem color-mix(
        in srgb,
        var(--sccc-directory-primary) 4%,
        transparent
      ),
      0 0 0 5rem color-mix(
        in srgb,
        var(--sccc-directory-primary) 2%,
        transparent
      );
    pointer-events: none;
  }

  /*
   * Close control.
   */
  .sccc-business-directory-template .sccc-business-directory__modal-close {
    position: absolute;
    inset-block-start: 1rem;
    inset-inline-end: 1rem;
    z-index: 4;
    display: grid;
    place-items: center;
    width: 2.8rem;
    height: 2.8rem;
    padding: 0;
    border: 1px solid color-mix(
      in srgb,
      var(--sccc-directory-primary) 20%,
      var(--sccc-directory-border)
    );
    border-radius: 999px;
    background: color-mix(
      in srgb,
      var(--sccc-directory-surface) 82%,
      transparent
    );
    color: var(--sccc-directory-text);
    font-size: 1.7rem;
    line-height: 1;
    cursor: pointer;
    box-shadow:
      0 10px 30px rgba(0, 0, 0, 0.16),
      inset 0 1px 0 rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    transition:
      transform 180ms ease,
      border-color 180ms ease,
      color 180ms ease,
      box-shadow 180ms ease;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-close:hover {
    transform: translateY(-1px) rotate(4deg);
    border-color: color-mix(
      in srgb,
      var(--sccc-directory-primary) 48%,
      var(--sccc-directory-border)
    );
    color: var(--sccc-directory-primary);
    box-shadow:
      0 14px 34px rgba(0, 0, 0, 0.2),
      0 0 22px color-mix(
        in srgb,
        var(--sccc-directory-primary) 15%,
        transparent
      );
  }

  .sccc-business-directory-template .sccc-business-directory__modal-close:focus-visible {
    outline: 3px solid color-mix(
      in srgb,
      var(--sccc-directory-primary) 30%,
      transparent
    );
    outline-offset: 3px;
  }

  /*
   * Business identity header.
   */
  .sccc-business-directory-template .sccc-business-directory__modal-header {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1.35rem;
    min-height: 11rem;
    padding: 2rem 5rem 2rem 2rem;
    border-bottom: 1px solid var(--sccc-directory-border);
    background:
      linear-gradient(
        120deg,
        color-mix(in srgb, var(--sccc-directory-primary) 12%, transparent),
        transparent 44%
      ),
      color-mix(
        in srgb,
        var(--sccc-directory-surface-alt) 64%,
        transparent
      );
  }

  .sccc-business-directory-template .sccc-business-directory__modal-logo {
    position: relative;
    flex: 0 0 7rem;
    width: 7rem;
    height: 7rem;
    padding: 0.4rem;
    border: 1px solid color-mix(
      in srgb,
      var(--sccc-directory-primary) 22%,
      var(--sccc-directory-border)
    );
    border-radius: 1.6rem;
    background: color-mix(
      in srgb,
      var(--sccc-directory-surface) 88%,
      transparent
    );
    box-shadow:
      0 18px 45px rgba(0, 0, 0, 0.17),
      0 0 26px color-mix(
        in srgb,
        var(--sccc-directory-primary) 12%,
        transparent
      );
    overflow: hidden;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-logo::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
    pointer-events: none;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-logo img {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 1.2rem;
    object-fit: contain;
    background: var(--sccc-directory-surface);
  }

  .sccc-business-directory-template .sccc-business-directory__modal-logo-fallback {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
    border-radius: 1.2rem;
    background:
      radial-gradient(
        circle at 28% 22%,
        color-mix(in srgb, var(--sccc-directory-primary) 26%, transparent),
        transparent 44%
      ),
      color-mix(
        in srgb,
        var(--sccc-directory-primary) 10%,
        var(--sccc-directory-surface-alt)
      );
    color: var(--sccc-directory-primary);
    font-family: var(--font-headline, inherit);
    font-size: 2.65rem;
    font-weight: 800;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-logo-fallback[hidden],
  .sccc-business-directory-template .sccc-business-directory__modal [hidden] {
    display: none;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-heading {
    min-width: 0;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-eyebrow {
    display: block;
    margin-bottom: 0.45rem;
    color: var(--sccc-directory-primary);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-title {
    margin: 0 0 0.8rem;
    color: var(--sccc-directory-text);
    font-family: var(--font-headline, inherit);
    font-size: clamp(1.85rem, 4vw, 2.75rem);
    font-weight: 800;
    line-height: 1.08;
    letter-spacing: -0.02em;
    overflow-wrap: anywhere;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-category {
    box-shadow: 0 8px 22px color-mix(
      in srgb,
      var(--sccc-directory-primary) 10%,
      transparent
    );
  }

  /*
   * Main content layout.
   */
  .sccc-business-directory-template .sccc-business-directory__modal-layout {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(15rem, 18rem);
    gap: 1.5rem;
    padding: 1.75rem;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-main,
  .sccc-business-directory-template .sccc-business-directory__modal-sidebar {
    min-width: 0;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-sidebar {
    display: grid;
    align-content: start;
    gap: 1.35rem;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-panel {
    position: relative;
    min-height: 100%;
    padding: clamp(1.3rem, 3vw, 1.75rem);
    border: 1px solid var(--sccc-directory-border);
    border-radius: 1.35rem;
    background:
      linear-gradient(
        145deg,
        color-mix(in srgb, var(--sccc-directory-primary) 6%, transparent),
        transparent 42%
      ),
      color-mix(
        in srgb,
        var(--sccc-directory-surface-alt) 62%,
        transparent
      );
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.04),
      0 18px 45px rgba(0, 0, 0, 0.07);
    overflow: hidden;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-panel::before {
    content: "";
    position: absolute;
    inset: 1.4rem auto 1.4rem 0;
    width: 3px;
    border-radius: 999px;
    background: var(--sccc-header-underline-gradient);
    box-shadow: 0 0 16px color-mix(
      in srgb,
      var(--sccc-directory-primary) 28%,
      transparent
    );
  }

  .sccc-business-directory-template .sccc-business-directory__modal-section-heading {
    margin-bottom: 1rem;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-section-kicker {
    display: block;
    margin-bottom: 0.2rem;
    color: var(--sccc-directory-primary);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-section-title,
  .sccc-business-directory-template .sccc-business-directory__modal-sidebar-title {
    margin: 0;
    color: var(--sccc-directory-text);
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-description {
    margin: 0;
    color: var(--sccc-directory-text-muted);
    font-size: 1rem;
    line-height: 1.8;
    white-space: pre-line;
    overflow-wrap: anywhere;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-sidebar-title {
    margin-bottom: 0.65rem;
  }

  /*
   * Member owner block uses the same class structure and visual treatment as the
   * owner block on the regular business card.
   */
  .sccc-business-directory-template .sccc-business-directory__modal-owner-card {
    margin-top: 0;
    border: 1px solid var(--sccc-directory-border);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.04),
      0 14px 34px rgba(0, 0, 0, 0.07);
  }

  .sccc-business-directory-template .sccc-business-directory__modal-owner-fallback {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
    color: var(--sccc-directory-primary);
    background: color-mix(
      in srgb,
      var(--sccc-directory-primary) 10%,
      var(--sccc-directory-surface-alt)
    );
    font-weight: 800;
  }

  /*
   * Contact links are compact action cards rather than repeated buttons on the
   * directory card itself.
   */
  .sccc-business-directory-template .sccc-business-directory__modal-contact-list {
    display: grid;
    gap: 0.65rem;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-card {
    display: grid;
    grid-template-columns: 2.6rem minmax(0, 1fr) auto;
    gap: 0.75rem;
    align-items: center;
    min-height: 4.4rem;
    padding: 0.7rem 0.8rem;
    border: 1px solid var(--sccc-directory-border);
    border-radius: 1rem;
    background:
      linear-gradient(
        135deg,
        color-mix(in srgb, var(--sccc-directory-primary) 5%, transparent),
        transparent 52%
      ),
      color-mix(
        in srgb,
        var(--sccc-directory-surface-alt) 70%,
        transparent
      );
    color: var(--sccc-directory-text);
    text-decoration: none;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.035);
    transition:
      transform 180ms ease,
      border-color 180ms ease,
      background-color 180ms ease,
      box-shadow 180ms ease;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(
      in srgb,
      var(--sccc-directory-primary) 36%,
      var(--sccc-directory-border)
    );
    box-shadow:
      0 14px 30px color-mix(
        in srgb,
        var(--sccc-directory-primary) 10%,
        transparent
      ),
      inset 0 1px 0 rgba(255, 255, 255, 0.05);
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-card:focus-visible {
    outline: 3px solid color-mix(
      in srgb,
      var(--sccc-directory-primary) 26%,
      transparent
    );
    outline-offset: 3px;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-icon {
    display: grid;
    place-items: center;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.8rem;
    background: color-mix(
      in srgb,
      var(--sccc-directory-primary) 12%,
      var(--sccc-directory-surface)
    );
    color: var(--sccc-directory-primary);
    box-shadow: inset 0 0 0 1px color-mix(
      in srgb,
      var(--sccc-directory-primary) 13%,
      transparent
    );
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-icon svg {
    width: 1.25rem;
    height: 1.25rem;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-copy {
    min-width: 0;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-label {
    display: block;
    margin-bottom: 0.12rem;
    color: var(--sccc-directory-text-muted);
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-value {
    display: block;
    color: var(--sccc-directory-text);
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.3;
    overflow-wrap: anywhere;
  }

  .sccc-business-directory-template .sccc-business-directory__modal-contact-arrow {
    color: var(--sccc-directory-primary);
    font-size: 1.15rem;
    font-weight: 800;
  }

  @keyframes sccc-directory-modal-enter {
    from {
      opacity: 0;
      transform: translateY(1rem) scale(0.975);
    }

    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  @media (max-width: 760px) {
    .sccc-business-directory-template .sccc-business-directory__modal {
      width: min(calc(100% - 1rem), 55rem);
      max-height: calc(100svh - 1rem);
      border-radius: 1.35rem;
    }

    .sccc-business-directory-template .sccc-business-directory__modal-shell {
      max-height: calc(100svh - 1rem);
    }

    .sccc-business-directory-template .sccc-business-directory__modal-header {
      align-items: flex-start;
      min-height: 0;
      padding: 1.5rem 4.5rem 1.5rem 1.25rem;
    }

    .sccc-business-directory-template .sccc-business-directory__modal-logo {
      flex-basis: 5rem;
      width: 5rem;
      height: 5rem;
      border-radius: 1.2rem;
    }

    .sccc-business-directory-template .sccc-business-directory__modal-logo img,
    .sccc-business-directory-template .sccc-business-directory__modal-logo-fallback {
      border-radius: 0.9rem;
    }

    .sccc-business-directory-template .sccc-business-directory__modal-logo-fallback {
      font-size: 2rem;
    }

    .sccc-business-directory-template .sccc-business-directory__modal-layout {
      grid-template-columns: 1fr;
      padding: 1.25rem;
    }
  }

  @media (max-width: 480px) {
    .sccc-business-directory-template .sccc-business-directory__modal-header {
      flex-direction: column;
    }

    .sccc-business-directory-template .sccc-business-directory__modal-title {
      font-size: 1.75rem;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .sccc-business-directory-template .sccc-business-directory__modal[open] {
      animation: none;
    }

    .sccc-business-directory-template .sccc-business-directory__modal-close,
    .sccc-business-directory-template .sccc-business-directory__modal-contact-card {
      transition: none;
    }
  }
</style>

<script>
(() => {
  'use strict';

  /**
   * Scope all behavior to the directory instance containing this script.
   *
   * The partial is rendered only on the Business Directory page, so no global
   * asset or unrelated-page listener is created.
   */
  const currentScript = document.currentScript;
  const directoryRoot = currentScript
    ? currentScript.closest('.sccc-business-directory-template')
    : document.querySelector('.sccc-business-directory-template');

  if (!directoryRoot) {
    return;
  }

  const modal = directoryRoot.querySelector('[data-directory-modal]');
  const dataElement = directoryRoot.querySelector('[data-directory-modal-data]');

  if (!modal || !dataElement || typeof modal.showModal !== 'function') {
    return;
  }

  let businesses = {};
  let lastTrigger = null;

  try {
    businesses = JSON.parse(dataElement.textContent || '{}');
  } catch (error) {
    businesses = {};
  }

  /**
   * Cache the reusable modal elements once.
   */
  const title = modal.querySelector('[data-directory-modal-title]');
  const category = modal.querySelector('[data-directory-modal-category]');
  const logo = modal.querySelector('[data-directory-modal-logo]');
  const logoFallback = modal.querySelector('[data-directory-modal-logo-fallback]');
  const descriptionSection = modal.querySelector('[data-directory-modal-description-section]');
  const description = modal.querySelector('[data-directory-modal-description]');
  const ownerSection = modal.querySelector('[data-directory-modal-owner-section]');
  const ownerName = modal.querySelector('[data-directory-modal-owner-name]');
  const ownerAvatar = modal.querySelector('[data-directory-modal-owner-avatar]');
  const ownerFallback = modal.querySelector('[data-directory-modal-owner-fallback]');
  const contactSection = modal.querySelector('[data-directory-modal-contact-section]');
  const websiteRow = modal.querySelector('[data-directory-modal-website-row]');
  const websiteValue = modal.querySelector('[data-directory-modal-website-value]');
  const phoneRow = modal.querySelector('[data-directory-modal-phone-row]');
  const phoneValue = modal.querySelector('[data-directory-modal-phone-value]');
  const emailRow = modal.querySelector('[data-directory-modal-email-row]');
  const emailValue = modal.querySelector('[data-directory-modal-email-value]');
  const closeButton = modal.querySelector('[data-directory-modal-close]');

  /**
   * Toggle an optional contact card without leaving stale values from the
   * previously opened business.
   */
  const setContactCard = (row, valueElement, value, href) => {
    const hasValue = Boolean(value && href);

    row.hidden = !hasValue;
    valueElement.textContent = hasValue ? value : '';

    if (hasValue) {
      row.setAttribute('href', href);

      return true;
    }

    row.removeAttribute('href');

    return false;
  };

  /**
   * Populate the one reusable dialog from the sanitized JSON payload.
   */
  const populateModal = (business) => {
    const businessName = business.name || '';
    const businessDescription = business.description || '';
    const businessCategory = business.category || '';
    const memberOwnerName = business.ownerName || '';
    const website = business.website || '';
    const websiteLabel = business.websiteLabel || website;
    const phoneDisplay = business.phoneDisplay || '';
    const phoneHref = business.phoneHref || '';
    const email = business.email || '';

    title.textContent = businessName;

    category.textContent = businessCategory;
    category.hidden = !businessCategory;

    description.textContent = businessDescription;
    descriptionSection.hidden = !businessDescription;

    ownerName.textContent = memberOwnerName;
    ownerSection.hidden = !memberOwnerName;
    ownerFallback.textContent = business.ownerFallback || '';

    if (business.ownerAvatarUrl) {
      ownerAvatar.src = business.ownerAvatarUrl;
      ownerAvatar.alt = business.ownerAvatarAlt || memberOwnerName;
      ownerAvatar.hidden = false;
      ownerFallback.hidden = true;
    } else {
      ownerAvatar.removeAttribute('src');
      ownerAvatar.alt = '';
      ownerAvatar.hidden = true;
      ownerFallback.hidden = false;
    }

    logoFallback.textContent = business.logoFallback || '';

    if (business.logoUrl) {
      logo.src = business.logoUrl;
      logo.alt = business.logoAlt || `${businessName} logo`;
      logo.hidden = false;
      logoFallback.hidden = true;
    } else {
      logo.removeAttribute('src');
      logo.alt = '';
      logo.hidden = true;
      logoFallback.hidden = false;
    }

    const hasWebsite = setContactCard(
      websiteRow,
      websiteValue,
      websiteLabel,
      website
    );

    const hasPhone = setContactCard(
      phoneRow,
      phoneValue,
      phoneDisplay,
      phoneHref ? `tel:${phoneHref}` : ''
    );

    const hasEmail = setContactCard(
      emailRow,
      emailValue,
      email,
      email ? `mailto:${email}` : ''
    );

    contactSection.hidden = !(hasWebsite || hasPhone || hasEmail);
  };

  /**
   * Fall back to initials if a logo or avatar image fails to load.
   */
  logo.addEventListener('error', () => {
    logo.hidden = true;
    logoFallback.hidden = false;
  });

  ownerAvatar.addEventListener('error', () => {
    ownerAvatar.hidden = true;
    ownerFallback.hidden = false;
  });

  /**
   * Handle all details buttons through one delegated listener.
   */
  directoryRoot.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }

    const trigger = event.target.closest('[data-directory-modal-open]');

    if (!trigger || !directoryRoot.contains(trigger)) {
      return;
    }

    const businessId = String(trigger.dataset.businessId || '');
    const business = businesses[businessId];

    if (!business) {
      return;
    }

    lastTrigger = trigger;
    populateModal(business);

    document.documentElement.classList.add('sccc-directory-modal-open');
    document.body.classList.add('sccc-directory-modal-open');

    modal.showModal();
  });

  closeButton.addEventListener('click', () => {
    modal.close();
  });

  /**
   * Close when the visitor clicks the native dialog backdrop.
   */
  modal.addEventListener('click', (event) => {
    if (event.target !== modal) {
      return;
    }

    const bounds = modal.getBoundingClientRect();
    const clickedInside = (
      event.clientX >= bounds.left &&
      event.clientX <= bounds.right &&
      event.clientY >= bounds.top &&
      event.clientY <= bounds.bottom
    );

    if (!clickedInside) {
      modal.close();
    }
  });

  /**
   * Clean up after the close button, backdrop click, or Escape key.
   */
  modal.addEventListener('close', () => {
    document.documentElement.classList.remove('sccc-directory-modal-open');
    document.body.classList.remove('sccc-directory-modal-open');

    if (lastTrigger && document.contains(lastTrigger)) {
      lastTrigger.focus();
    }

    lastTrigger = null;
  });
})();
</script>