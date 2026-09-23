{{--
==============================================================================
File path + filename: resources/views/blocks/browse-tags-widget.blade.php
==============================================================================

Purpose:
- Render a sidebar-style tag browser widget that matches the supplied blog
  sidebar HTML pattern, but uses live WordPress post tags.

Why this file exists:
- The user wants the "Browse by Category" card converted into a reusable
  Gutenberg block that:
  - uses `post_tag` instead of categories
  - sorts tags by descending post count
  - shows the first 5 initially
  - lets visitors expand to reveal the remaining tags
  - remains readable in both light and dark themes
  - keeps dark mode visually very close to the supplied reference

Styling notes:
- All selectors are scoped to `.sccc-browse-tags-widget`.
- Dark mode supports both project theme signals:
  - [data-theme="dark"]
  - .dark
- The dark styling intentionally stays very close to the provided mockup.
- The expand/collapse behavior is handled with a tiny scoped script and
  per-instance unique IDs so multiple blocks can coexist safely.

Important:
- `$visibleTerms` and `$hiddenTerms` are already normalized in the block class.
- The count badges intentionally mirror the example hover behavior.
--}}

@once
  <style>
    /* ==========================================================================
       Browse Tags Widget block
       Fully scoped so it does not leak into the rest of the theme.
       ========================================================================== */

    .sccc-browse-tags-widget {
      --sccc-tags-bg: #ffffff;
      --sccc-tags-border: #e5e7eb;
      --sccc-tags-text: #111418;
      --sccc-tags-link: #4b5563;
      --sccc-tags-count-bg: #e5e7eb;
      --sccc-tags-count-text: #111418;
      --sccc-tags-hover: #13a4ec;
      --sccc-tags-hover-count-bg: rgba(19, 164, 236, 0.20);
      --sccc-tags-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
      width: 100%;
      margin-block: 1rem;
    }

    [data-theme="dark"] .sccc-browse-tags-widget,
    .dark .sccc-browse-tags-widget {
      --sccc-tags-bg: #16262e;
      --sccc-tags-border: #233c48;
      --sccc-tags-text: #ffffff;
      --sccc-tags-link: #9ca3af;
      --sccc-tags-count-bg: #111c22;
      --sccc-tags-count-text: #ffffff;
      --sccc-tags-hover: #13a4ec;
      --sccc-tags-hover-count-bg: rgba(19, 164, 236, 0.20);
      --sccc-tags-shadow: none;
    }

    .sccc-browse-tags-widget *,
    .sccc-browse-tags-widget *::before,
    .sccc-browse-tags-widget *::after {
      box-sizing: border-box;
    }

    .sccc-browse-tags-widget__card {
      border: 1px solid var(--sccc-tags-border);
      border-radius: 0.75rem;
      background: var(--sccc-tags-bg);
      box-shadow: var(--sccc-tags-shadow);
      padding: 1.5rem;
    }

    .sccc-browse-tags-widget__heading {
      margin: 0 0 1rem;
      color: var(--sccc-tags-text);
      font-size: 1.125rem;
      font-weight: 700;
      line-height: 1.3;
    }

    .sccc-browse-tags-widget__list,
    .sccc-browse-tags-widget__more-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .sccc-browse-tags-widget__list > li + li,
    .sccc-browse-tags-widget__more-list > li + li {
      margin-top: 0.75rem;
    }

    .sccc-browse-tags-widget__link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      color: var(--sccc-tags-link);
      text-decoration: none;
      transition: color 180ms ease;
    }

    .sccc-browse-tags-widget__link:hover,
    .sccc-browse-tags-widget__link:focus-visible {
      color: var(--sccc-tags-hover);
      text-decoration: none;
    }

    .sccc-browse-tags-widget__name {
      min-width: 0;
      font-size: 0.875rem;
      font-weight: 500;
      line-height: 1.4;
    }

    .sccc-browse-tags-widget__count {
      flex: 0 0 auto;
      padding: 0.125rem 0.5rem;
      border-radius: 9999px;
      background: var(--sccc-tags-count-bg);
      color: var(--sccc-tags-count-text);
      font-size: 0.75rem;
      font-weight: 500;
      line-height: 1.2;
      transition:
        background-color 180ms ease,
        color 180ms ease;
    }

    .sccc-browse-tags-widget__link:hover .sccc-browse-tags-widget__count,
    .sccc-browse-tags-widget__link:focus-visible .sccc-browse-tags-widget__count {
      background: var(--sccc-tags-hover-count-bg);
      color: var(--sccc-tags-hover);
    }

    .sccc-browse-tags-widget__more {
      margin-top: 0.75rem;
    }

    .sccc-browse-tags-widget__more[hidden] {
      display: none;
    }

    .sccc-browse-tags-widget__toggle {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      margin-top: 1rem;
      padding: 0;
      border: 0;
      background: transparent;
      color: var(--sccc-tags-hover);
      font-size: 0.875rem;
      font-weight: 700;
      line-height: 1.2;
      cursor: pointer;
      transition: opacity 180ms ease;
    }

    .sccc-browse-tags-widget__toggle:hover,
    .sccc-browse-tags-widget__toggle:focus-visible {
      opacity: 0.85;
    }

    .sccc-browse-tags-widget__toggle-icon {
      width: 1rem;
      height: 1rem;
      flex: 0 0 auto;
      transition: transform 180ms ease;
    }

    .sccc-browse-tags-widget__toggle[aria-expanded="true"] .sccc-browse-tags-widget__toggle-icon {
      transform: rotate(180deg);
    }

    .sccc-browse-tags-widget__empty {
      margin: 0;
      color: var(--sccc-tags-link);
      font-size: 0.875rem;
      line-height: 1.5;
    }

    .sccc-browse-tags-widget__preview-note {
      margin-top: 0.85rem;
      color: var(--sccc-tags-link);
      font-size: 0.75rem;
      line-height: 1.45;
    }
  </style>
@endonce

<section {{ $attributes->class('sccc-browse-tags-widget') }}>
  <div class="sccc-browse-tags-widget__card">
    <h4 class="sccc-browse-tags-widget__heading">{{ $heading }}</h4>

    @if (! empty($visibleTerms))
      <ul class="sccc-browse-tags-widget__list">
        @foreach ($visibleTerms as $term)
          <li>
            <a class="sccc-browse-tags-widget__link" href="{{ esc_url($term['url']) }}">
              <span class="sccc-browse-tags-widget__name">{{ $term['name'] }}</span>
              <span class="sccc-browse-tags-widget__count">{{ $term['count'] }}</span>
            </a>
          </li>
        @endforeach
      </ul>

      @if ($hasMoreTerms)
        <div class="sccc-browse-tags-widget__more" id="{{ esc_attr($toggleId) }}" hidden>
          <ul class="sccc-browse-tags-widget__more-list">
            @foreach ($hiddenTerms as $term)
              <li>
                <a class="sccc-browse-tags-widget__link" href="{{ esc_url($term['url']) }}">
                  <span class="sccc-browse-tags-widget__name">{{ $term['name'] }}</span>
                  <span class="sccc-browse-tags-widget__count">{{ $term['count'] }}</span>
                </a>
              </li>
            @endforeach
          </ul>
        </div>

        <button
          class="sccc-browse-tags-widget__toggle"
          type="button"
          aria-expanded="false"
          aria-controls="{{ esc_attr($toggleId) }}"
          data-sccc-tags-toggle
          data-label-more="Show All Tags"
          data-label-less="Show Fewer Tags"
        >
          <span data-sccc-tags-toggle-label>Show All Tags</span>
          <svg class="sccc-browse-tags-widget__toggle-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M6 9l6 6 6-6"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>
      @endif
    @else
      <p class="sccc-browse-tags-widget__empty">No tags are available yet.</p>
    @endif

    @if ($isPreview)
      <p class="sccc-browse-tags-widget__preview-note">
        This widget pulls live <strong>post tags</strong>, sorted from highest post count to lowest.
      </p>
    @endif
  </div>
</section>

@once
  <script>
    document.addEventListener('click', function (event) {
      const toggle = event.target.closest('[data-sccc-tags-toggle]');

      if (! toggle) {
        return;
      }

      const targetId = toggle.getAttribute('aria-controls');
      const target = targetId ? document.getElementById(targetId) : null;

      if (! target) {
        return;
      }

      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
      const nextState = ! isExpanded;
      const labelNode = toggle.querySelector('[data-sccc-tags-toggle-label]');
      const moreLabel = toggle.getAttribute('data-label-more') || 'Show All Tags';
      const lessLabel = toggle.getAttribute('data-label-less') || 'Show Fewer Tags';

      toggle.setAttribute('aria-expanded', nextState ? 'true' : 'false');
      target.hidden = ! nextState;

      if (labelNode) {
        labelNode.textContent = nextState ? lessLabel : moreLabel;
      }
    });
  </script>
@endonce