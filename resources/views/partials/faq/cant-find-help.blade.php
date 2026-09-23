{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/faq/cant-find-help.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the closing support block.
|
| Why this file exists:
| - The closing FAQ CTA is fed from Theme Settings and should stay isolated
|   from the main FAQ module markup.
|
| Notes:
| - All selectors remain scoped to `.sccc-faq-scope`.
| - This version keeps the current content structure, but updates the buttons
|   to a glassy neon-border treatment that matches the approved reference.
|--------------------------------------------------------------------------
--}}

@php
  /**
   * -------------------------------------------------------------------------
   * Defensive normalization
   * -------------------------------------------------------------------------
   * Why this block exists:
   * - Keeps the partial safe if the composer passes incomplete data.
   */
  $faqHelp = is_array($faqHelp ?? null) ? $faqHelp : [];

  $heading = isset($faqHelp['heading']) && is_string($faqHelp['heading'])
    ? trim($faqHelp['heading'])
    : '';

  $copy = isset($faqHelp['copy']) && is_string($faqHelp['copy'])
    ? trim($faqHelp['copy'])
    : '';

  $primary = isset($faqHelp['primary']) && is_array($faqHelp['primary'])
    ? $faqHelp['primary']
    : [];

  $secondary = isset($faqHelp['secondary']) && is_array($faqHelp['secondary'])
    ? $faqHelp['secondary']
    : [];
@endphp

<style>
  .sccc-faq-scope .sccc-faq-help {
    --faq-help-button-fill-light-top: rgba(10, 26, 48, 0.12);
    --faq-help-button-fill-light-bottom: rgba(10, 26, 48, 0.06);
    --faq-help-button-fill-dark-top: rgba(10, 18, 34, 0.74);
    --faq-help-button-fill-dark-bottom: rgba(7, 14, 28, 0.54);
    --faq-help-button-text-light: #17324d;
    --faq-help-button-text-dark: #f5f9ff;
    --faq-help-button-gradient:
      linear-gradient(
        90deg,
        color-mix(in srgb, var(--faq-primary-light) 92%, white) 0%,
        color-mix(in srgb, #60a5fa 88%, white) 46%,
        color-mix(in srgb, #8b5cf6 84%, white) 100%
      );
    --faq-help-button-shadow-light:
      0 10px 20px rgba(15, 23, 42, 0.08),
      0 0 18px rgba(19, 91, 236, 0.08);
    --faq-help-button-shadow-dark:
      0 12px 24px rgba(0, 0, 0, 0.32),
      0 0 20px rgba(96, 165, 250, 0.10);

    margin: 0 0 clamp(4rem, 8vw, 6rem);
    border: 1px solid var(--faq-border-light);
    border-radius: 1.65rem;
    background:
      radial-gradient(28rem 18rem at 0% 0%, rgba(19, 91, 236, 0.08), transparent 62%),
      var(--faq-surface-light);
    box-shadow: var(--faq-shadow-light);
    backdrop-filter: blur(12px);
    padding: clamp(1.75rem, 4vw, 3rem);
    text-align: center;
  }

  html.dark .sccc-faq-scope .sccc-faq-help,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-help {
    border-color: var(--faq-border-dark);
    background:
      radial-gradient(28rem 18rem at 0% 0%, rgba(96, 165, 250, 0.11), transparent 62%),
      var(--faq-surface-dark);
    box-shadow: var(--faq-shadow-dark);
  }

  .sccc-faq-scope .sccc-faq-help__title {
    margin: 0;
    font-size: clamp(1.9rem, 3vw, 2.8rem);
    line-height: 1.08;
    font-weight: 800;
    color: var(--faq-text-light);
  }

  .sccc-faq-scope .sccc-faq-help__copy {
    width: min(100%, 42rem);
    margin: 1rem auto 0;
    color: var(--faq-muted-light);
    line-height: 1.85;
    font-size: 1rem;
  }

  html.dark .sccc-faq-scope .sccc-faq-help__title,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-help__title {
    color: var(--faq-text-dark);
  }

  html.dark .sccc-faq-scope .sccc-faq-help__copy,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-help__copy {
    color: var(--faq-muted-dark);
  }

  .sccc-faq-scope .sccc-faq-help__actions {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.9rem;
    margin-top: 1.5rem;
  }

  @media (min-width: 640px) {
    .sccc-faq-scope .sccc-faq-help__actions {
      flex-direction: row;
      align-items: center;
    }
  }

  .sccc-faq-scope .sccc-faq-help__button {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3rem;
    padding: 0.9rem 1.4rem;
    border-radius: 9999px;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.96rem;
    line-height: 1.1;
    letter-spacing: 0;
    color: var(--faq-help-button-text-light);
    background:
      linear-gradient(
        180deg,
        var(--faq-help-button-fill-light-top),
        var(--faq-help-button-fill-light-bottom)
      ) padding-box,
      var(--faq-help-button-gradient) border-box;
    box-shadow: var(--faq-help-button-shadow-light);
    backdrop-filter: blur(16px) saturate(155%);
    -webkit-backdrop-filter: blur(16px) saturate(155%);
    transition:
      transform 0.25s ease,
      box-shadow 0.25s ease,
      background 0.25s ease,
      color 0.25s ease,
      filter 0.25s ease;
  }

  .sccc-faq-scope .sccc-faq-help__button::before {
    content: "";
    position: absolute;
    inset: 1px;
    border-radius: inherit;
    pointer-events: none;
    background: linear-gradient(
      180deg,
      rgba(255, 255, 255, 0.14),
      rgba(255, 255, 255, 0)
    );
    opacity: 0.7;
  }

  html.dark .sccc-faq-scope .sccc-faq-help__button,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-help__button {
    color: var(--faq-help-button-text-dark);
    background:
      linear-gradient(
        180deg,
        var(--faq-help-button-fill-dark-top),
        var(--faq-help-button-fill-dark-bottom)
      ) padding-box,
      var(--faq-help-button-gradient) border-box;
    box-shadow: var(--faq-help-button-shadow-dark);
  }

  .sccc-faq-scope .sccc-faq-help__button:hover {
    transform: translateY(-1px);
    filter: brightness(1.03);
  }

  .sccc-faq-scope .sccc-faq-help__button--primary:hover {
    box-shadow:
      0 14px 28px color-mix(in srgb, var(--faq-primary-light) 14%, transparent),
      0 8px 18px rgba(139, 92, 246, 0.12);
  }

  html.dark .sccc-faq-scope .sccc-faq-help__button--primary:hover,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-help__button--primary:hover {
    box-shadow:
      0 14px 28px rgba(0, 0, 0, 0.34),
      0 0 20px color-mix(in srgb, var(--faq-primary-dark) 12%, transparent);
  }

  .sccc-faq-scope .sccc-faq-help__button--secondary {
    font-weight: 600;
    opacity: 0.96;
  }

  .sccc-faq-scope .sccc-faq-help__button--secondary:hover {
    box-shadow:
      0 12px 24px rgba(15, 23, 42, 0.08),
      0 0 16px rgba(96, 165, 250, 0.08);
  }

  html.dark .sccc-faq-scope .sccc-faq-help__button--secondary:hover,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-help__button--secondary:hover {
    box-shadow:
      0 12px 24px rgba(0, 0, 0, 0.3),
      0 0 16px rgba(96, 165, 250, 0.08);
  }
</style>

<section class="sccc-faq-help" aria-label="Need more help">
  @if ($heading !== '')
    <h2 class="sccc-faq-help__title">{{ $heading }}</h2>
  @endif

  @if ($copy !== '')
    <p class="sccc-faq-help__copy">{!! nl2br(e($copy)) !!}</p>
  @endif

  <div class="sccc-faq-help__actions">
    @if (! empty($primary['url']) && ! empty($primary['title']))
      <a
        class="sccc-faq-help__button sccc-faq-help__button--primary"
        href="{{ $primary['url'] }}"
        @if (! empty($primary['target'])) target="{{ $primary['target'] }}" @endif
        @if (! empty($primary['target']) && $primary['target'] === '_blank') rel="noopener noreferrer" @endif
      >
        {{ $primary['title'] }}
      </a>
    @endif

    @if (! empty($secondary['url']) && ! empty($secondary['title']))
      <a
        class="sccc-faq-help__button sccc-faq-help__button--secondary"
        href="{{ $secondary['url'] }}"
        @if (! empty($secondary['target'])) target="{{ $secondary['target'] }}" @endif
        @if (! empty($secondary['target']) && $secondary['target'] === '_blank') rel="noopener noreferrer" @endif
      >
        {{ $secondary['title'] }}
      </a>
    @endif
  </div>
</section>