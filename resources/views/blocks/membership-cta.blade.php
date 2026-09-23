{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/membership-cta.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the "Not a member yet?" membership CTA card.
| - Adapt visually to the site's light and dark themes.
| - Hide the card entirely on the frontend when the visitor is logged in.
| - Show a neutral editor placeholder when viewed inside the block editor
|   while logged in.
|
| Variables passed from MembershipCta::with():
|   $heading      — card heading text
|   $body         — body paragraph text
|   $button_label — CTA button label
|   $button_url   — CTA button href
|   $is_logged_in — bool: true when WordPress reports user is logged in
|   $is_preview   — bool: true when rendering inside the block editor
|
| Theme switching pattern (from the project's No-FOUC boot script):
|   Dark:  <html class="dark" data-theme="dark">
|   Light: <html class=""     data-theme="light">
|
|   Because the light theme has NO .dark class, we use:
|   - [data-theme="light"] .sccc-mcta  → light styles  (default / base)
|   - [data-theme="dark"]  .sccc-mcta  → dark override
|   - html.dark            .sccc-mcta  → dark override (belt-and-suspenders)
|
|   Using [data-theme="light"] as the default rather than bare .sccc-mcta
|   means the card always has an intentional appearance even if the data
|   attribute is missing during a flash-of-unstyled-content window.
|
| Self-contained CSS caveat:
|   Styles live in <style> here per the project requirement. If this block
|   is ever used multiple times on one page, the <style> tag injects once
|   per instance. For a single sidebar widget this is harmless. If
|   multi-instance use is needed, move the styles to the block's enqueue()
|   method with wp_add_inline_style() and a flag to print once.
--------------------------------------------------------------------------
--}}

{{-- ─── SELF-CONTAINED STYLES ──────────────────────────────────────────────── --}}
<style>
  /*
   * ── BASE (light theme) ────────────────────────────────────────────────────
   * Matches the light-mode card aesthetic used across the blog list:
   * white/surface background, subtle border, dark text, primary accent.
   * Tokens align with the project's --color-* custom properties.
   */
  .sccc-mcta {
    position: relative;
    overflow: hidden;
    padding: 1.5rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(17, 20, 24, 0.12); /* --color-line light fallback */
    background: #ffffff;                        /* --color-surface light fallback */
    text-align: center;
    box-shadow: 0 8px 24px rgb(0 0 0 / 0.07);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;

    /*
     * Token references — if the project's CSS custom properties are in scope
     * these will resolve to the correct theme values automatically.
     */
    border-color: var(--color-line, rgba(17, 20, 24, 0.12));
    background:   var(--color-surface, #ffffff);
  }

  /*
   * Top accent line — primary brand colour gradient.
   * Identical in both themes; only the card behind it changes.
   */
  .sccc-mcta::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(
      to right,
      transparent,
      var(--color-primary-500, #13a4ec),
      transparent
    );
  }

  /* ── Heading (light) ── */
  .sccc-mcta__heading {
    margin: 0 0 0.5rem;
    color: var(--color-text, #111418);
    font-family: var(--font-display, inherit);
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.25;
  }

  /* ── Body copy (light) ── */
  .sccc-mcta__body {
    margin: 0 0 1.5rem;
    color: var(--color-muted, #5f6670);
    font-size: 0.875rem;
    line-height: 1.6;
  }

  /* ── CTA button — same in both themes ── */
  .sccc-mcta__btn {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 0.5rem;
    background-color: var(--color-primary-500, #13a4ec);
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 6px 20px rgb(19 164 236 / 0.28);
    transition:
      background-color 0.2s ease,
      transform 0.2s ease,
      box-shadow 0.2s ease;
  }

  .sccc-mcta__btn:hover,
  .sccc-mcta__btn:focus-visible {
    background-color: color-mix(
      in srgb,
      var(--color-primary-500, #13a4ec) 84%,
      white 16%
    );
    transform: translateY(-1px);
    box-shadow: 0 10px 26px rgb(19 164 236 / 0.36);
    color: #ffffff;
    text-decoration: none;
    outline: none;
  }

  /*
   * ── DARK THEME OVERRIDE ───────────────────────────────────────────────────
   * Targets both selectors because:
   *   [data-theme="dark"] — set synchronously by the No-FOUC script on <html>
   *   html.dark           — set by the same script; Tailwind also uses this
   * Both are always present together in this project, but specifying both
   * makes the dark styles robust against any future change to either alone.
   */
  [data-theme="dark"] .sccc-mcta,
  html.dark .sccc-mcta {
    border-color: #233c48;
    background: linear-gradient(to bottom, #111c22, #1a2830);
    box-shadow:
      0 10px 30px rgb(0 0 0 / 0.35),
      0 1px 0 rgb(255 255 255 / 0.03) inset;
  }

  [data-theme="dark"] .sccc-mcta__heading,
  html.dark .sccc-mcta__heading {
    color: #ffffff;
  }

  [data-theme="dark"] .sccc-mcta__body,
  html.dark .sccc-mcta__body {
    color: #9ca3af;
  }

  /*
   * ── EDITOR PLACEHOLDER ────────────────────────────────────────────────────
   * Shown in the block editor when the editor is logged in (which is always).
   * Neutral appearance — not themed, just informational.
   */
  .sccc-mcta--editor-placeholder {
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    border: 1px dashed #233c48;
    background: rgb(17 28 34 / 0.5);
    color: #5f7a87;
    font-size: 0.78rem;
    text-align: center;
  }
</style>

{{-- ─── LOGGED-IN + FRONTEND: render nothing ───────────────────────────────── --}}
@if ($is_logged_in && ! $is_preview)
  {{--
    User is logged in on the real frontend.
    Return early — block occupies zero space in the sidebar.
  --}}
@elseif ($is_logged_in && $is_preview)
  {{--
    Inside the block editor (editor is always logged in).
    Show a small placeholder so the block remains selectable and
    its fields panel remains accessible.
  --}}
  <div class="sccc-mcta--editor-placeholder">
    <strong>Membership CTA</strong> — hidden for logged-in members on the frontend.
  </div>
@else
  {{-- Logged-out visitor — render the full card. --}}
  <div class="sccc-mcta">

    <h3 class="sccc-mcta__heading">
      {{ $heading }}
    </h3>

    @if (! empty($body))
      <p class="sccc-mcta__body">
        {{ $body }}
      </p>
    @endif

    
      <a href="{{ esc_url($button_url) }}"
      class="sccc-mcta__btn"
    >
      {{ $button_label }}
    </a>

  </div>
@endif