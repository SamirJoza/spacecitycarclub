{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/sections/newsletter.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the newsletter sign-up section between </main> and the footer.
| - Respect two layers of visibility control from the ACF Options sub-page:
|     1. Master switch (newsletter_enabled) — global kill-switch.
|     2. Archive selector (newsletter_show_on) — per-page-type control.
|
| Visibility logic (in order):
|   1. If newsletter_enabled is falsy → render nothing, return early.
|   2. Detect the current WordPress page/archive type using conditional tags.
|   3. If the current type is not in the newsletter_show_on selection → render nothing.
|   4. Otherwise → render the full section.
|
| Archive type detection:
| - Each string key in $archiveTypeMap corresponds to one checkbox choice
|   registered in app/Options/ThemeSettings.php.
| - WooCommerce conditional tags (is_shop, is_product_category) are guarded
|   with function_exists() so the partial does not fatal on sites where
|   WooCommerce is deactivated.
| - The events post type slug is 'events' — update the
|   is_post_type_archive() call if your project uses a different slug.
|
| Included from:
| - resources/views/layouts/blog.blade.php   — blog index
| - resources/views/layouts/single.blade.php — single blog posts
| - resources/views/layouts/app.blade.php    — all other page types
|
| Form behaviour:
| - Renders a real <form> only when a form action URL is configured in Options.
| - Omits the form entirely when the URL is blank (prevents a broken form
|   going live before the endpoint is connected).
|--------------------------------------------------------------------------
--}}

@php
  // ─── Step 1: Read the master switch ─────────────────────────────────────
  $newsletterEnabled = get_field('newsletter_enabled', 'option');
@endphp

{{-- Kill-switch: bail immediately if the section is globally disabled. --}}
@if (! $newsletterEnabled)
  {{-- Render nothing. --}}
@else
@php
  // ─── Step 2: Read the archive page selector ──────────────────────────────

  /*
   * newsletter_show_on is a checkbox field that returns an array of
   * string keys, e.g. ['single_post', 'blog_index', 'blog_category'].
   * Cast to array in case ACF returns false on a fresh install before
   * the options page has been saved for the first time.
   */
  $showOn = (array) (get_field('newsletter_show_on', 'option') ?: []);

  // ─── Step 3: Detect current WordPress page type ──────────────────────────

  /*
   * Map each checkbox value to its WordPress conditional tag result.
   *
   * single_post: true on any single published post of post_type 'post'.
   * is_singular('post') is used rather than is_single() alone to avoid
   * matching single WooCommerce products or other custom post types.
   *
   * WooCommerce functions are guarded with function_exists() so this
   * partial does not fatal-error when WooCommerce is inactive.
   *
   * ASSUMPTION: events post type slug is 'events'.
   * Update the is_post_type_archive() argument if your slug differs.
   */
  $archiveTypeMap = [
    // Single blog post — checked against is_singular('post') so it only
    // matches standard WordPress posts, not products or other CPTs.
    'single_post'          => is_singular('post'),

    // Blog archives
    'blog_index'           => is_home(),
    'blog_category'        => is_category(),
    'blog_tag'             => is_tag(),
    'blog_author'          => is_author(),
    'blog_date'            => is_date(),

    // WooCommerce (guarded — safe when WooCommerce is deactivated)
    'woo_shop'             => function_exists('is_shop') && is_shop(),
    'woo_product_category' => function_exists('is_product_category') && is_product_category(),

    // Events for WooCommerce
    'events_archive'       => is_post_type_archive('events'),
  ];

  /*
   * Check whether ANY selected checkbox key is true for the current page.
   * If the current page type matches at least one selected key, show the section.
   */
  $shouldShowOnCurrentPage = false;

  foreach ($showOn as $key) {
    if (! empty($archiveTypeMap[$key])) {
      $shouldShowOnCurrentPage = true;
      break;
    }
  }
@endphp

  {{-- Step 4: Only render if the current page type is selected. --}}
  @if ($shouldShowOnCurrentPage)
  @php
    // ─── Read all content fields ─────────────────────────────────────────
    // Fallbacks mirror the default_value set in ThemeSettings.php so the
    // section renders correctly before the first options save.
    $newsletterHeading    = get_field('newsletter_heading',      'option') ?: __('Stay in the fast lane', 'sage');
    $newsletterBody       = get_field('newsletter_body',         'option') ?: __('Subscribe to our newsletter to get the latest event announcements, member exclusive deals, and automotive news delivered straight to your inbox.', 'sage');
    $newsletterBtnLabel   = get_field('newsletter_button_label', 'option') ?: __('Subscribe', 'sage');
    $newsletterFormAction = get_field('newsletter_form_action',  'option');
    $newsletterDisclaimer = get_field('newsletter_disclaimer',   'option') ?: __('No spam, just cars. Unsubscribe at any time.', 'sage');
  @endphp

  <section class="sccc-newsletter" aria-label="{{ esc_attr__('Newsletter sign-up', 'sage') }}">
    <div class="sccc-newsletter__inner">

      {{-- Mail icon — inline SVG, no plugin dependency. --}}
      <span class="sccc-newsletter__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <rect x="2" y="4" width="20" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
          <path d="m2 7 10 7 10-7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
      </span>

      {{-- Heading --}}
      <h2 class="sccc-newsletter__heading">
        {{ $newsletterHeading }}
      </h2>

      {{-- Body copy. nl2br preserves line breaks from the textarea. --}}
      @if (! empty($newsletterBody))
        <p class="sccc-newsletter__body">
          {!! nl2br(esc_html($newsletterBody)) !!}
        </p>
      @endif

      {{--
        Form: only rendered when a form action URL is configured.
        Without a valid endpoint, hiding the form is safer than rendering
        a broken one that POSTs back to the current page.
        EMAIL is the standard MailChimp field name — adjust to your provider.
      --}}
      @if (! empty($newsletterFormAction))
        <form
          class="sccc-newsletter__form"
          action="{{ esc_url($newsletterFormAction) }}"
          method="post"
        >
          {{-- Honeypot: basic spam deterrent, hidden from real users. --}}
          <div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">
            <input type="text" name="b_honeypot" tabindex="-1" value="" autocomplete="off">
          </div>

          <label class="screen-reader-text" for="sccc-newsletter-email">
            {{ __('Email address', 'sage') }}
          </label>

          <input
            id="sccc-newsletter-email"
            class="sccc-newsletter__input"
            type="email"
            name="EMAIL"
            placeholder="{{ esc_attr__('Enter your email address', 'sage') }}"
            required
          >

          <button class="sccc-newsletter__btn" type="submit">
            {{ $newsletterBtnLabel }}
          </button>
        </form>
      @endif

      {{-- Disclaimer / small print --}}
      @if (! empty($newsletterDisclaimer))
        <p class="sccc-newsletter__disclaimer">
          {{ $newsletterDisclaimer }}
        </p>
      @endif

    </div>
  </section>

  @endif {{-- $shouldShowOnCurrentPage --}}

@endif {{-- $newsletterEnabled --}}