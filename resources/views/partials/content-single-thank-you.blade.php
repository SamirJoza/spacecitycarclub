{{--
|--------------------------------------------------------------------------
| partials/content-single-thank-you.blade.php
|--------------------------------------------------------------------------
| Hero for Thank You CPT: featured image background, optional ACF “Visual style”
| (nebula / aurora / signal). Post title is admin-only; headline + document title
| use “Thank you” (see filters in thank_you.php).
| Filters: sccc_thank_you_background_image_url, sccc_thank_you_badge_text,
|          sccc_thank_you_callout_title, sccc_thank_you_callout_text,
|          sccc_thank_you_secondary_cta_label, sccc_thank_you_secondary_cta_url,
|          sccc_thank_you_display_headline, sccc_thank_you_visual_variation
|--------------------------------------------------------------------------
--}}

@php
  $postId = get_the_ID();
  $bgUrl = apply_filters(
    'sccc_thank_you_background_image_url',
    get_the_post_thumbnail_url($postId, 'full'),
    $postId
  );
  $badge = apply_filters('sccc_thank_you_badge_text', __('Received', 'sccc'), $postId);
  $calloutTitle = apply_filters('sccc_thank_you_callout_title', __('What happens next', 'sccc'), $postId);
  $calloutDefault = __('We will follow up by email if any action is needed. You can always return to the site for news and events.', 'sccc');
  $calloutText = apply_filters('sccc_thank_you_callout_text', $calloutDefault, $postId);
  $secondaryLabel = apply_filters('sccc_thank_you_secondary_cta_label', __('Our sponsors', 'sccc'), $postId);
  $secondaryUrl = apply_filters('sccc_thank_you_secondary_cta_url', home_url('/sponsors/'), $postId);

  $variation = 'nebula';
  $metaVar = get_post_meta($postId, 'thank_you_visual_variation', true);
  if (is_string($metaVar) && in_array($metaVar, ['nebula', 'aurora', 'signal'], true)) {
    $variation = $metaVar;
  } elseif (function_exists('get_field')) {
    $acfVar = get_field('thank_you_visual_variation', $postId);
    if (is_string($acfVar) && in_array($acfVar, ['nebula', 'aurora', 'signal'], true)) {
      $variation = $acfVar;
    }
  }
  $variation = apply_filters('sccc_thank_you_visual_variation', $variation, $postId);
  if (! in_array($variation, ['nebula', 'aurora', 'signal'], true)) {
    $variation = 'nebula';
  }

  $orbitIcon = match ($variation) {
    'aurora' => 'auto_awesome',
    'signal' => 'bolt',
    default => 'verified',
  };

  $headline = apply_filters('sccc_thank_you_display_headline', __('Thank you', 'sccc'), $postId);
  $headWords = preg_split('/\s+/u', trim((string) $headline), -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $headLast = array_pop($headWords) ?: __('you', 'sccc');
  $headFirst = implode(' ', $headWords);

  $headAccentClass = match ($variation) {
    'aurora' => 'sccc-thank-you__head-accent sccc-thank-you__head-accent--aurora',
    'signal' => 'sccc-thank-you__head-accent sccc-thank-you__head-accent--signal',
    default => 'sccc-thank-you__head-accent text-gradient text-gradient--primary',
  };
@endphp

<div class="sccc-thank-you sccc-thank-you--{{ $variation }} relative flex flex-col flex-1 min-h-0 overflow-hidden">
  {{-- Background: featured image + vignette (see thank-you.css) --}}
  <div class="sccc-thank-you__bg absolute inset-0 z-0 pointer-events-none" aria-hidden="true">
    @if ($bgUrl)
      <img
        class="sccc-thank-you__bg-img"
        src="{{ esc_url($bgUrl) }}"
        alt=""
        decoding="async"
        fetchpriority="high"
      />
    @endif
    <div class="sccc-thank-you__bg-tint sccc-thank-you__bg-tint--{{ $variation }} absolute inset-0 z-[0] pointer-events-none" aria-hidden="true"></div>
    <div class="sccc-thank-you__bg-vignette absolute inset-0 z-[1]"></div>
  </div>

  <div class="sccc-thank-you__shell relative z-10 flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 py-12 sm:py-16 md:py-20">
    <div class="sccc-thank-you__grid grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 items-center">
      <div class="space-y-6 sm:space-y-8">
        <div class="sccc-thank-you__badge inline-flex items-center gap-3 px-4 py-2 rounded-full border border-line bg-surface/80 text-xs font-bold tracking-[0.18em] uppercase backdrop-blur-sm">
          <span class="relative flex h-2 w-2" aria-hidden="true">
            <span class="sccc-thank-you__badge-ping animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"></span>
            <span class="sccc-thank-you__badge-dot relative inline-flex rounded-full h-2 w-2"></span>
          </span>
          {{ esc_html($badge) }}
        </div>

        <h1 class="sccc-thank-you__heading font-display font-black text-4xl sm:text-5xl md:text-6xl lg:text-7xl tracking-tight leading-[1.05] text-text">
          @if ($headFirst !== '')
            {{ esc_html($headFirst) }}
            <span class="whitespace-nowrap"> </span>
          @endif
          <span class="{{ $headAccentClass }}">{{ esc_html($headLast) }}</span>
        </h1>

        @if (has_excerpt())
          <p class="text-muted text-lg md:text-xl leading-relaxed max-w-xl font-medium">
            {{ esc_html(get_the_excerpt()) }}
          </p>
        @endif

        <div class="sccc-thank-you__glass sccc-thank-you__glass-callout rounded-xl p-5 sm:p-6">
          <div class="flex gap-4">
            <span class="sccc-thank-you__callout-icon material-symbols-outlined shrink-0" style="font-variation-settings: 'FILL' 1;">mark_email_read</span>
            <p class="text-muted text-sm leading-snug">
              <span class="text-text font-bold block mb-1">{{ esc_html($calloutTitle) }}</span>
              {{ esc_html($calloutText) }}
            </p>
          </div>
        </div>

        @php
          $content = get_post_field('post_content', $postId);
        @endphp
        @if (trim((string) $content) !== '')
          <div class="sccc-thank-you__entry prose max-w-none text-text prose-headings:font-display prose-headings:text-text prose-p:text-muted prose-a:text-primary-500 prose-strong:text-text">
            @php(the_content())
          </div>
        @endif

        <div class="sccc-thank-you__cta-row flex flex-wrap items-center gap-3 sm:gap-4 pt-2">
          <a class="sccc-thank-you__btn" href="{{ esc_url(home_url('/')) }}">
            <span class="sccc-thank-you__btn-label">{{ __('Back to home', 'sccc') }}</span>
            <span class="sccc-thank-you__btn-fab" aria-hidden="true">
              <span class="material-symbols-outlined">arrow_forward</span>
            </span>
          </a>
          <a class="sccc-thank-you__btn sccc-thank-you__btn--secondary" href="{{ esc_url($secondaryUrl) }}">
            <span class="sccc-thank-you__btn-label">{{ esc_html($secondaryLabel) }}</span>
            <span class="sccc-thank-you__btn-fab sccc-thank-you__btn-fab--secondary" aria-hidden="true">
              <span class="material-symbols-outlined">chevron_right</span>
            </span>
          </a>
        </div>
      </div>

      <div class="sccc-thank-you__media-col hidden lg:flex justify-center relative py-6">
        <div class="w-full aspect-square max-w-md relative">
          <div class="sccc-thank-you__kinetic absolute inset-0 z-0 rounded-full pointer-events-none" aria-hidden="true"></div>
          <div class="sccc-thank-you__radar-sweep absolute inset-0 z-[1] rounded-full pointer-events-none" aria-hidden="true"></div>
          <div class="absolute inset-0 z-[2] flex items-center justify-center">
            <div class="sccc-thank-you__orbit relative w-56 h-56 sm:w-64 sm:h-64 rounded-full flex items-center justify-center border sccc-thank-you__glass">
              <div class="sccc-thank-you__orbit-ping absolute inset-0 rounded-full border-2 animate-[ping_3s_linear_infinite]" aria-hidden="true"></div>
              <div class="sccc-thank-you__orbit-pulse absolute inset-0 rounded-full border animate-pulse" aria-hidden="true"></div>
              <span class="sccc-thank-you__orbit-icon material-symbols-outlined text-[4.5rem] sm:text-[5rem]" style="font-variation-settings: 'FILL' 1;">{{ $orbitIcon }}</span>
            </div>
          </div>
          <div class="absolute top-6 right-0 sccc-thank-you__glass px-3 py-2.5 rounded-lg border border-line flex items-center gap-2 shadow-elev text-[10px] font-bold tracking-widest uppercase text-text">
            <span class="sccc-thank-you__chip-icon material-symbols-outlined text-lg">database</span>
            <span>{{ __('Logged', 'sccc') }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
