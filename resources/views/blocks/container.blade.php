{{--
==============================================================================
File path + filename: resources/views/blocks/container.blade.php
==============================================================================

Purpose:
- Render the simplified Container block as a reusable section wrapper.

Why this file exists:
- The upgraded Container block is intended to be easier for editors to use.
- Instead of requiring separate Desktop / Tablet / Mobile spacing controls,
  this version relies on:
  - section presets
  - content width presets
  - mutually exclusive background mode
  - optional image overlay
  - text readability scheme
- This keeps the UI much lighter while still covering the most common section
  use cases such as standard sections, hero sections, CTA bands, and parallax
  image sections.

Layout model:
- OUTER wrapper:
  - background treatment OR image / overlay OR parallax image / overlay
  - preset vertical spacing
  - preset min-height when applicable
  - text color scheme
- INNER wrapper:
  - content width
  - vertical flow
  - InnerBlocks

Important fix:
- ACF outputs an .acf-innerblocks-container inside InnerBlocks.
- That generated wrapper needs width: 100%; otherwise nested blocks can shrink
  to their content width, especially when child blocks are collapsed.

Parallax behavior:
- Parallax mode renders a dedicated background layer instead of relying on
  background-attachment: fixed. This keeps the speed field useful and avoids
  tying content movement to the section wrapper itself.
- Parallax mode forces the outer section to full viewport width, even if a
  .container or max-width utility class gets applied by WordPress, Tailwind, or
  the block wrapper.

Monochrome behavior:
- When enabled, monochrome mode applies a grayscale filter only to the parallax
  background layer. The overlay and section content remain unaffected.

Mobile gutter behavior:
- The inner content wrapper includes a scoped mobile-only inline gutter.
- This protects nested InnerBlocks from touching the viewport edge when the
  Tailwind container utility, Gutenberg alignment styles, or a full-width
  background mode does not provide enough horizontal padding on small screens.
--}}

@php
  /**
   * Parallax sections must break out to the viewport edge.
   * This prevents Tailwind's .container max-width, Gutenberg alignment styles,
   * or inherited wrapper classes from constraining the visual background.
   */
  $is_parallax_mode = $background_mode === 'parallax';
  $outer_width_class = ($container_size === 'bg-default-width' && ! $is_parallax_mode)
    ? 'bg-default-width container'
    : 'bg-full-width';
  $inner_width_class = ($container_size === 'bg-full-width' || $is_parallax_mode)
    ? 'container'
    : '';
  $parallax_layer_class = ! empty($parallax_monochrome)
    ? 'sccc-container__parallax-bg is-monochrome'
    : 'sccc-container__parallax-bg';
@endphp

<{{ $tag }}
  id="{{ esc_attr($block_id) }}"
  class="{{ $block->classes }} sccc-container sccc-container--{{ esc_attr($section_preset) }} sccc-container--bg-{{ esc_attr($background_mode) }} sccc-container--treatment-{{ esc_attr($background_treatment) }} {{ $outer_width_class }}"
>
  @if ($has_parallax_background)
    <span
      class="{{ esc_attr($parallax_layer_class) }}"
      style="{{ $parallax_background_style }}"
      data-parallax-speed="{{ esc_attr((string) $parallax_speed) }}"
      aria-hidden="true"
    ></span>
  @endif

  @if ($has_overlay)
    <span class="sccc-container__overlay" aria-hidden="true"></span>
  @endif

  <div
    class="{{ $inner_width_class }} innerblock-wrap"
    @if (! empty($content_width_style))
      style="{{ $content_width_style }}"
    @endif
  >
    <InnerBlocks />
  </div>
</{{ $tag }}>

<style>
  /*
  |--------------------------------------------------------------------------
  | OUTER wrapper
  |--------------------------------------------------------------------------
  | Handles background, overlay context, text color, spacing, and full-width
  | breakout behavior for both the editor preview and the frontend.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} {
    position: relative;
    isolation: isolate;
    overflow: hidden;

    {{ $background_style }}
    {{ $text_style }}
    {{ $preset_outer_style }}
    {{ $editor_fullwidth_append }}
  }

  /*
  |--------------------------------------------------------------------------
  | Forced full-width parallax stage
  |--------------------------------------------------------------------------
  | This is intentionally aggressive because the reported issue was a
  | max-width: 96rem rule landing on the section itself. The ID selector and
  | !important values ensure parallax backgrounds cannot be boxed in by the
  | Tailwind .container class, Gutenberg align styles, or other wrapper rules.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }}.sccc-container--bg-parallax {
    width: 100vw !important;
    max-width: none !important;
    margin-left: calc(50% - 50vw) !important;
    margin-right: calc(50% - 50vw) !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
  }

  /*
  |--------------------------------------------------------------------------
  | Theme-safe background treatments
  |--------------------------------------------------------------------------
  | These treatments are scoped to this block instance and use project-style
  | design tokens with fallbacks so they survive both light and dark mode.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }}.sccc-container--bg-treatment.sccc-container--treatment-surface {
    background:
      linear-gradient(
        180deg,
        color-mix(in oklab, var(--color-surface, var(--surface, #ffffff)) 96%, transparent),
        color-mix(in oklab, var(--color-surface, var(--surface, #ffffff)) 100%, transparent)
      );
    border-block: 1px solid var(--color-line, var(--line, rgb(255 255 255 / 0.12)));
  }

  #{{ $block_id }}.sccc-container--bg-treatment.sccc-container--treatment-soft_glow {
    background:
      radial-gradient(
        680px 320px at 12% 0%,
        color-mix(in oklab, var(--color-primary-500, #2979ff) 18%, transparent),
        transparent 68%
      ),
      radial-gradient(
        620px 300px at 88% 12%,
        color-mix(in oklab, var(--color-accent-500, #ae71ff) 16%, transparent),
        transparent 70%
      ),
      var(--color-surface, var(--surface, transparent));
    border-block: 1px solid var(--color-line, var(--line, rgb(255 255 255 / 0.12)));
  }

  #{{ $block_id }}.sccc-container--bg-treatment.sccc-container--treatment-glass {
    background:
      linear-gradient(
        135deg,
        color-mix(in oklab, var(--color-surface, var(--surface, #ffffff)) 82%, transparent),
        color-mix(in oklab, var(--color-surface, var(--surface, #ffffff)) 62%, transparent)
      );
    border-block: 1px solid var(--color-line, var(--line, rgb(255 255 255 / 0.12)));
    backdrop-filter: blur(14px);
  }

  #{{ $block_id }}.sccc-container--bg-treatment.sccc-container--treatment-midnight {
    background:
      radial-gradient(760px 360px at 15% 0%, rgb(41 121 255 / 0.24), transparent 68%),
      radial-gradient(660px 340px at 86% 8%, rgb(174 113 255 / 0.22), transparent 70%),
      linear-gradient(135deg, #05070d 0%, #0b1020 58%, #07080c 100%);
    border-block: 1px solid rgb(255 255 255 / 0.14);
  }

  /*
  |--------------------------------------------------------------------------
  | Parallax background layer
  |--------------------------------------------------------------------------
  | Parallax mode uses a real background layer so the image can move at the
  | editor-selected speed while the section content remains stable/readable.
  | The layer is intentionally taller than the section so movement does not
  | reveal empty edges while scrolling.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} > .sccc-container__parallax-bg {
    position: absolute;
    top: -30%;
    right: 0;
    bottom: -30%;
    left: 0;
    z-index: 0;
    pointer-events: none;

    width: 100%;
    max-width: none;
    background-repeat: no-repeat;
    background-size: cover !important;
    transform: translate3d(0, 0, 0) scale(1.08);
    transform-origin: center;
    will-change: transform;
  }

  /*
  |--------------------------------------------------------------------------
  | Optional monochrome parallax treatment
  |--------------------------------------------------------------------------
  | This filter is applied only to the moving background layer. It does not
  | affect the overlay, buttons, headings, cards, or nested blocks.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} > .sccc-container__parallax-bg.is-monochrome {
    filter: grayscale(100%);
  }

  @media (prefers-reduced-motion: reduce) {
    #{{ $block_id }} > .sccc-container__parallax-bg {
      transform: none !important;
      will-change: auto;
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Overlay
  |--------------------------------------------------------------------------
  | Optional tint above the background image/parallax image and below the
  | content.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} > .sccc-container__overlay {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;

    {{ $overlay_style }}
  }

  /*
  |--------------------------------------------------------------------------
  | INNER wrapper
  |--------------------------------------------------------------------------
  | Holds actual content width and block flow.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} > .innerblock-wrap {
    position: relative;
    z-index: 2;

    display: flex;
    flex-direction: column;
    width: 100%;
    box-sizing: border-box;
  }

  /*
  |--------------------------------------------------------------------------
  | Mobile inline gutter
  |--------------------------------------------------------------------------
  | Tailwind's px-* utilities map to padding-inline, and Tailwind's responsive
  | model is mobile-first. This block keeps the gutter scoped here instead of
  | relying on a global .container definition because this wrapper can render as
  | default-width, full-width, image, or parallax.
  |
  | The padding only applies below the md breakpoint so desktop/tablet layouts
  | keep their current width behavior while phones get a reliable left/right
  | breathing room.
  |--------------------------------------------------------------------------
  */
  @media (max-width: 47.999rem) {
    #{{ $block_id }} > .innerblock-wrap {
      padding-inline: clamp(1.25rem, 6vw, 1.75rem);
    }
  }

  /*
  |--------------------------------------------------------------------------
  | ACF InnerBlocks width fix
  |--------------------------------------------------------------------------
  | ACF injects .acf-innerblocks-container inside the rendered InnerBlocks area.
  | Without width: 100%, collapsed child blocks can shrink to content width.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} > .innerblock-wrap > .acf-innerblocks-container {
    width: 100%;
    max-width: 100%;
  }

  /*
  |--------------------------------------------------------------------------
  | Child block stretch behavior
  |--------------------------------------------------------------------------
  | The parent no longer controls text alignment. Individual child blocks should
  | handle their own alignment. The parent only ensures layout wrappers stretch.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} > .innerblock-wrap > .acf-innerblocks-container > * {
    max-width: 100%;
  }

  /*
  |--------------------------------------------------------------------------
  | Preset-specific vertical behavior
  |--------------------------------------------------------------------------
  | Hero, CTA, and parallax sections behave more like stage/band layouts by
  | default, so the inner content should sit vertically centered in the section.
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }}.sccc-container--hero_banner > .innerblock-wrap,
  #{{ $block_id }}.sccc-container--cta_band > .innerblock-wrap,
  #{{ $block_id }}.sccc-container--bg-parallax > .innerblock-wrap {
    justify-content: center;
    min-height: inherit;
  }

  #{{ $block_id }}.sccc-container--standard:not(.sccc-container--bg-parallax) > .innerblock-wrap {
    justify-content: flex-start;
  }

  /*
  |--------------------------------------------------------------------------
  | Readability helper for unstyled links
  |--------------------------------------------------------------------------
  */
  #{{ $block_id }} :where(a:not(.wp-element-button):not(.button):not(.btn)) {
    color: inherit;
    text-decoration-color: currentColor;
  }
</style>

@if ($has_parallax_background)
  <script>
    (() => {
      /**
       * ----------------------------------------------------------------------
       * Scoped parallax initializer
       * ----------------------------------------------------------------------
       * This script only targets this block instance. It uses a lightweight
       * requestAnimationFrame scroll handler instead of requiring GSAP to be
       * present on window. This makes the editor-selected speed field visibly
       * affect the movement even when GSAP is bundled privately by the theme.
       */
      const init = () => {
        const section = document.getElementById(@json($block_id));

        if (! section || section.dataset.scccParallaxReady === 'true') {
          return;
        }

        const layer = section.querySelector(':scope > .sccc-container__parallax-bg');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (! layer || reducedMotion) {
          return;
        }

        const rawSpeed = Number.parseFloat(layer.dataset.parallaxSpeed || '30');
        const speed = Number.isFinite(rawSpeed) ? Math.max(0, Math.min(100, rawSpeed)) : 30;

        section.dataset.scccParallaxReady = 'true';

        if (speed <= 0) {
          return;
        }

        let ticking = false;

        const updateParallax = () => {
          const rect = section.getBoundingClientRect();
          const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
          const total = viewportHeight + rect.height;
          const progress = total > 0 ? (viewportHeight - rect.top) / total : 0.5;
          const clampedProgress = Math.max(0, Math.min(1, progress));
          const offset = (clampedProgress - 0.5) * speed * 3.4;

          layer.style.transform = `translate3d(0, ${offset}px, 0) scale(1.08)`;
          ticking = false;
        };

        const requestTick = () => {
          if (! ticking) {
            window.requestAnimationFrame(updateParallax);
            ticking = true;
          }
        };

        const scrollTargets = new Set([
          window,
          document,
          section.closest('.interface-interface-skeleton__content'),
          section.closest('.edit-post-visual-editor__content-area'),
          section.closest('.editor-styles-wrapper'),
          section.closest('.block-editor-writing-flow'),
        ]);

        scrollTargets.forEach((target) => {
          if (target && target.addEventListener) {
            target.addEventListener('scroll', requestTick, { passive: true });
          }
        });

        window.addEventListener('resize', requestTick);
        window.addEventListener('load', requestTick);

        updateParallax();
      };

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
      } else {
        init();
      }
    })();
  </script>
@endif