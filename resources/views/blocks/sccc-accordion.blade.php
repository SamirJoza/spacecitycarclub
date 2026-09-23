{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/sccc-accordion.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render a reusable Gutenberg accordion block using the same visual language
|   as the existing FAQ accordion module.
|
| Notes:
| - Styling is intentionally block-level and scoped to `.sccc-accordion-scope`.
| - Plain text fields are decoded first, then safely escaped once.
| - Escaped text is printed with `{!! !!}` because `esc_html()` already escapes
|   the value; printing with `{{ }}` would escape it again.
|--------------------------------------------------------------------------
--}}

@php
  $blockData = isset($block) && is_array($block) ? $block : [];

  $blockId = ! empty($blockData['anchor'])
    ? sanitize_title($blockData['anchor'])
    : 'sccc-accordion-' . wp_unique_id();

  $alignClass = ! empty($blockData['align'])
    ? 'align' . esc_attr($blockData['align'])
    : '';

  $decodePlainText = function ($value): string {
    return html_entity_decode(
      (string) $value,
      ENT_QUOTES | ENT_HTML5,
      get_bloginfo('charset') ?: 'UTF-8'
    );
  };

  $safeText = function ($value) use ($decodePlainText): string {
    return esc_html($decodePlainText($value));
  };

  $safeTextarea = function ($value) use ($decodePlainText): string {
    return nl2br(esc_html($decodePlainText($value)));
  };

  $hasHeader = ! empty($eyebrow) || ! empty($headline) || ! empty($intro);
  $hasSections = ! empty($sections);
@endphp

<style>
  .wp-block-acf-sccc-accordion,
  .wp-block-acf-s-c-c-c-accordion,
  .editor-styles-wrapper .wp-block-acf-sccc-accordion,
  .editor-styles-wrapper .wp-block-acf-s-c-c-c-accordion,
  .editor-styles-wrapper .acf-block-preview:has(.sccc-accordion-scope),
  .editor-styles-wrapper .acf-block-preview .sccc-accordion-scope,
  .sccc-accordion-scope {
    display: block !important;
    width: 100% !important;
    inline-size: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
  }

  .sccc-accordion-scope {
    --accordion-text-light: var(--faq-text-light, var(--color-text, #172033));
    --accordion-text-dark: var(--faq-text-dark, #f8fbff);
    --accordion-muted-light: var(--faq-muted-light, var(--color-muted, #5f6670));
    --accordion-muted-dark: var(--faq-muted-dark, #aab5c8);
    --accordion-primary-light: var(--faq-primary-light, var(--color-primary-500, #135bec));
    --accordion-primary-dark: var(--faq-primary-dark, #60a5fa);
    --accordion-primary-strong-dark: var(--faq-primary-strong-dark, #93c5fd);
    --accordion-primary-soft-light: var(--faq-primary-soft-light, rgba(19, 91, 236, 0.08));
    --accordion-primary-soft-dark: var(--faq-primary-soft-dark, rgba(96, 165, 250, 0.12));
    --accordion-border-light: var(--faq-border-light, rgba(15, 23, 42, 0.11));
    --accordion-border-dark: var(--faq-border-dark, rgba(255, 255, 255, 0.12));
    --accordion-surface-light: var(--faq-surface-light, rgba(255, 255, 255, 0.78));
    --accordion-surface-dark: var(--faq-surface-dark, rgba(15, 23, 42, 0.68));
    --accordion-shadow-light: var(--faq-shadow-light, 0 18px 45px rgba(15, 23, 42, 0.08));
    --accordion-shadow-dark: var(--faq-shadow-dark, 0 20px 55px rgba(0, 0, 0, 0.35));

    overflow-x: clip;
  }

  .sccc-accordion-scope,
  .sccc-accordion-scope * {
    box-sizing: border-box;
  }

  .sccc-accordion-scope .sccc-accordion,
  .sccc-accordion-scope .sccc-accordion__sections,
  .sccc-accordion-scope .sccc-accordion__section,
  .sccc-accordion-scope .sccc-accordion__items,
  .sccc-accordion-scope .sccc-accordion__item,
  .sccc-accordion-scope details {
    display: block;
    width: 100% !important;
    inline-size: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
  }

  .sccc-accordion-scope .sccc-accordion {
    display: grid;
    gap: 2rem;
  }

  .sccc-accordion-scope .sccc-accordion__header {
    display: grid;
    gap: 0.75rem;
    width: 100%;
    max-width: none;
  }

  .sccc-accordion-scope .sccc-accordion__eyebrow {
    margin: 0;
    color: var(--accordion-primary-light);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    line-height: 1.4;
    text-transform: uppercase;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__eyebrow,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__eyebrow {
    color: var(--accordion-primary-dark);
  }

  .sccc-accordion-scope .sccc-accordion__headline {
    margin: 0;
    color: var(--accordion-text-light);
    font-size: clamp(2rem, 4vw, 3.5rem);
    font-weight: 800;
    line-height: 1.05;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__headline,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__headline {
    color: var(--accordion-text-dark);
  }

  .sccc-accordion-scope .sccc-accordion__intro {
    margin: 0;
    color: var(--accordion-muted-light);
    font-size: 1rem;
    line-height: 1.8;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__intro,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__intro {
    color: var(--accordion-muted-dark);
  }

  .sccc-accordion-scope .sccc-accordion__sections {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 2.75rem;
  }

  .sccc-accordion-scope .sccc-accordion__section-head {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    width: 100%;
  }

  .sccc-accordion-scope .sccc-accordion__section-head h3 {
    margin: 0;
    color: var(--accordion-text-light);
    font-size: clamp(1.45rem, 2vw, 2rem);
    font-weight: 800;
    line-height: 1.1;
    white-space: nowrap;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__section-head h3,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__section-head h3 {
    color: var(--accordion-text-dark);
  }

  .sccc-accordion-scope .sccc-accordion__section-line {
    flex: 1 1 auto;
    min-width: 2rem;
    height: 1px;
    background: var(--accordion-border-light);
  }

  html.dark .sccc-accordion-scope .sccc-accordion__section-line,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__section-line {
    background: var(--accordion-border-dark);
  }

  .sccc-accordion-scope .sccc-accordion__section-description {
    margin: 0 0 1.35rem;
    max-width: 46rem;
    color: var(--accordion-muted-light);
    line-height: 1.8;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__section-description,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__section-description {
    color: var(--accordion-muted-dark);
  }

  .sccc-accordion-scope .sccc-accordion__items {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    justify-items: stretch;
    align-items: stretch;
    gap: 1rem;
  }

  .sccc-accordion-scope .sccc-accordion__item {
    display: block;
    justify-self: stretch;
    align-self: stretch;
    border: 1px solid var(--accordion-border-light);
    border-radius: 1.25rem;
    background: var(--accordion-surface-light);
    box-shadow: var(--accordion-shadow-light);
    overflow: hidden;
    backdrop-filter: blur(12px);
    transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
  }

  .sccc-accordion-scope .sccc-accordion__item:hover {
    transform: translateY(-2px);
    border-color: rgba(19, 91, 236, 0.18);
  }

  html.dark .sccc-accordion-scope .sccc-accordion__item,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__item {
    border-color: var(--accordion-border-dark);
    background: var(--accordion-surface-dark);
    box-shadow: var(--accordion-shadow-dark);
  }

  html.dark .sccc-accordion-scope .sccc-accordion__item:hover,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__item:hover {
    border-color: rgba(96, 165, 250, 0.24);
  }

  .sccc-accordion-scope .sccc-accordion details > summary {
    list-style: none;
  }

  .sccc-accordion-scope .sccc-accordion details > summary::-webkit-details-marker {
    display: none;
  }

  .sccc-accordion-scope .sccc-accordion__summary {
    display: flex !important;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    width: 100% !important;
    inline-size: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
    padding: 1.35rem 1.35rem 1.2rem;
    cursor: pointer;
  }

  .sccc-accordion-scope .sccc-accordion__question {
    min-width: 0;
    color: var(--accordion-text-light);
    font-size: 1.06rem;
    font-weight: 700;
    line-height: 1.6;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__question,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__question {
    color: var(--accordion-text-dark);
  }

  .sccc-accordion-scope .sccc-accordion__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border: 1px solid var(--accordion-border-light);
    border-radius: 9999px;
    color: #728099;
    background: rgba(255, 255, 255, 0.75);
    transition: transform 0.25s ease, color 0.25s ease, border-color 0.25s ease, background-color 0.25s ease;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__icon,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__icon {
    border-color: var(--accordion-border-dark);
    background: rgba(255, 255, 255, 0.03);
    color: #aab5c8;
  }

  .sccc-accordion-scope .sccc-accordion details[open] .sccc-accordion__icon {
    transform: rotate(180deg);
    color: var(--accordion-primary-light);
    border-color: rgba(19, 91, 236, 0.2);
    background: var(--accordion-primary-soft-light);
  }

  html.dark .sccc-accordion-scope .sccc-accordion details[open] .sccc-accordion__icon,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion details[open] .sccc-accordion__icon {
    color: var(--accordion-primary-strong-dark);
    border-color: rgba(96, 165, 250, 0.24);
    background: var(--accordion-primary-soft-dark);
  }

  .sccc-accordion-scope .sccc-accordion__answer {
    padding: 0 1.35rem 1.35rem;
    color: var(--accordion-muted-light);
    line-height: 1.85;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__answer,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__answer {
    color: var(--accordion-muted-dark);
  }

  .sccc-accordion-scope .sccc-accordion__answer > :first-child {
    margin-top: 0;
  }

  .sccc-accordion-scope .sccc-accordion__answer > :last-child {
    margin-bottom: 0;
  }

  .sccc-accordion-scope .sccc-accordion__answer a {
    color: var(--accordion-primary-light);
    text-decoration: none;
    border-bottom: 1px solid currentColor;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__answer a,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__answer a {
    color: var(--accordion-primary-dark);
  }

  .sccc-accordion-scope .sccc-accordion__empty {
    width: 100%;
    border: 1px solid var(--accordion-border-light);
    border-radius: 1.25rem;
    background: var(--accordion-surface-light);
    box-shadow: var(--accordion-shadow-light);
    padding: 2.4rem 1.5rem;
    text-align: center;
    backdrop-filter: blur(12px);
  }

  html.dark .sccc-accordion-scope .sccc-accordion__empty,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__empty {
    border-color: var(--accordion-border-dark);
    background: var(--accordion-surface-dark);
    box-shadow: var(--accordion-shadow-dark);
  }

  .sccc-accordion-scope .sccc-accordion__empty h3 {
    margin: 0 0 0.5rem;
    color: var(--accordion-text-light);
    font-size: 1.55rem;
    font-weight: 800;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__empty h3,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__empty h3 {
    color: var(--accordion-text-dark);
  }

  .sccc-accordion-scope .sccc-accordion__empty p {
    margin: 0;
    color: var(--accordion-muted-light);
    line-height: 1.8;
  }

  html.dark .sccc-accordion-scope .sccc-accordion__empty p,
  [data-theme="dark"] .sccc-accordion-scope .sccc-accordion__empty p {
    color: var(--accordion-muted-dark);
  }
</style>

<section
  id="{!! esc_attr($blockId) !!}"
  class="sccc-accordion-scope {!! esc_attr($alignClass) !!}"
  style="display: block; width: 100%; max-width: none;"
  data-sccc-accordion
  data-allow-multiple="{!! $allowMultipleOpen ? 'true' : 'false' !!}"
>
  <div class="sccc-accordion">
    @if ($hasHeader)
      <header class="sccc-accordion__header">
        @if (! empty($eyebrow))
          <p class="sccc-accordion__eyebrow">{!! $safeText($eyebrow) !!}</p>
        @endif

        @if (! empty($headline))
          <h2 class="sccc-accordion__headline">{!! $safeText($headline) !!}</h2>
        @endif

        @if (! empty($intro))
          <p class="sccc-accordion__intro">{!! $safeTextarea($intro) !!}</p>
        @endif
      </header>
    @endif

    @if ($hasSections)
      <div class="sccc-accordion__sections">
        @foreach ($sections as $section)
          <section class="sccc-accordion__section" data-sccc-accordion-section>
            @if (! empty($section['label']))
              <div class="sccc-accordion__section-head">
                <h3>{!! $safeText($section['label']) !!}</h3>
                <span class="sccc-accordion__section-line" aria-hidden="true"></span>
              </div>
            @endif

            @if (! empty($section['description']))
              <p class="sccc-accordion__section-description">
                {!! $safeTextarea($section['description']) !!}
              </p>
            @endif

            @if (! empty($section['items']))
              <div class="sccc-accordion__items">
                @foreach ($section['items'] as $item)
                  <article class="sccc-accordion__item">
                    <details
                      data-sccc-accordion-details
                      @if ($openFirstItem && $loop->parent->first && $loop->first) open @endif
                    >
                      <summary class="sccc-accordion__summary">
                        <span class="sccc-accordion__question">
                          {!! $safeText($item['question']) !!}
                        </span>

                        <span class="sccc-accordion__icon" aria-hidden="true">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"></path>
                          </svg>
                        </span>
                      </summary>

                      <div class="sccc-accordion__answer">
                        {!! wp_kses_post($item['answer']) !!}
                      </div>
                    </details>
                  </article>
                @endforeach
              </div>
            @endif
          </section>
        @endforeach
      </div>
    @else
      <div class="sccc-accordion__empty">
        <h3>No accordion items found yet</h3>
        <p>Add at least one section and one item to populate this block.</p>
      </div>
    @endif
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const accordions = document.querySelectorAll('[data-sccc-accordion]');

    accordions.forEach((accordion) => {
      const allowMultiple = accordion.getAttribute('data-allow-multiple') === 'true';

      if (allowMultiple) {
        return;
      }

      const detailsList = Array.from(accordion.querySelectorAll('[data-sccc-accordion-details]'));

      detailsList.forEach((detail) => {
        detail.addEventListener('toggle', () => {
          if (!detail.open) {
            return;
          }

          const section = detail.closest('[data-sccc-accordion-section]');

          if (!section) {
            return;
          }

          section.querySelectorAll('[data-sccc-accordion-details]').forEach((sibling) => {
            if (sibling !== detail) {
              sibling.open = false;
            }
          });
        });
      });
    });
  });
</script>