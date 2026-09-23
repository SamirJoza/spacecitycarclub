{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/faq/faq-index.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the FAQ module: search, category navigation, grouped sections,
|   accordion items, optional sidebar widget area, and no-results state.
|
| Notes:
| - All selectors are scoped to `.sccc-faq-scope` only.
| - Mobile:
|   - Search stays in normal flow.
|   - Category card stays sticky.
|   - Widget area is moved below the FAQ content.
| - Desktop:
|   - The left rail stays sticky.
|   - The widget area lives inside the sticky rail below the categories.
| - Only one accordion item can be open at a time within each section.
| - Desktop rail no longer has any internal scrolling.
|--------------------------------------------------------------------------
--}}

@php
  $faqHasSidebarWidget = function_exists('is_active_sidebar') && is_active_sidebar(\App\Support\Content\Faq::SIDEBAR);
@endphp

<style>
  .sccc-faq-scope {
    overflow-x: clip;
  }

  .sccc-faq-scope .sccc-faq-module,
  .sccc-faq-scope .sccc-faq-module__rail,
  .sccc-faq-scope .sccc-faq-module__content,
  .sccc-faq-scope .sccc-faq-module__widget-mobile,
  .sccc-faq-scope .sccc-faq-module__nav-card,
  .sccc-faq-scope .sccc-faq-module__widget-area,
  .sccc-faq-scope .sccc-faq-widget,
  .sccc-faq-scope .sccc-faq-module__item {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
  }

  .sccc-faq-scope .sccc-faq-module {
    display: grid;
    gap: 2rem;
    align-items: start;
  }

  @media (min-width: 1024px) {
    .sccc-faq-scope .sccc-faq-module {
      grid-template-columns: minmax(15rem, 18rem) minmax(0, 1fr);
      gap: 3rem;
    }
  }

  .sccc-faq-scope .sccc-faq-module__rail {
    display: grid;
    gap: 1.25rem;
    align-self: start;
  }

  @media (min-width: 1024px) {
    .sccc-faq-scope .sccc-faq-module__rail {
      position: sticky;
      top: 7.5rem;
    }
  }

  .sccc-faq-scope .sccc-faq-module__content {
    display: grid;
    gap: 2.75rem;
    min-width: 0;
  }

  .sccc-faq-scope .sccc-faq-module__search {
    position: relative;
    min-width: 0;
    max-width: 100%;
  }

  .sccc-faq-scope .sccc-faq-module__search input {
    width: 100%;
    max-width: 100%;
    border: 1px solid var(--faq-border-light);
    border-radius: 1rem;
    background: var(--faq-surface-light-strong);
    color: var(--faq-text-light);
    padding: 1rem 1rem 1rem 3rem;
    box-shadow: var(--faq-shadow-light);
    outline: none;
    transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
    box-sizing: border-box;
  }

  .sccc-faq-scope .sccc-faq-module__search input:focus {
    border-color: rgba(19, 91, 236, 0.35);
    box-shadow: 0 0 0 4px var(--faq-ring-light), var(--faq-shadow-light);
  }

  .sccc-faq-scope .sccc-faq-module__search-icon {
    position: absolute;
    top: 33%;
    left: 1rem;
    transform: translateY(-50%);
    color: #7a8598;
    pointer-events: none;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__search input,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__search input {
    border-color: var(--faq-border-dark);
    background: var(--faq-surface-dark-strong);
    color: var(--faq-text-dark);
    box-shadow: var(--faq-shadow-dark);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__search input:focus,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__search input:focus {
    border-color: rgba(96, 165, 250, 0.45);
    box-shadow: 0 0 0 4px var(--faq-ring-dark), var(--faq-shadow-dark);
  }

  .sccc-faq-scope .sccc-faq-module__search-help {
    margin: 0.75rem 0 0;
    font-size: 0.88rem;
    color: var(--faq-muted-light);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__search-help,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__search-help {
    color: var(--faq-muted-dark);
  }

  .sccc-faq-scope .sccc-faq-module__nav-card,
  .sccc-faq-scope .sccc-faq-module__item,
  .sccc-faq-scope .sccc-faq-module__no-results,
  .sccc-faq-scope .sccc-faq-module__widget-area,
  .sccc-faq-scope .sccc-faq-widget {
    border: 1px solid var(--faq-border-light);
    background: var(--faq-surface-light);
    box-shadow: var(--faq-shadow-light);
    backdrop-filter: blur(12px);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__nav-card,
  html.dark .sccc-faq-scope .sccc-faq-module__item,
  html.dark .sccc-faq-scope .sccc-faq-module__no-results,
  html.dark .sccc-faq-scope .sccc-faq-module__widget-area,
  html.dark .sccc-faq-scope .sccc-faq-widget,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__nav-card,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__item,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__no-results,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__widget-area,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-widget {
    border-color: var(--faq-border-dark);
    background: var(--faq-surface-dark);
    box-shadow: var(--faq-shadow-dark);
  }

  .sccc-faq-scope .sccc-faq-module__nav-card {
    border-radius: 1.25rem;
    padding: 1.1rem;
    overflow: hidden;
    z-index: 15;
  }

  @media (max-width: 1023.98px) {
    .sccc-faq-scope .sccc-faq-module__nav-card {
      position: sticky;
      top: 4.5rem;
    }
  }

  .sccc-faq-scope .sccc-faq-module__nav-label {
    margin: 0 0 0.9rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--faq-muted-light);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__nav-label,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__nav-label {
    color: var(--faq-muted-dark);
  }

  .sccc-faq-scope .sccc-faq-module__nav {
    display: flex;
    gap: 0.65rem;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 0.2rem;
    scrollbar-width: none;
    box-sizing: border-box;
  }

  .sccc-faq-scope .sccc-faq-module__nav::-webkit-scrollbar {
    display: none;
  }

  @media (min-width: 1024px) {
    .sccc-faq-scope .sccc-faq-module__nav {
      display: grid;
      overflow: visible;
    }
  }

  .sccc-faq-scope .sccc-faq-module__nav-link {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    min-width: max-content;
    flex: 0 0 auto;
    padding: 0.92rem 1rem;
    border: 1px solid var(--faq-border-light);
    border-radius: 0.95rem;
    background: rgba(255, 255, 255, 0.75);
    color: var(--faq-muted-light);
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    transition: border-color 0.25s ease, color 0.25s ease, background-color 0.25s ease, transform 0.25s ease;
    box-sizing: border-box;
  }

  .sccc-faq-scope .sccc-faq-module__nav-link:hover,
  .sccc-faq-scope .sccc-faq-module__nav-link.is-active {
    border-color: rgba(19, 91, 236, 0.22);
    background: var(--faq-primary-soft-light);
    color: var(--faq-primary-light);
    transform: translateY(-1px);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__nav-link,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__nav-link {
    border-color: var(--faq-border-dark);
    background: rgba(255, 255, 255, 0.02);
    color: var(--faq-muted-dark);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__nav-link:hover,
  html.dark .sccc-faq-scope .sccc-faq-module__nav-link.is-active,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__nav-link:hover,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__nav-link.is-active {
    border-color: rgba(96, 165, 250, 0.3);
    background: var(--faq-primary-soft-dark);
    color: var(--faq-primary-strong-dark);
  }

  .sccc-faq-scope .sccc-faq-module__nav-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.8rem;
    height: 1.8rem;
    padding-inline: 0.45rem;
    border-radius: 9999px;
    background: rgba(15, 23, 42, 0.06);
    font-size: 0.74rem;
    font-weight: 800;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__nav-count,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__nav-count {
    background: rgba(255, 255, 255, 0.08);
  }

  .sccc-faq-scope .sccc-faq-module__widget-desktop {
    display: none;
  }

  @media (min-width: 1024px) {
    .sccc-faq-scope .sccc-faq-module__widget-desktop {
      display: block;
    }
  }

  .sccc-faq-scope .sccc-faq-module__widget-mobile {
    display: block;
  }

  @media (min-width: 1024px) {
    .sccc-faq-scope .sccc-faq-module__widget-mobile {
      display: none;
    }
  }

  .sccc-faq-scope .sccc-faq-module__widget-area {
    border-radius: 1.25rem;
    padding: 1.1rem;
    overflow: hidden;
  }

  .sccc-faq-scope .sccc-faq-module__widget-area .sccc-faq-widget + .sccc-faq-widget {
    margin-top: 1rem;
  }

  .sccc-faq-scope .sccc-faq-widget {
    border-radius: 1rem;
    padding: 1rem;
    overflow: hidden;
  }

  .sccc-faq-scope .sccc-faq-widget__title,
  .sccc-faq-scope .sccc-faq-widget .wp-block-heading {
    margin: 0 0 0.75rem;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.3;
    color: var(--faq-text-light);
  }

  html.dark .sccc-faq-scope .sccc-faq-widget__title,
  html.dark .sccc-faq-scope .sccc-faq-widget .wp-block-heading,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-widget__title,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-widget .wp-block-heading {
    color: var(--faq-text-dark);
  }

  .sccc-faq-scope .sccc-faq-widget :where(p, li) {
    color: var(--faq-muted-light);
    line-height: 1.75;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  html.dark .sccc-faq-scope .sccc-faq-widget :where(p, li),
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-widget :where(p, li) {
    color: var(--faq-muted-dark);
  }

  .sccc-faq-scope .sccc-faq-widget a {
    color: var(--faq-primary-light);
    text-decoration: none;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  html.dark .sccc-faq-scope .sccc-faq-widget a,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-widget a {
    color: var(--faq-primary-dark);
  }

  .sccc-faq-scope .sccc-faq-widget :where(img, svg, canvas, iframe, video) {
    display: block;
    max-width: 100%;
    height: auto;
  }

  .sccc-faq-scope .sccc-faq-widget :where(.wp-block-group, .wp-block-cover, .wp-block-columns, .wp-block-column) {
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
  }

  .sccc-faq-scope .sccc-faq-widget :where(table, .wp-block-table table, .wp-block-calendar table) {
    width: 100% !important;
    max-width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
  }

  .sccc-faq-scope .sccc-faq-widget :where(th, td) {
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  .sccc-faq-scope .sccc-faq-widget .wp-block-calendar {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
  }

  .sccc-faq-scope .sccc-faq-module__section {
    scroll-margin-top: 8rem;
    min-width: 0;
  }

  .sccc-faq-scope .sccc-faq-module__section-head {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    min-width: 0;
  }

  .sccc-faq-scope .sccc-faq-module__section-head h2 {
    margin: 0;
    font-size: clamp(1.6rem, 2vw, 2rem);
    line-height: 1.1;
    font-weight: 800;
    color: var(--faq-text-light);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__section-head h2,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__section-head h2 {
    color: var(--faq-text-dark);
  }

  .sccc-faq-scope .sccc-faq-module__section-line {
    flex: 1;
    height: 1px;
    background: var(--faq-border-light);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__section-line,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__section-line {
    background: var(--faq-border-dark);
  }

  .sccc-faq-scope .sccc-faq-module__section-description {
    margin: 0 0 1.35rem;
    max-width: 46rem;
    color: var(--faq-muted-light);
    line-height: 1.8;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__section-description,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__section-description {
    color: var(--faq-muted-dark);
  }

  .sccc-faq-scope .sccc-faq-module__items {
    display: grid;
    gap: 1rem;
    min-width: 0;
  }

  .sccc-faq-scope .sccc-faq-module__item {
    border-radius: 1.25rem;
    overflow: hidden;
    transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
  }

  .sccc-faq-scope .sccc-faq-module__item:hover {
    transform: translateY(-2px);
    border-color: rgba(19, 91, 236, 0.18);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__item:hover,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__item:hover {
    border-color: rgba(96, 165, 250, 0.24);
  }

  .sccc-faq-scope .sccc-faq-module details > summary {
    list-style: none;
  }

  .sccc-faq-scope .sccc-faq-module details > summary::-webkit-details-marker {
    display: none;
  }

  .sccc-faq-scope .sccc-faq-module__summary {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    padding: 1.35rem 1.35rem 1.2rem;
    cursor: pointer;
    box-sizing: border-box;
  }

  .sccc-faq-scope .sccc-faq-module__question {
    font-size: 1.06rem;
    line-height: 1.6;
    font-weight: 700;
    color: var(--faq-text-light);
    min-width: 0;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__question,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__question {
    color: var(--faq-text-dark);
  }

  .sccc-faq-scope .sccc-faq-module__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border: 1px solid var(--faq-border-light);
    border-radius: 9999px;
    color: #728099;
    background: rgba(255, 255, 255, 0.75);
    transition: transform 0.25s ease, color 0.25s ease, border-color 0.25s ease, background-color 0.25s ease;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__icon,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__icon {
    border-color: var(--faq-border-dark);
    background: rgba(255, 255, 255, 0.03);
    color: #aab5c8;
  }

  .sccc-faq-scope .sccc-faq-module details[open] .sccc-faq-module__icon {
    transform: rotate(180deg);
    color: var(--faq-primary-light);
    border-color: rgba(19, 91, 236, 0.2);
    background: var(--faq-primary-soft-light);
  }

  html.dark .sccc-faq-scope .sccc-faq-module details[open] .sccc-faq-module__icon,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module details[open] .sccc-faq-module__icon {
    color: var(--faq-primary-strong-dark);
    border-color: rgba(96, 165, 250, 0.24);
    background: var(--faq-primary-soft-dark);
  }

  .sccc-faq-scope .sccc-faq-module__answer {
    padding: 0 1.35rem 1.35rem;
    color: var(--faq-muted-light);
    line-height: 1.85;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__answer,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__answer {
    color: var(--faq-muted-dark);
  }

  .sccc-faq-scope .sccc-faq-module__answer > :first-child {
    margin-top: 0;
  }

  .sccc-faq-scope .sccc-faq-module__answer > :last-child {
    margin-bottom: 0;
  }

  .sccc-faq-scope .sccc-faq-module__answer a {
    color: var(--faq-primary-light);
    text-decoration: none;
    border-bottom: 1px solid currentColor;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__answer a,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__answer a {
    color: var(--faq-primary-dark);
  }

  .sccc-faq-scope .sccc-faq-module__no-results {
    display: none;
    border-radius: 1.25rem;
    padding: 2.4rem 1.5rem;
    text-align: center;
  }

  .sccc-faq-scope .sccc-faq-module__no-results.is-visible {
    display: block;
  }

  .sccc-faq-scope .sccc-faq-module__no-results h3 {
    margin: 0 0 0.5rem;
    font-size: 1.55rem;
    font-weight: 800;
    color: var(--faq-text-light);
  }

  .sccc-faq-scope .sccc-faq-module__no-results p {
    margin: 0;
    color: var(--faq-muted-light);
    line-height: 1.8;
  }

  html.dark .sccc-faq-scope .sccc-faq-module__no-results h3,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__no-results h3 {
    color: var(--faq-text-dark);
  }

  html.dark .sccc-faq-scope .sccc-faq-module__no-results p,
  [data-theme="dark"] .sccc-faq-scope .sccc-faq-module__no-results p {
    color: var(--faq-muted-dark);
  }

  .sccc-faq-scope [hidden] {
    display: none !important;
  }
</style>

<section class="sccc-faq-module" data-faq-module>
  <aside class="sccc-faq-module__rail">
    <div class="sccc-faq-module__search">
      <span class="sccc-faq-module__search-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7"></circle>
          <path d="m20 20-3.5-3.5"></path>
        </svg>
      </span>

      <input
        type="search"
        data-faq-search
        placeholder="{{ $faqSearchPlaceholder }}"
        aria-label="Search FAQ"
      />

      <p class="sccc-faq-module__search-help">Start typing to filter questions instantly.</p>
    </div>

    <div class="sccc-faq-module__nav-card">
      <p class="sccc-faq-module__nav-label">Categories</p>

      <nav class="sccc-faq-module__nav" aria-label="FAQ categories">
        @foreach ($faqSections as $section)
          <a
            class="sccc-faq-module__nav-link"
            href="#{{ $section['anchor'] }}"
            data-faq-nav-link
          >
            <span>{!! $section['label'] !!}</span>
            <span class="sccc-faq-module__nav-count">{!! $section['count'] !!}</span>
          </a>
        @endforeach
      </nav>
    </div>

    @if ($faqHasSidebarWidget)
      <div class="sccc-faq-module__widget-desktop" data-faq-widget-host="desktop"></div>
    @endif
  </aside>

  <div class="sccc-faq-module__content" data-faq-results>
    @if ($faqHasContent)
      @foreach ($faqSections as $section)
        <section
          id="{{ $section['anchor'] }}"
          class="sccc-faq-module__section"
          data-faq-section
        >
          <div class="sccc-faq-module__section-head">
            <h2>{!! $section['label'] !!}</h2>
            <span class="sccc-faq-module__section-line" aria-hidden="true"></span>
          </div>

          @if (! empty($section['description']))
            <p class="sccc-faq-module__section-description">{!! $section['description'] !!}</p>
          @endif

          <div class="sccc-faq-module__items">
            @foreach ($section['items'] as $item)
              <article
                class="sccc-faq-module__item"
                data-faq-item
                data-faq-search-text="{{ strtolower(trim($item['question'].' '.$item['answer_text'].' '.$section['label'])) }}"
              >
                <details data-faq-details @if ($loop->first) open @endif>
                  <summary class="sccc-faq-module__summary">
                    <span class="sccc-faq-module__question">{!! $item['question'] !!}</span>
                    <span class="sccc-faq-module__icon" aria-hidden="true">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m6 9 6 6 6-6"></path>
                      </svg>
                    </span>
                  </summary>

                  <div class="sccc-faq-module__answer">
                    {!! $item['answer_html'] !!}
                  </div>
                </details>
              </article>
            @endforeach
          </div>
        </section>
      @endforeach
    @else
      <div class="sccc-faq-module__no-results is-visible">
        <h3>No FAQs found yet</h3>
        <p>Create FAQ categories and publish FAQ entries to populate this section.</p>
      </div>
    @endif

    <div class="sccc-faq-module__no-results" data-faq-no-results>
      <h3>No matching questions found</h3>
      <p>Try a different keyword or browse one of the available categories.</p>
    </div>
  </div>

  @if ($faqHasSidebarWidget)
    <div class="sccc-faq-module__widget-mobile" data-faq-widget-host="mobile"></div>

    <template data-faq-widget-template>
      <div class="sccc-faq-module__widget-area" aria-label="FAQ sidebar panel">
        @php(dynamic_sidebar(\App\Support\Content\Faq::SIDEBAR))
      </div>
    </template>
  @endif
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.sccc-faq-scope');

    if (!root) {
      return;
    }

    const input = root.querySelector('[data-faq-search]');
    const items = Array.from(root.querySelectorAll('[data-faq-item]'));
    const sections = Array.from(root.querySelectorAll('[data-faq-section]'));
    const navLinks = Array.from(root.querySelectorAll('[data-faq-nav-link]'));
    const noResults = root.querySelector('[data-faq-no-results]');
    const detailsList = Array.from(root.querySelectorAll('[data-faq-details]'));
    const widgetTemplate = root.querySelector('[data-faq-widget-template]');
    const desktopWidgetHost = root.querySelector('[data-faq-widget-host="desktop"]');
    const mobileWidgetHost = root.querySelector('[data-faq-widget-host="mobile"]');
    const desktopMedia = window.matchMedia('(min-width: 1024px)');

    let widgetNode = null;

    const normalize = (value) => (value || '').toString().toLowerCase().trim();

    const updateFilter = () => {
      const query = normalize(input ? input.value : '');
      let visibleCount = 0;

      items.forEach((item) => {
        const haystack = normalize(item.getAttribute('data-faq-search-text'));
        const match = query === '' || haystack.includes(query);

        item.hidden = !match;

        if (match) {
          visibleCount += 1;
        }
      });

      sections.forEach((section) => {
        const hasVisibleItems = section.querySelectorAll('[data-faq-item]:not([hidden])').length > 0;
        section.hidden = !hasVisibleItems;
      });

      navLinks.forEach((link) => {
        const targetId = (link.getAttribute('href') || '').replace('#', '');
        const target = targetId ? root.querySelector(`#${CSS.escape(targetId)}`) : null;
        link.hidden = !target || target.hidden;
      });

      if (noResults) {
        noResults.classList.toggle('is-visible', visibleCount === 0 && items.length > 0);
      }
    };

    const placeWidget = () => {
      if (!widgetTemplate || (!desktopWidgetHost && !mobileWidgetHost)) {
        return;
      }

      if (!widgetNode) {
        const templateFirstChild = widgetTemplate.content.firstElementChild;

        if (!templateFirstChild) {
          return;
        }

        widgetNode = templateFirstChild.cloneNode(true);
      }

      const targetHost = desktopMedia.matches ? desktopWidgetHost : mobileWidgetHost;

      if (!targetHost) {
        return;
      }

      if (widgetNode.parentNode !== targetHost) {
        targetHost.replaceChildren(widgetNode);
      }
    };

    detailsList.forEach((detail) => {
      detail.addEventListener('toggle', () => {
        if (!detail.open) {
          return;
        }

        const section = detail.closest('[data-faq-section]');

        if (!section) {
          return;
        }

        section.querySelectorAll('[data-faq-details]').forEach((sibling) => {
          if (sibling !== detail) {
            sibling.open = false;
          }
        });
      });
    });

    if (input) {
      input.addEventListener('input', updateFilter);
      input.addEventListener('search', updateFilter);
    }

    if (typeof desktopMedia.addEventListener === 'function') {
      desktopMedia.addEventListener('change', placeWidget);
    } else if (typeof desktopMedia.addListener === 'function') {
      desktopMedia.addListener(placeWidget);
    }

    placeWidget();
    updateFilter();

    const visibleSections = () => sections.filter((section) => !section.hidden);

    const setActiveLink = () => {
      if (!navLinks.length) {
        return;
      }

      const current = visibleSections().find((section) => {
        const rect = section.getBoundingClientRect();
        return rect.top <= 170 && rect.bottom > 170;
      }) || visibleSections()[0];

      navLinks.forEach((link) => {
        const targetId = (link.getAttribute('href') || '').replace('#', '');
        link.classList.toggle('is-active', !!current && current.id === targetId);
      });
    };

    setActiveLink();
    window.addEventListener('scroll', setActiveLink, { passive: true });
  });
</script>