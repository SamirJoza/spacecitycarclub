{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/leadership-band.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the Leadership Band Gutenberg block.
| - Display round leadership photos in inline bands.
| - Open a modal with larger photo, name, position titles, and bio.
|
| Why this file exists:
| - The block class prepares the Leadership CPT data.
| - This Blade view handles the frontend markup, styling, visual themes, spacing,
|   text alignment, section width behavior, and lightweight modal behavior.
|
| Section width behavior:
| - No alignment selected:
|   - The entire section becomes container-width.
|   - The section background is contained to that width.
|   - The section receives rounded corners.
| - Full width selected:
|   - The entire section stretches edge-to-edge.
|   - The section has no rounded corners.
|   - The inner content remains readable with a max-width container.
|
| Editor behavior:
| - Gutenberg/admin previews still show the leadership card layout.
| - The modal HTML and modal JavaScript are intentionally not rendered in
|   wp-admin to prevent profile popups from appearing while editing pages/posts.
|
| Theme behavior:
| - Editors can select one of three themes:
|   1) Noir Glass
|   2) Electric Blue
|   3) Clean Chrome
| - Each theme defines its own section background and readable foreground colors.
| - Dark-mode overrides are included for themes that need adjusted contrast.
|
| Spacing behavior:
| - Padding and margin controls are applied to the outer section.
| - They do not apply to the inner content container.
|
| Data priority:
| - The image shown here is already resolved in PHP:
|   Leadership CPT override photo first, linked user avatar second.
| - The bio shown here is already resolved in PHP:
|   Leadership CPT bio override first, linked user bio second.
|
| Notes for future developers:
| - The modal is moved to <body> when opened so it centers against the real
|   browser viewport instead of being affected by block/container layout.
| - Because the modal is moved outside `.sccc-leadership-band`, the modal also
|   receives the same theme class directly.
| - The title/position line on the small cards intentionally wraps so all
|   positions remain visible when a person has multiple titles.
|--------------------------------------------------------------------------
--}}

@php
  $sectionId = sanitize_html_class('sccc-leadership-band-' . ($block->block->id ?? wp_unique_id()));

  $themeClass = sanitize_html_class('sccc-leadership-band--theme-' . ($theme ?? 'noir'));
  $textAlignClass = sanitize_html_class('sccc-leadership-band--text-' . ($textAlign ?? 'left'));
  $paddingTopClass = sanitize_html_class('sccc-lb-pt-' . ($paddingTop ?? 'default'));
  $paddingBottomClass = sanitize_html_class('sccc-lb-pb-' . ($paddingBottom ?? 'default'));
  $marginTopClass = sanitize_html_class('sccc-lb-mt-' . ($marginTop ?? 'none'));
  $marginBottomClass = sanitize_html_class('sccc-lb-mb-' . ($marginBottom ?? 'none'));

  $rootAttributes = $attributes
    ->class([
      'sccc-leadership-band',
      $themeClass,
      $textAlignClass,
      $paddingTopClass,
      $paddingBottomClass,
      $marginTopClass,
      $marginBottomClass,
      'sccc-leadership-band--grouped' => !empty($isGrouped),
    ])
    ->merge([
      'id' => $sectionId,
      'data-sccc-leadership-band' => true,
    ]);

  $hasIntro = trim((string) ($headline ?? '')) !== '' || trim((string) ($subtext ?? '')) !== '';

  /**
   * Frontend-only modal guard.
   *
   * Why:
   * - ACF blocks render real HTML previews inside Gutenberg.
   * - Depending on how the editor preview is requested, the render can come
   *   through wp-admin, an iframe, admin-ajax, or a JSON/REST request.
   * - The modal is a frontend interaction only, so we intentionally suppress
   *   both the modal markup and modal trigger attributes anywhere that looks
   *   like an editor/server-render preview.
   *
   * Notes:
   * - ACF exposes `$is_preview` in normal block templates.
   * - Some Sage/ACF Composer setups expose the same state as `$isPreview`.
   * - The extra REST/JSON checks keep this safe with modern iframe/server-side
   *   Gutenberg previews where `is_admin()` alone may not be enough.
   */
  $isAcfPreview = (isset($is_preview) && (bool) $is_preview)
    || (isset($isPreview) && (bool) $isPreview);

  $isJsonRequest = function_exists('wp_is_json_request') && wp_is_json_request();
  $isRestRequest = defined('REST_REQUEST') && REST_REQUEST;
  $isEditorLikeRequest = is_admin() || $isAcfPreview || $isJsonRequest || $isRestRequest;

  $shouldRenderModal = !$isEditorLikeRequest;
@endphp

@once
  <style>
    .sccc-leadership-band,
    .sccc-leadership-modal {
      --sccc-lb-section-bg:
        radial-gradient(52rem 28rem at 10% -10%, color-mix(in oklab, var(--color-primary-500, #2979ff) 18%, transparent), transparent 64%),
        radial-gradient(44rem 24rem at 95% 0%, color-mix(in oklab, var(--color-accent-500, #ff1744) 14%, transparent), transparent 68%),
        color-mix(in oklab, var(--color-surface, #ffffff) 92%, transparent);
      --sccc-lb-surface: var(--color-surface, #ffffff);
      --sccc-lb-surface-soft: color-mix(in oklab, var(--color-surface, #ffffff) 86%, transparent);
      --sccc-lb-text: var(--color-text, #111827);
      --sccc-lb-muted: var(--color-muted, #64748b);
      --sccc-lb-line: var(--color-line, rgba(15, 23, 42, 0.14));
      --sccc-lb-primary: var(--color-primary-500, #2979ff);
      --sccc-lb-accent: var(--color-accent-500, #ff1744);
      --sccc-lb-shadow: 0 18px 46px rgb(15 23 42 / 0.12);
      --sccc-lb-card-radius: 1.25rem;
      --sccc-lb-section-radius: 1.75rem;
      --sccc-lb-container-width: 76rem;
      --sccc-lb-full-inner-width: 86rem;
      --sccc-lb-padding-top: clamp(2.75rem, 5vw, 5.5rem);
      --sccc-lb-padding-bottom: clamp(2.75rem, 5vw, 5.5rem);
      --sccc-lb-margin-top: 0;
      --sccc-lb-margin-bottom: 0;

      color: var(--sccc-lb-text);
    }

    /**
     * Theme: Noir Glass
     *
     * Dark, high-contrast, cinematic.
     */
    .sccc-leadership-band--theme-noir {
      --sccc-lb-section-bg:
        radial-gradient(58rem 28rem at 10% -10%, color-mix(in oklab, var(--color-primary-500, #2979ff) 28%, transparent), transparent 62%),
        radial-gradient(46rem 25rem at 96% 0%, color-mix(in oklab, var(--color-accent-500, #ff1744) 24%, transparent), transparent 64%),
        linear-gradient(135deg, #08111f 0%, #0b1020 56%, #020617 100%);
      --sccc-lb-surface: #0f172a;
      --sccc-lb-surface-soft: rgb(15 23 42 / 0.72);
      --sccc-lb-text: #f8fafc;
      --sccc-lb-muted: #cbd5e1;
      --sccc-lb-line: rgb(255 255 255 / 0.13);
      --sccc-lb-shadow: 0 22px 60px rgb(0 0 0 / 0.42);
    }

    /**
     * Theme: Electric Blue
     *
     * Blue-forward energy with readable light/dark variants.
     */
    .sccc-leadership-band--theme-electric {
      --sccc-lb-section-bg:
        radial-gradient(48rem 25rem at 0% 0%, rgb(41 121 255 / 0.22), transparent 66%),
        radial-gradient(42rem 22rem at 100% 0%, rgb(255 23 68 / 0.13), transparent 66%),
        linear-gradient(135deg, #eff6ff 0%, #f8fafc 52%, #eef2ff 100%);
      --sccc-lb-surface: #ffffff;
      --sccc-lb-surface-soft: rgb(255 255 255 / 0.78);
      --sccc-lb-text: #0f172a;
      --sccc-lb-muted: #475569;
      --sccc-lb-line: rgb(15 23 42 / 0.14);
      --sccc-lb-shadow: 0 18px 46px rgb(15 23 42 / 0.13);
    }

    .dark .sccc-leadership-band--theme-electric,
    .dark .sccc-leadership-modal.sccc-leadership-band--theme-electric,
    [data-theme="dark"] .sccc-leadership-band--theme-electric,
    [data-theme="dark"] .sccc-leadership-modal.sccc-leadership-band--theme-electric {
      --sccc-lb-section-bg:
        radial-gradient(52rem 28rem at 0% 0%, rgb(41 121 255 / 0.32), transparent 66%),
        radial-gradient(42rem 22rem at 100% 0%, rgb(255 23 68 / 0.16), transparent 66%),
        linear-gradient(135deg, #06111f 0%, #081827 52%, #020617 100%);
      --sccc-lb-surface: #0f172a;
      --sccc-lb-surface-soft: rgb(15 23 42 / 0.72);
      --sccc-lb-text: #f8fafc;
      --sccc-lb-muted: #cbd5e1;
      --sccc-lb-line: rgb(255 255 255 / 0.13);
      --sccc-lb-shadow: 0 22px 60px rgb(0 0 0 / 0.42);
    }

    /**
     * Theme: Clean Chrome
     *
     * Neutral, clean, and easier to use on simple content pages.
     */
    .sccc-leadership-band--theme-chrome {
      --sccc-lb-section-bg:
        radial-gradient(44rem 24rem at 0% -10%, rgb(148 163 184 / 0.18), transparent 64%),
        radial-gradient(40rem 22rem at 100% 0%, rgb(41 121 255 / 0.10), transparent 64%),
        linear-gradient(135deg, #f8fafc 0%, #ffffff 55%, #f1f5f9 100%);
      --sccc-lb-surface: #ffffff;
      --sccc-lb-surface-soft: rgb(255 255 255 / 0.84);
      --sccc-lb-text: #111827;
      --sccc-lb-muted: #4b5563;
      --sccc-lb-line: rgb(15 23 42 / 0.12);
      --sccc-lb-shadow: 0 16px 38px rgb(15 23 42 / 0.10);
    }

    .dark .sccc-leadership-band--theme-chrome,
    .dark .sccc-leadership-modal.sccc-leadership-band--theme-chrome,
    [data-theme="dark"] .sccc-leadership-band--theme-chrome,
    [data-theme="dark"] .sccc-leadership-modal.sccc-leadership-band--theme-chrome {
      --sccc-lb-section-bg:
        radial-gradient(44rem 24rem at 0% -10%, rgb(148 163 184 / 0.12), transparent 64%),
        radial-gradient(40rem 22rem at 100% 0%, rgb(41 121 255 / 0.16), transparent 64%),
        linear-gradient(135deg, #090b10 0%, #101114 55%, #05070b 100%);
      --sccc-lb-surface: #111827;
      --sccc-lb-surface-soft: rgb(17 24 39 / 0.76);
      --sccc-lb-text: #f9fafb;
      --sccc-lb-muted: #cbd5e1;
      --sccc-lb-line: rgb(255 255 255 / 0.12);
      --sccc-lb-shadow: 0 22px 60px rgb(0 0 0 / 0.42);
    }

    /**
     * Spacing presets.
     *
     * These classes set variables on the outer section.
     */
    .sccc-lb-pt-none { --sccc-lb-padding-top: 0; }
    .sccc-lb-pt-sm { --sccc-lb-padding-top: 1.5rem; }
    .sccc-lb-pt-md { --sccc-lb-padding-top: 3rem; }
    .sccc-lb-pt-lg { --sccc-lb-padding-top: 4.5rem; }
    .sccc-lb-pt-xl { --sccc-lb-padding-top: 6.5rem; }

    .sccc-lb-pb-none { --sccc-lb-padding-bottom: 0; }
    .sccc-lb-pb-sm { --sccc-lb-padding-bottom: 1.5rem; }
    .sccc-lb-pb-md { --sccc-lb-padding-bottom: 3rem; }
    .sccc-lb-pb-lg { --sccc-lb-padding-bottom: 4.5rem; }
    .sccc-lb-pb-xl { --sccc-lb-padding-bottom: 6.5rem; }

    .sccc-lb-mt-none { --sccc-lb-margin-top: 0; }
    .sccc-lb-mt-sm { --sccc-lb-margin-top: 1rem; }
    .sccc-lb-mt-md { --sccc-lb-margin-top: 2rem; }
    .sccc-lb-mt-lg { --sccc-lb-margin-top: 3rem; }
    .sccc-lb-mt-xl { --sccc-lb-margin-top: 5rem; }

    .sccc-lb-mb-none { --sccc-lb-margin-bottom: 0; }
    .sccc-lb-mb-sm { --sccc-lb-margin-bottom: 1rem; }
    .sccc-lb-mb-md { --sccc-lb-margin-bottom: 2rem; }
    .sccc-lb-mb-lg { --sccc-lb-margin-bottom: 3rem; }
    .sccc-lb-mb-xl { --sccc-lb-margin-bottom: 5rem; }

    /**
     * Outer section.
     *
     * Important:
     * - The background is applied to the outer section.
     * - In default/no-alignment mode, the section itself is container-width.
     * - In full-width mode, the section itself stretches edge-to-edge.
     */
    .sccc-leadership-band {
      position: relative;
      box-sizing: border-box;
      margin-top: var(--sccc-lb-margin-top);
      margin-bottom: var(--sccc-lb-margin-bottom);
      padding-top: var(--sccc-lb-padding-top);
      padding-bottom: var(--sccc-lb-padding-bottom);
      background: var(--sccc-lb-section-bg);
      color: var(--sccc-lb-text);
    }

    /**
     * Default/no-alignment section width.
     *
     * Why:
     * - The designed background panel should not span full browser width unless
     *   the editor explicitly selects Full Width.
     */
    .sccc-leadership-band:not(.alignfull) {
      width: min(calc(100% - 2rem), var(--sccc-lb-container-width));
      max-width: var(--sccc-lb-container-width);
      margin-left: auto;
      margin-right: auto;
      border-radius: var(--sccc-lb-section-radius);
      overflow: clip;
    }

    /**
     * Full-width section.
     *
     * Why the viewport math exists:
     * - Some themes wrap content inside constrained containers.
     * - This lets the full-width block break out and stretch edge-to-edge.
     */
    .sccc-leadership-band.alignfull {
      width: 100vw;
      max-width: 100vw;
      margin-left: calc(50% - 50vw);
      margin-right: calc(50% - 50vw);
      border-radius: 0;
      overflow: clip;
    }

    body.sccc-leadership-modal-is-open {
      overflow: hidden;
    }

    /**
     * Inner content container.
     *
     * In default mode:
     * - The section already has container width, so the inner container just adds
     *   comfortable side padding.
     *
     * In full-width mode:
     * - The section spans the viewport, but the inner content remains readable.
     */
    .sccc-leadership-band__inner {
      box-sizing: border-box;
      width: 100%;
      margin-inline: auto;
      padding-inline: clamp(1rem, 3vw, 2rem);
    }

    .sccc-leadership-band.alignfull .sccc-leadership-band__inner {
      width: min(calc(100% - 2rem), var(--sccc-lb-full-inner-width));
      max-width: var(--sccc-lb-full-inner-width);
      padding-inline: 0;
    }

    .sccc-leadership-band__intro {
      max-width: 48rem;
      margin-bottom: clamp(1.75rem, 3vw, 2.75rem);
    }

    .sccc-leadership-band--text-left .sccc-leadership-band__intro {
      text-align: left;
    }

    .sccc-leadership-band--text-center .sccc-leadership-band__intro {
      margin-left: auto;
      margin-right: auto;
      text-align: center;
    }

    .sccc-leadership-band--text-center .sccc-leadership-band__subtext {
      margin-left: auto;
      margin-right: auto;
    }

    .sccc-leadership-band__headline {
      margin: 0;
      font-family: var(--font-display, inherit);
      font-size: clamp(2rem, 4vw, 3.35rem);
      line-height: 0.95;
      letter-spacing: -0.035em;
      color: var(--sccc-lb-text);
    }

    .sccc-leadership-band__subtext {
      margin: 0.85rem 0 0;
      max-width: 42rem;
      color: var(--sccc-lb-muted);
      font-size: clamp(1rem, 1.5vw, 1.125rem);
      line-height: 1.7;
    }

    .sccc-leadership-band__groups {
      display: grid;
      gap: clamp(2rem, 4vw, 3.5rem);
    }

    .sccc-leadership-band__group {
      position: relative;
    }

    .sccc-leadership-band__group-title {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      margin: 0 0 1rem;
      font-family: var(--font-display, inherit);
      font-size: clamp(1.2rem, 2vw, 1.65rem);
      line-height: 1;
      letter-spacing: 0.02em;
      color: var(--sccc-lb-text);
    }

    .sccc-leadership-band__group-title::before {
      content: "";
      width: 0.75rem;
      height: 0.75rem;
      border-radius: 999px;
      background:
        radial-gradient(circle at 35% 35%, #fff 0 8%, transparent 9%),
        linear-gradient(135deg, var(--sccc-lb-primary), var(--sccc-lb-accent));
      box-shadow:
        0 0 0 0.25rem color-mix(in oklab, var(--sccc-lb-primary) 12%, transparent),
        0 0 1.25rem color-mix(in oklab, var(--sccc-lb-accent) 28%, transparent);
    }

    .sccc-leadership-band__cards {
      display: flex;
      flex-wrap: wrap;
      gap: clamp(0.9rem, 2vw, 1.25rem);
      align-items: stretch;
      justify-content: space-evenly;
    }

    .sccc-leadership-card {
      position: relative;
      flex: 0 1 10.75rem;
      min-width: 9.75rem;
      border: 1px solid var(--sccc-lb-line);
      border-radius: var(--sccc-lb-card-radius);
      padding: 1.1rem 0.9rem 1rem;
      background:
        radial-gradient(16rem 9rem at 50% -40%, color-mix(in oklab, var(--sccc-lb-primary) 16%, transparent), transparent 64%),
        radial-gradient(14rem 8rem at 100% 0%, color-mix(in oklab, var(--sccc-lb-accent) 12%, transparent), transparent 65%),
        var(--sccc-lb-surface-soft);
      box-shadow: 0 0 0 rgb(0 0 0 / 0);
      color: inherit;
      text-align: center;
      cursor: pointer;
      appearance: none;
      transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        border-color 180ms ease,
        background-color 180ms ease;
      overflow: hidden;
    }

    .sccc-leadership-card::after {
      content: "";
      position: absolute;
      inset: 0;
      border-radius: inherit;
      pointer-events: none;
      background:
        linear-gradient(135deg, color-mix(in oklab, var(--sccc-lb-primary) 22%, transparent), transparent 42%),
        linear-gradient(315deg, color-mix(in oklab, var(--sccc-lb-accent) 18%, transparent), transparent 44%);
      opacity: 0;
      transition: opacity 180ms ease;
    }

    /**
     * Editor-only cards.
     *
     * These keep the preview looking like the frontend cards while removing the
     * clickable behavior that opens profile modals inside Gutenberg.
     */
    .sccc-leadership-card--editor {
      cursor: default;
    }

    .sccc-leadership-card.sccc-leadership-card--editor:hover,
    .sccc-leadership-card.sccc-leadership-card--editor:focus-visible {
      transform: none;
    }

    .sccc-leadership-card.sccc-leadership-card--editor:hover::after,
    .sccc-leadership-card.sccc-leadership-card--editor:focus-visible::after {
      opacity: 0;
    }

    .sccc-leadership-card:hover,
    .sccc-leadership-card:focus-visible {
      transform: translateY(-4px);
      border-color: color-mix(in oklab, var(--sccc-lb-primary) 42%, var(--sccc-lb-line));
      box-shadow:
        var(--sccc-lb-shadow),
        0 0 0 0.2rem color-mix(in oklab, var(--sccc-lb-primary) 10%, transparent),
        0 0 1.35rem color-mix(in oklab, var(--sccc-lb-accent) 18%, transparent);
      outline: none;
    }

    .sccc-leadership-card:hover::after,
    .sccc-leadership-card:focus-visible::after {
      opacity: 1;
    }

    .sccc-leadership-card__content {
      position: relative;
      z-index: 1;
      display: grid;
      justify-items: center;
      gap: 0.65rem;
    }

    .sccc-leadership-card__photo {
      width: 5.25rem;
      height: 5.25rem;
      border-radius: 999px;
      object-fit: cover;
      border: 2px solid color-mix(in oklab, var(--sccc-lb-surface) 86%, white);
      box-shadow:
        0 0 0 0.22rem color-mix(in oklab, var(--sccc-lb-primary) 16%, transparent),
        0 0 1.25rem color-mix(in oklab, var(--sccc-lb-accent) 18%, transparent);
      background: var(--sccc-lb-surface);
    }

    .sccc-leadership-card__name {
      margin: 0;
      max-width: 100%;
      color: var(--sccc-lb-text);
      font-weight: 800;
      font-size: 0.98rem;
      line-height: 1.15;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .sccc-leadership-card__positions {
      display: block;
      max-width: 100%;
      margin: 0;
      color: var(--sccc-lb-muted);
      font-size: 0.78rem;
      font-weight: 650;
      line-height: 1.32;
      white-space: normal;
      overflow: visible;
      text-overflow: initial;
      overflow-wrap: break-word;
    }

    .sccc-leadership-band__empty {
      border: 1px dashed var(--sccc-lb-line);
      border-radius: 1rem;
      padding: 1.25rem;
      color: var(--sccc-lb-muted);
      background: color-mix(in oklab, var(--sccc-lb-surface) 80%, transparent);
    }

    .sccc-leadership-modal[hidden] {
      display: none !important;
    }

    .sccc-leadership-modal {
      position: fixed;
      inset: 0;
      z-index: 999999;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100vw;
      width: 100dvw;
      height: 100vh;
      height: 100dvh;
      padding: clamp(1rem, 3vw, 2rem);
      box-sizing: border-box;
      pointer-events: none;
    }

    .sccc-leadership-modal__backdrop {
      position: absolute;
      inset: 0;
      background:
        radial-gradient(54rem 34rem at 50% 0%, color-mix(in oklab, var(--sccc-lb-primary) 18%, transparent), transparent 68%),
        radial-gradient(44rem 28rem at 85% 20%, color-mix(in oklab, var(--sccc-lb-accent) 14%, transparent), transparent 64%),
        rgb(2 6 23 / 0.72);
      backdrop-filter: blur(12px) saturate(118%);
      pointer-events: auto;
    }

    .sccc-leadership-modal__dialog {
      position: relative;
      isolation: isolate;
      width: min(100%, 50rem);
      max-height: min(44rem, calc(100dvh - 2rem));
      margin: auto;
      overflow: auto;
      border: 1px solid color-mix(in oklab, var(--sccc-lb-primary) 26%, var(--sccc-lb-line));
      border-radius: 1.65rem;
      background:
        radial-gradient(34rem 16rem at 15% -10%, color-mix(in oklab, var(--sccc-lb-primary) 13%, transparent), transparent 62%),
        radial-gradient(30rem 15rem at 95% 0%, color-mix(in oklab, var(--sccc-lb-accent) 11%, transparent), transparent 64%),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--sccc-lb-surface) 92%, white 8%) 0%,
          color-mix(in oklab, var(--sccc-lb-surface) 96%, var(--sccc-lb-primary) 4%) 100%
        );
      color: var(--sccc-lb-text);
      box-shadow:
        0 34px 110px rgb(0 0 0 / 0.54),
        0 18px 42px rgb(0 0 0 / 0.28),
        0 0 0 1px color-mix(in oklab, white 12%, transparent),
        0 0 0 0.3rem color-mix(in oklab, var(--sccc-lb-primary) 10%, transparent),
        0 0 3rem color-mix(in oklab, var(--sccc-lb-accent) 14%, transparent);
      backdrop-filter: blur(18px) saturate(126%);
      pointer-events: auto;
    }

    .dark .sccc-leadership-modal__dialog,
    [data-theme="dark"] .sccc-leadership-modal__dialog {
      background:
        radial-gradient(34rem 16rem at 15% -10%, color-mix(in oklab, var(--sccc-lb-primary) 13%, transparent), transparent 62%),
        radial-gradient(30rem 15rem at 95% 0%, color-mix(in oklab, var(--sccc-lb-accent) 11%, transparent), transparent 64%),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--sccc-lb-surface) 82%, #020617 18%) 0%,
          color-mix(in oklab, var(--sccc-lb-surface) 72%, #020617 28%) 100%
        );
    }

    .sccc-leadership-modal__dialog::before {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -1;
      border-radius: inherit;
      pointer-events: none;
      background:
        linear-gradient(135deg, color-mix(in oklab, white 16%, transparent), transparent 34%),
        linear-gradient(315deg, color-mix(in oklab, var(--sccc-lb-primary) 12%, transparent), transparent 38%);
      opacity: 0.9;
    }

    .sccc-leadership-modal__dialog::after {
      content: "";
      position: absolute;
      inset: 0.55rem;
      z-index: -1;
      border-radius: 1.25rem;
      pointer-events: none;
      border: 1px solid color-mix(in oklab, white 8%, transparent);
      opacity: 0.75;
    }

    .sccc-leadership-modal__close {
      position: sticky;
      top: 0.85rem;
      z-index: 2;
      float: right;
      display: inline-grid;
      place-items: center;
      width: 2.25rem;
      height: 2.25rem;
      margin: 0.85rem 0.85rem 0 0;
      border: 1px solid color-mix(in oklab, var(--sccc-lb-line) 70%, white 12%);
      border-radius: 999px;
      background: color-mix(in oklab, var(--sccc-lb-surface) 78%, transparent);
      color: var(--sccc-lb-text);
      cursor: pointer;
      box-shadow: 0 8px 20px rgb(0 0 0 / 0.18);
      backdrop-filter: blur(10px);
      transition:
        transform 160ms ease,
        border-color 160ms ease,
        box-shadow 160ms ease;
    }

    .dark .sccc-leadership-modal__close,
    [data-theme="dark"] .sccc-leadership-modal__close {
      background: rgb(15 23 42 / 0.72);
      border-color: rgba(255,255,255,.14);
    }

    .sccc-leadership-modal__close:hover,
    .sccc-leadership-modal__close:focus-visible {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--sccc-lb-accent) 52%, var(--sccc-lb-line));
      box-shadow:
        0 10px 24px rgb(0 0 0 / 0.24),
        0 0 1rem color-mix(in oklab, var(--sccc-lb-accent) 22%, transparent);
      outline: none;
    }

    .sccc-leadership-modal__panel[hidden] {
      display: none;
    }

    .sccc-leadership-modal__panel {
      padding: clamp(1.25rem, 4vw, 2.35rem);
      clear: both;
    }

    .sccc-leadership-modal__header {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: clamp(1rem, 3vw, 1.5rem);
      align-items: center;
      margin-bottom: clamp(1.2rem, 3vw, 1.75rem);
      padding-bottom: clamp(1rem, 2vw, 1.35rem);
      border-bottom: 1px solid color-mix(in oklab, var(--sccc-lb-line) 72%, transparent);
    }

    .sccc-leadership-modal__photo {
      width: clamp(5.75rem, 12vw, 8rem);
      height: clamp(5.75rem, 12vw, 8rem);
      border-radius: 999px;
      object-fit: cover;
      border: 2px solid color-mix(in oklab, var(--sccc-lb-surface) 86%, white);
      box-shadow:
        0 0 0 0.25rem color-mix(in oklab, var(--sccc-lb-primary) 16%, transparent),
        0 0 1.75rem color-mix(in oklab, var(--sccc-lb-accent) 22%, transparent);
      background: var(--sccc-lb-surface);
    }

    .sccc-leadership-modal__name {
      margin: 0;
      font-family: var(--font-display, inherit);
      font-size: clamp(1.65rem, 4vw, 2.6rem);
      line-height: 0.95;
      letter-spacing: -0.025em;
      color: var(--sccc-lb-text);
    }

    .sccc-leadership-modal__positions {
      margin: 0.55rem 0 0;
      color: var(--sccc-lb-muted);
      font-size: clamp(0.95rem, 1.4vw, 1.08rem);
      font-weight: 750;
      line-height: 1.4;
    }

    .sccc-leadership-modal__bio {
      color: var(--sccc-lb-text);
      font-size: 1rem;
      line-height: 1.75;
    }

    .sccc-leadership-modal__bio :where(p, ul, ol) {
      margin-top: 0;
      margin-bottom: 1rem;
    }

    .sccc-leadership-modal__bio :where(p:last-child, ul:last-child, ol:last-child) {
      margin-bottom: 0;
    }

    @media (max-width: 640px) {
      .sccc-leadership-band:not(.alignfull) {
        width: min(calc(100% - 1rem), var(--sccc-lb-container-width));
      }

      .sccc-leadership-card {
        flex-basis: calc(50% - 0.5rem);
      }

      .sccc-leadership-card__name {
        white-space: normal;
        overflow: visible;
        text-overflow: initial;
      }

      .sccc-leadership-modal__header {
        grid-template-columns: 1fr;
        justify-items: center;
        text-align: center;
      }
    }

    @media (max-width: 420px) {
      .sccc-leadership-card {
        flex-basis: 100%;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .sccc-leadership-card,
      .sccc-leadership-card::after,
      .sccc-leadership-modal__close {
        transition: none;
      }
    }
  </style>

  @if (!empty($shouldRenderModal))
    <script>
      (() => {
        if (window.scccLeadershipBandModalReady) {
          return;
        }

        window.scccLeadershipBandModalReady = true;

        let lastFocusedElement = null;

        const escapeSelectorValue = (value) => {
          const stringValue = String(value || '');

          if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(stringValue);
          }

          return stringValue.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        };

        const closeModal = (modal) => {
          if (!modal) {
            return;
          }

          modal.hidden = true;

          modal.querySelectorAll('[data-sccc-leadership-panel]').forEach((panel) => {
            panel.hidden = true;
          });

          const openModal = document.querySelector('[data-sccc-leadership-modal]:not([hidden])');
          if (!openModal) {
            document.body.classList.remove('sccc-leadership-modal-is-open');
          }

          if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
          }

          lastFocusedElement = null;
        };

        const openModal = (button) => {
          const block = button.closest('[data-sccc-leadership-band]');
          const blockId = button.getAttribute('data-sccc-leadership-block');
          const panelId = button.getAttribute('data-sccc-leadership-open');

          if (!block || !blockId || !panelId) {
            return;
          }

          let modal = block.querySelector(`[data-sccc-leadership-modal="${escapeSelectorValue(blockId)}"]`);

          if (!modal) {
            modal = document.querySelector(`[data-sccc-leadership-modal="${escapeSelectorValue(blockId)}"]`);
          }

          if (!modal) {
            return;
          }

          const panel = modal.querySelector(`[data-sccc-leadership-panel="${escapeSelectorValue(panelId)}"]`);

          if (!panel) {
            return;
          }

          if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
          }

          lastFocusedElement = document.activeElement;

          modal.querySelectorAll('[data-sccc-leadership-panel]').forEach((candidate) => {
            candidate.hidden = candidate !== panel;
          });

          document.body.classList.add('sccc-leadership-modal-is-open');

          modal.hidden = false;

          const closeButton = modal.querySelector('[data-sccc-leadership-close]');
          if (closeButton && typeof closeButton.focus === 'function') {
            closeButton.focus();
          }
        };

        document.addEventListener('click', (event) => {
          const openButton = event.target.closest('[data-sccc-leadership-open]');
          if (openButton) {
            event.preventDefault();
            openModal(openButton);
            return;
          }

          const closeButton = event.target.closest('[data-sccc-leadership-close]');
          if (closeButton) {
            event.preventDefault();
            closeModal(closeButton.closest('[data-sccc-leadership-modal]'));
            return;
          }

          const backdrop = event.target.closest('[data-sccc-leadership-backdrop]');
          if (backdrop) {
            event.preventDefault();
            closeModal(backdrop.closest('[data-sccc-leadership-modal]'));
          }
        });

        document.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape') {
            return;
          }

          const openModalElement = document.querySelector('[data-sccc-leadership-modal]:not([hidden])');

          if (openModalElement) {
            closeModal(openModalElement);
          }
        });
      })();
    </script>
  @endif
@endonce

@if (!empty($groups) || !empty($isAcfPreview))
  <section {{ $rootAttributes }}>
    <div class="sccc-leadership-band__inner">
      @if ($hasIntro)
        <header class="sccc-leadership-band__intro">
          @if (!empty($headline))
            <h2 class="sccc-leadership-band__headline">
              {{ $headline }}
            </h2>
          @endif

          @if (!empty($subtext))
            <p class="sccc-leadership-band__subtext">
              {!! wp_kses_post($subtext) !!}
            </p>
          @endif
        </header>
      @endif

      @if (!empty($groups))
        <div class="sccc-leadership-band__groups">
          @foreach ($groups as $group)
            <section class="sccc-leadership-band__group" aria-label="{{ esc_attr($group['label'] ?? __('Leadership group', 'sccc')) }}">
              @if (!empty($isGrouped))
                <h3 class="sccc-leadership-band__group-title">
                  {{ $group['label'] ?? '' }}
                </h3>
              @endif

              <div class="sccc-leadership-band__cards">
                @foreach (($group['leaders'] ?? []) as $leader)
                  @php
                    $leaderId = sanitize_html_class($sectionId . '-leader-' . (string) ($leader['postId'] ?? wp_unique_id()));
                    $positionsInline = !empty($leader['positions'])
                      ? implode(', ', array_map('strval', $leader['positions']))
                      : '';
                  @endphp

                  @if (!empty($shouldRenderModal))
                    <button
                      class="sccc-leadership-card"
                      type="button"
                      data-sccc-leadership-block="{{ esc_attr($sectionId) }}"
                      data-sccc-leadership-open="{{ esc_attr($leaderId) }}"
                      aria-haspopup="dialog"
                      aria-label="{{ esc_attr(sprintf(__('View bio for %s', 'sccc'), $leader['name'] ?? __('Leader', 'sccc'))) }}"
                    >
                      <span class="sccc-leadership-card__content">
                        @if (!empty($leader['avatarUrl']))
                          <img
                            class="sccc-leadership-card__photo"
                            src="{{ esc_url($leader['avatarUrl']) }}"
                            alt="{{ esc_attr($leader['name'] ?? __('Leader', 'sccc')) }}"
                            loading="lazy"
                            decoding="async"
                          >
                        @endif

                        <span class="sccc-leadership-card__name">
                          {{ $leader['name'] ?? '' }}
                        </span>

                        @if (!empty($positionsInline))
                          <span class="sccc-leadership-card__positions" title="{{ esc_attr($positionsInline) }}">
                            {{ $positionsInline }}
                          </span>
                        @endif
                      </span>
                    </button>
                  @else
                    <div
                      class="sccc-leadership-card sccc-leadership-card--editor"
                      role="group"
                      aria-label="{{ esc_attr(sprintf(__('Leadership preview for %s', 'sccc'), $leader['name'] ?? __('Leader', 'sccc'))) }}"
                    >
                      <span class="sccc-leadership-card__content">
                        @if (!empty($leader['avatarUrl']))
                          <img
                            class="sccc-leadership-card__photo"
                            src="{{ esc_url($leader['avatarUrl']) }}"
                            alt="{{ esc_attr($leader['name'] ?? __('Leader', 'sccc')) }}"
                            loading="lazy"
                            decoding="async"
                          >
                        @endif

                        <span class="sccc-leadership-card__name">
                          {{ $leader['name'] ?? '' }}
                        </span>

                        @if (!empty($positionsInline))
                          <span class="sccc-leadership-card__positions" title="{{ esc_attr($positionsInline) }}">
                            {{ $positionsInline }}
                          </span>
                        @endif
                      </span>
                    </div>
                  @endif
                @endforeach
              </div>
            </section>
          @endforeach
        </div>

        @if (!empty($shouldRenderModal))
          <div
            class="sccc-leadership-modal {{ $themeClass }}"
            data-sccc-leadership-modal="{{ esc_attr($sectionId) }}"
            hidden
          >
            <div
              class="sccc-leadership-modal__backdrop"
              data-sccc-leadership-backdrop
              aria-hidden="true"
            ></div>

            <div
              class="sccc-leadership-modal__dialog"
              role="dialog"
              aria-modal="true"
              aria-label="{{ esc_attr(__('Leadership profile', 'sccc')) }}"
            >
              <button
                class="sccc-leadership-modal__close"
                type="button"
                data-sccc-leadership-close
                aria-label="{{ esc_attr(__('Close profile', 'sccc')) }}"
              >
                ×
              </button>

              @foreach ($groups as $group)
                @foreach (($group['leaders'] ?? []) as $leader)
                  @php
                    $leaderId = sanitize_html_class($sectionId . '-leader-' . (string) ($leader['postId'] ?? wp_unique_id()));
                    $positionsInline = !empty($leader['positions'])
                      ? implode(', ', array_map('strval', $leader['positions']))
                      : '';
                  @endphp

                  <article
                    class="sccc-leadership-modal__panel"
                    data-sccc-leadership-panel="{{ esc_attr($leaderId) }}"
                    hidden
                  >
                    <header class="sccc-leadership-modal__header">
                      @if (!empty($leader['avatarUrl']))
                        <img
                          class="sccc-leadership-modal__photo"
                          src="{{ esc_url($leader['avatarUrl']) }}"
                          alt="{{ esc_attr($leader['name'] ?? __('Leader', 'sccc')) }}"
                          loading="lazy"
                          decoding="async"
                        >
                      @endif

                      <div>
                        <h3 class="sccc-leadership-modal__name">
                          {{ $leader['name'] ?? '' }}
                        </h3>

                        @if (!empty($positionsInline))
                          <p class="sccc-leadership-modal__positions">
                            {{ $positionsInline }}
                          </p>
                        @endif
                      </div>
                    </header>

                    @if (!empty($leader['bio']))
                      <div class="sccc-leadership-modal__bio">
                        {!! wp_kses_post($leader['bio']) !!}
                      </div>
                    @endif
                  </article>
                @endforeach
              @endforeach
            </div>
          </div>
        @endif
      @elseif (!empty($isAcfPreview))
        <div class="sccc-leadership-band__empty">
          {{ __('No matching leadership entries were found. Check the selected Leadership Categories or publish Leadership entries assigned to those categories.', 'sccc') }}
        </div>
      @endif
    </div>
  </section>
@endif