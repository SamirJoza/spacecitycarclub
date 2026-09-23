<?php
/**
 * Template Name: FAQ Layout
 * Template Post Type: page
 */
?>

{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/template-faq.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Reusable FAQ page template.
| - Render normal page editor content completely untouched.
| - Render FAQ layout and styles only inside `.sccc-faq-scope`.
|
| Important:
| - No template CSS targets the page content area.
| - No shared container styles are applied to the editor content section.
| - Width, spacing, colors, and layout rules are applied only inside
|   `.sccc-faq-scope` so Gutenberg/block styles remain intact above.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('content')
  @php
    if (have_posts()) {
        the_post();
    }

    $faqSections = $faqSections ?? [];
    $faqHasContent = $faqHasContent ?? ! empty($faqSections);
    $faqSearchPlaceholder = $faqSearchPlaceholder ?? "Search for 'membership', 'events', 'refunds'...";
    $faqHelp = $faqHelp ?? [
        'heading' => 'Still have questions?',
        'copy' => 'Can’t find the answer you’re looking for? Reach out to our team directly and we’ll point you in the right direction.',
        'primary' => [
            'title' => 'Contact Support',
            'url' => home_url('/contact/'),
            'target' => '',
        ],
        'secondary' => [
            'title' => 'Visit Events',
            'url' => home_url('/events/'),
            'target' => '',
        ],
    ];
  @endphp

  @if (trim((string) get_the_content()) !== '')
    @php the_content(); @endphp
  @endif

  <section class="sccc-faq-scope">
    <style>
      /* =========================================================================
         File path + filename: resources/views/template-faq.blade.php
         FAQ layout styles only.
         IMPORTANT:
         - Nothing outside `.sccc-faq-scope` is styled here.
         ========================================================================= */

      .sccc-faq-scope {
        --faq-bg-light: #f6f7fb;
        --faq-surface-light: rgba(255, 255, 255, 0.9);
        --faq-surface-light-strong: #ffffff;
        --faq-text-light: #0f172a;
        --faq-muted-light: #5b6472;
        --faq-border-light: rgba(15, 23, 42, 0.1);
        --faq-ring-light: rgba(19, 91, 236, 0.12);
        --faq-shadow-light: 0 18px 45px rgba(15, 23, 42, 0.08);
        --faq-primary-light: #135bec;
        --faq-primary-soft-light: rgba(19, 91, 236, 0.08);
        --faq-primary-strong-light: #0e45b8;

        --faq-bg-dark: #0d1420;
        --faq-surface-dark: rgba(26, 35, 50, 0.8);
        --faq-surface-dark-strong: #1a2332;
        --faq-text-dark: #edf3ff;
        --faq-muted-dark: #aab5c8;
        --faq-border-dark: rgba(255, 255, 255, 0.08);
        --faq-ring-dark: rgba(96, 165, 250, 0.16);
        --faq-shadow-dark: 0 22px 55px rgba(0, 0, 0, 0.35);
        --faq-primary-dark: #60a5fa;
        --faq-primary-soft-dark: rgba(96, 165, 250, 0.12);
        --faq-primary-strong-dark: #8ec5ff;

        position: relative;
        background:
          radial-gradient(38rem 26rem at 8% 8%, rgba(19, 91, 236, 0.12), transparent 62%),
          radial-gradient(42rem 28rem at 92% 18%, rgba(110, 90, 255, 0.1), transparent 64%),
          var(--faq-bg-light);
        color: var(--faq-text-light);
        padding: clamp(2rem, 5vw, 4rem) 0 0;
      }

      html.dark .sccc-faq-scope,
      [data-theme="dark"] .sccc-faq-scope {
        background:
          radial-gradient(42rem 26rem at 8% 8%, rgba(19, 91, 236, 0.18), transparent 62%),
          radial-gradient(46rem 30rem at 92% 18%, rgba(83, 136, 255, 0.12), transparent 64%),
          var(--faq-bg-dark);
        color: var(--faq-text-dark);
      }

      .sccc-faq-scope__main,
      .sccc-faq-scope__help-wrap {
        width: min(100%, 80rem);
        margin-inline: auto;
        padding-inline: 1rem;
      }

      @media (min-width: 640px) {
        .sccc-faq-scope__main,
        .sccc-faq-scope__help-wrap {
          padding-inline: 1.5rem;
        }
      }

      @media (min-width: 1024px) {
        .sccc-faq-scope__main,
        .sccc-faq-scope__help-wrap {
          padding-inline: 2rem;
        }
      }

      .sccc-faq-scope__main {
        position: relative;
        z-index: 1;
        padding-bottom: clamp(4rem, 7vw, 6rem);
      }
    </style>

    <main class="sccc-faq-scope__main">
      @include('partials.faq.faq-index', [
        'faqSections' => $faqSections,
        'faqHasContent' => $faqHasContent,
        'faqSearchPlaceholder' => $faqSearchPlaceholder,
      ])
    </main>

    <section class="sccc-faq-scope__help-wrap">
      @include('partials.faq.cant-find-help', [
        'faqHelp' => $faqHelp,
      ])
    </section>
  </section>
@endsection