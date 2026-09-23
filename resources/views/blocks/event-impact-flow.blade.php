{{-- 
  File: resources/views/blocks/event-impact-flow.blade.php

  Event Impact Flow block template.

  Purpose:
  This block renders a reusable "impact pathway" section that can be used for
  event impact, process flows, mission journeys, or any multi-step narrative.

  This revision finalizes the width and alignment behavior:
  - Headline uses the available inner block width.
  - Intro/body copy uses the available inner block width.
  - Cards use the available inner block width.
  - Regular closing copy also uses the available inner block width.
  - Closing blockquotes remain a special editorial treatment at 80% width on
    desktop and 100% width on mobile.
  - Text alignment supports left, center, and justified.
  - Rich text styling remains scoped to this block only.

  Notes:
  - This view expects the EventImpact block class to pass:
    $headline
    $intro
    $steps
    $closingCopy
    $layoutDirection
    $contentWidth
    $textAlign
    $backgroundTreatment
    $stepStyle
    $connectorStyle
    $connectorTheme
    $accentColor
    $cardDecoration
    $paddingTop
    $paddingBottom
--}}

@php
  /**
   * Stable block ID.
   *
   * If the editor provides a custom anchor, use it. Otherwise fall back to
   * the block ID, and finally a generated unique ID as a last resort.
   */
  $blockId = ! empty($block->anchor)
    ? $block->anchor
    : 'event-impact-' . (! empty($block->id) ? $block->id : uniqid());

  /**
   * Block modifier classes.
   *
   * These classes are driven by sanitized values from the block class.
   * They control layout, alignment, background treatment, card style,
   * connector mode, connector gradient, accent color, card decoration,
   * and spacing.
   */
  $classes = array_filter([
    'sccc-event-impact',
    'sccc-event-impact--layout-' . $layoutDirection,
    'sccc-event-impact--width-' . $contentWidth,
    'sccc-event-impact--align-' . $textAlign,
    'sccc-event-impact--bg-' . $backgroundTreatment,
    'sccc-event-impact--step-' . $stepStyle,
    'sccc-event-impact--connector-' . $connectorStyle,
    'sccc-event-impact--connector-theme-' . $connectorTheme,
    'sccc-event-impact--accent-' . $accentColor,
    'sccc-event-impact--decoration-' . $cardDecoration,
    'sccc-event-impact--pt-' . $paddingTop,
    'sccc-event-impact--pb-' . $paddingBottom,
    ! empty($block->preview) ? 'is-preview' : null,
  ]);

  $stepCount = is_countable($steps) ? count($steps) : 0;
@endphp

@once
  <style>
    /**
     * =========================================================================
     * Event Impact Flow Block
     * =========================================================================
     *
     * Theme strategy:
     * - Reads project design tokens first.
     * - Falls back to safe local defaults.
     * - Supports html[data-theme="light"], html[data-theme="dark"], and .dark.
     *
     * Design intent:
     * - Richer than a generic card grid.
     * - Clean enough to work inside other containerized sections.
     * - Flexible for multiple use cases.
     * - Readable in both light and dark themes.
     */

    .sccc-event-impact {
      --sccc-impact-bg: transparent;
      --sccc-impact-text: var(--color-text, #f8fafc);
      --sccc-impact-muted: var(--color-muted, #a0a7b2);
      --sccc-impact-surface: var(--color-surface, rgba(20, 20, 20, 0.86));
      --sccc-impact-line: var(--color-line, rgba(255, 255, 255, 0.14));
      --sccc-impact-primary: var(--color-primary-500, #2979ff);
      --sccc-impact-accent: var(--color-accent-500, #ff1744);
      --sccc-impact-purple: #a855f7;
      --sccc-impact-radius: 1.35rem;
      --sccc-impact-shadow: 0 20px 60px rgba(0, 0, 0, 0.26);
      --sccc-impact-step-count: 3;
      --sccc-impact-accent-active: var(--sccc-impact-accent);
      --sccc-impact-accent-gradient: linear-gradient(
        135deg,
        var(--sccc-impact-primary),
        var(--sccc-impact-accent)
      );

      /**
       * Section-header compatible gradient tokens.
       *
       * These mirror the reusable Section Header block so this block's title
       * feels visually connected to the rest of the page.
       */
      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;

      /**
       * Connector-specific defaults.
       *
       * These are intentionally independent from the card accent setting so the
       * arrow badge can be styled separately.
       */
      --sccc-impact-connector-size: clamp(3.25rem, 4.5vw, 4.5rem);
      --sccc-impact-connector-badge-size: clamp(2.85rem, 3.9vw, 3.6rem);
      --sccc-impact-connector-gradient: linear-gradient(135deg, #3b82f6, #ef4444);
      --sccc-impact-connector-glow: #60a5fa;

      position: relative;
      isolation: isolate;
      color: var(--sccc-impact-text);
      background: var(--sccc-impact-bg);
    }

    html[data-theme="light"] .sccc-event-impact {
      --sccc-impact-text: var(--color-text, #111827);
      --sccc-impact-muted: var(--color-muted, #4b5563);
      --sccc-impact-surface: var(--color-surface, rgba(255, 255, 255, 0.94));
      --sccc-impact-line: var(--color-line, rgba(17, 24, 39, 0.14));
      --sccc-impact-shadow: 0 20px 50px rgba(15, 23, 42, 0.10);
    }

    html[data-theme="dark"] .sccc-event-impact,
    .dark .sccc-event-impact {
      --sccc-impact-text: var(--color-text, #ffffff);
      --sccc-impact-muted: var(--color-muted, #a0a7b2);
      --sccc-impact-surface: var(--color-surface, rgba(20, 20, 20, 0.84));
      --sccc-impact-line: var(--color-line, rgba(255, 255, 255, 0.14));
      --sccc-impact-shadow: 0 20px 60px rgba(0, 0, 0, 0.34);
    }

    /**
     * Card accent options.
     *
     * These affect card labels and subtle border treatments.
     */
    .sccc-event-impact--accent-blue {
      --sccc-impact-accent-active: var(--sccc-impact-primary);
      --sccc-impact-accent-gradient: linear-gradient(
        135deg,
        var(--sccc-impact-primary),
        color-mix(in oklab, var(--sccc-impact-primary) 70%, white 30%)
      );
    }

    .sccc-event-impact--accent-red {
      --sccc-impact-accent-active: var(--sccc-impact-accent);
      --sccc-impact-accent-gradient: linear-gradient(
        135deg,
        var(--sccc-impact-accent),
        color-mix(in oklab, var(--sccc-impact-accent) 72%, white 28%)
      );
    }

    .sccc-event-impact--accent-purple {
      --sccc-impact-accent-active: var(--sccc-impact-purple);
      --sccc-impact-accent-gradient: linear-gradient(
        135deg,
        var(--sccc-impact-purple),
        color-mix(in oklab, var(--sccc-impact-purple) 68%, white 32%)
      );
    }

    .sccc-event-impact--accent-mixed {
      --sccc-impact-accent-active: var(--sccc-impact-accent);
      --sccc-impact-accent-gradient: linear-gradient(
        135deg,
        var(--sccc-impact-primary),
        var(--sccc-impact-accent)
      );
    }

    /**
     * Connector gradient options.
     *
     * These affect only the arrow badge and connector rail.
     */
    .sccc-event-impact--connector-theme-brand {
      --sccc-impact-connector-gradient: linear-gradient(135deg, #3b82f6, #ef4444);
      --sccc-impact-connector-glow: #60a5fa;
    }

    .sccc-event-impact--connector-theme-blue_purple {
      --sccc-impact-connector-gradient: linear-gradient(135deg, #3b82f6, #8b5cf6);
      --sccc-impact-connector-glow: #8b5cf6;
    }

    .sccc-event-impact--connector-theme-neon_green {
      --sccc-impact-connector-gradient: linear-gradient(135deg, #b7ff00, #00e676);
      --sccc-impact-connector-glow: #7dff4f;
    }

    /**
     * Background treatment.
     *
     * Intentionally subtle so the block itself does not feel double-wrapped
     * when placed inside a container or another section.
     */
    .sccc-event-impact--bg-surface {
      --sccc-impact-bg: transparent;
    }

    .sccc-event-impact--bg-gradient {
      --sccc-impact-bg:
        radial-gradient(circle at 10% 0%, color-mix(in oklab, var(--sccc-impact-primary) 12%, transparent) 0%, transparent 36%),
        radial-gradient(circle at 90% 100%, color-mix(in oklab, var(--sccc-impact-accent) 10%, transparent) 0%, transparent 38%),
        transparent;
    }

    .sccc-event-impact--bg-dark_panel {
      --sccc-impact-bg:
        radial-gradient(circle at 14% 0%, rgba(41, 121, 255, 0.14) 0%, transparent 34%),
        radial-gradient(circle at 86% 100%, rgba(255, 23, 68, 0.12) 0%, transparent 40%),
        transparent;
    }

    /**
     * Section spacing.
     */
    .sccc-event-impact--pt-none { padding-top: 0; }
    .sccc-event-impact--pt-small { padding-top: clamp(1.5rem, 3vw, 2.5rem); }
    .sccc-event-impact--pt-medium { padding-top: clamp(2.5rem, 5vw, 4rem); }
    .sccc-event-impact--pt-large { padding-top: clamp(4rem, 7vw, 6rem); }
    .sccc-event-impact--pt-xlarge { padding-top: clamp(5rem, 9vw, 8rem); }

    .sccc-event-impact--pb-none { padding-bottom: 0; }
    .sccc-event-impact--pb-small { padding-bottom: clamp(1.5rem, 3vw, 2.5rem); }
    .sccc-event-impact--pb-medium { padding-bottom: clamp(2.5rem, 5vw, 4rem); }
    .sccc-event-impact--pb-large { padding-bottom: clamp(4rem, 7vw, 6rem); }
    .sccc-event-impact--pb-xlarge { padding-bottom: clamp(5rem, 9vw, 8rem); }

    /**
     * Inner width controls.
     */
    .sccc-event-impact__inner {
      width: min(100% - 2rem, 72rem);
      margin-inline: auto;
    }

    .sccc-event-impact--width-narrow .sccc-event-impact__inner {
      width: min(100% - 2rem, 56rem);
    }

    .sccc-event-impact--width-wide .sccc-event-impact__inner {
      width: min(100% - 2rem, 84rem);
    }

    /**
     * Header and intro.
     *
     * The header uses the available inner width instead of acting like a narrow
     * text column next to a wide card row.
     */
    .sccc-event-impact__header {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      width: 100%;
      max-width: none;
    }

    .sccc-event-impact__headline {
      width: 100%;
      max-width: none;
      margin: 0;
      color: var(--color-text, var(--sccc-impact-text));
      font-family: var(--font-headline, var(--font-display, inherit));
      font-size: clamp(1.75rem, 3.6vw, 3rem);
      font-weight: 800;
      line-height: 1.05;
      letter-spacing: 0;
      text-wrap: balance;
      margin-bottom: 1.5625rem;
    }

    .sccc-event-impact__headline::after {
      content: "";
      display: block;
      width: min(9rem, 42vw);
      height: 2px;
      margin-block-start: 0.55rem;
      border-radius: 999px;
      background: var(--sccc-gradient-space-city);
      box-shadow: 0 0 18px color-mix(in oklab, var(--sccc-color-club-blue) 42%, transparent);
    }

    .sccc-event-impact__intro {
      width: 100%;
      max-width: none;
      margin: 0.25rem 0 0;
      color: var(--color-muted, var(--sccc-impact-muted));
      font-family: var(--font-body, inherit);
      font-size: clamp(1rem, 1.35vw, 1.125rem);
      line-height: 1.65;
    }

    /**
     * Alignment controls.
     *
     * Left is the default. Center and justify are driven by the block class
     * generated from the Text Alignment field.
     */
    .sccc-event-impact--align-center .sccc-event-impact__header {
      margin-inline: auto;
      text-align: center;
      align-items: center;
    }

    .sccc-event-impact--align-center .sccc-event-impact__headline,
    .sccc-event-impact--align-center .sccc-event-impact__intro {
      margin-inline: auto;
      text-align: center;
    }

    .sccc-event-impact--align-center .sccc-event-impact__headline::after {
      margin-inline: auto;
    }

    .sccc-event-impact--align-justify .sccc-event-impact__header {
      text-align: left;
      align-items: stretch;
    }

    .sccc-event-impact--align-justify .sccc-event-impact__intro {
      text-align: justify;
      text-justify: inter-word;
    }

    .sccc-event-impact__intro > *:first-child,
    .sccc-event-impact__closing > *:first-child,
    .sccc-event-impact__step-description > *:first-child {
      margin-top: 0;
    }

    .sccc-event-impact__intro > *:last-child,
    .sccc-event-impact__closing > *:last-child,
    .sccc-event-impact__step-description > *:last-child {
      margin-bottom: 0;
    }

    /**
     * Rich text content.
     *
     * These rules style content inserted through WYSIWYG fields in the editor:
     * intro copy, step descriptions, and closing copy.
     */
    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote {
      position: relative;
      width: 100%;
      margin: clamp(2rem, 4vw, 3.5rem) 0;
      padding: clamp(1rem, 1.8vw, 1.35rem) 0;
      border: 0;
      background: transparent;
      box-shadow: none;
      color: var(--sccc-impact-text);
    }

    /**
     * Closing copy layout.
     *
     * Regular closing copy now uses the full available inner block width.
     * Closing blockquotes keep the special 80% editorial treatment.
     */
    .sccc-event-impact__closing {
      width: 100%;
      max-width: none;
      margin-top: clamp(1.75rem, 4vw, 3rem);
      color: var(--sccc-impact-muted);
      font-size: clamp(1rem, 1.35vw, 1.125rem);
      line-height: 1.75;
    }

    .sccc-event-impact--align-center .sccc-event-impact__closing {
      margin-inline: auto;
      text-align: center;
    }

    .sccc-event-impact--align-justify .sccc-event-impact__closing {
      text-align: justify;
      text-justify: inter-word;
    }

    .sccc-event-impact .sccc-event-impact__closing blockquote {
      width: min(80%, 72rem);
      margin-inline: auto;
    }

    .sccc-event-impact--align-center .sccc-event-impact__closing blockquote {
      text-align: center;
    }

    .sccc-event-impact--align-justify .sccc-event-impact__closing blockquote {
      text-align: justify;
      text-justify: inter-word;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote::before {
      content: "“";
      position: absolute;
      left: -0.02em;
      top: -0.2em;
      z-index: 0;
      font-family: Georgia, "Times New Roman", serif;
      font-size: clamp(5rem, 9vw, 7.5rem);
      font-weight: 900;
      line-height: 1;
      color: color-mix(in oklab, var(--sccc-impact-primary) 26%, transparent);
      pointer-events: none;
      user-select: none;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote::after {
      content: "”";
      position: absolute;
      right: 0;
      bottom: -0.2em;
      z-index: 0;
      font-family: Georgia, "Times New Roman", serif;
      font-size: clamp(4.5rem, 8vw, 6.5rem);
      font-weight: 900;
      line-height: 1;
      color: color-mix(in oklab, var(--sccc-impact-accent) 18%, transparent);
      pointer-events: none;
      user-select: none;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote > * {
      position: relative;
      z-index: 1;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote p {
      margin: 0;
      color: var(--sccc-impact-text);
      font-family: var(--font-body, inherit);
      font-size: clamp(1.25rem, 1.45vw + 0.9rem, 1.6rem);
      font-style: normal;
      font-weight: 700;
      line-height: 1.55;
      letter-spacing: -0.015em;
      text-wrap: pretty;
    }

    .sccc-event-impact--align-center :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote p {
      text-align: center;
    }

    .sccc-event-impact--align-justify :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote p {
      text-align: justify;
      text-justify: inter-word;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote p + p {
      margin-top: 0.9rem;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) blockquote cite {
      display: block;
      margin-top: 1rem;
      color: var(--sccc-impact-muted);
      font-size: 0.92rem;
      font-style: normal;
      font-weight: 700;
      letter-spacing: 0.04em;
    }

    /**
     * Unordered and ordered lists.
     */
    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) :is(ul, ol) {
      display: grid;
      gap: 0.75rem;
      margin: 1.15rem 0;
      padding: 0;
      list-style: none;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) :is(ul, ol) li {
      position: relative;
      min-height: 1.65rem;
      padding-inline-start: 2.35rem;
      color: var(--sccc-impact-muted);
      line-height: 1.65;
    }

    .sccc-event-impact--align-center :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) :is(ul, ol) {
      width: fit-content;
      max-width: 100%;
      margin-inline: auto;
      text-align: left;
    }

    .sccc-event-impact--align-justify :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) :is(ul, ol) li {
      text-align: justify;
      text-justify: inter-word;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) ul li::before {
      content: "";
      position: absolute;
      left: 0.25rem;
      top: 0.64em;
      width: 0.72rem;
      height: 0.72rem;
      border-radius: 999px;
      background:
        radial-gradient(circle at 35% 28%, rgba(255, 255, 255, 0.9) 0%, transparent 32%),
        var(--sccc-impact-accent-gradient);
      box-shadow:
        0 0 0 5px color-mix(in oklab, var(--sccc-impact-accent-active) 12%, transparent),
        0 0 18px color-mix(in oklab, var(--sccc-impact-accent-active) 34%, transparent);
      transform: translateY(-50%);
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) ul li::after {
      content: "";
      position: absolute;
      left: 1.18rem;
      top: 0.64em;
      width: 0.55rem;
      height: 1px;
      border-radius: 999px;
      background: color-mix(in oklab, var(--sccc-impact-accent-active) 55%, transparent);
      transform: translateY(-50%);
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) ol {
      counter-reset: sccc-impact-list;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) ol li {
      counter-increment: sccc-impact-list;
      padding-inline-start: 2.75rem;
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) ol li::before {
      content: counter(sccc-impact-list);
      position: absolute;
      left: 0;
      top: 0.78em;
      display: grid;
      width: 1.65rem;
      height: 1.65rem;
      place-items: center;
      border: 1px solid color-mix(in oklab, var(--sccc-impact-line) 76%, transparent);
      border-radius: 999px;
      background:
        radial-gradient(circle at 35% 28%, rgba(255, 255, 255, 0.36) 0%, transparent 34%),
        var(--sccc-impact-accent-gradient);
      box-shadow:
        0 0 0 5px color-mix(in oklab, var(--sccc-impact-accent-active) 12%, transparent),
        0 10px 24px color-mix(in oklab, var(--sccc-impact-accent-active) 18%, transparent);
      color: #ffffff;
      font-size: 0.72rem;
      font-weight: 900;
      line-height: 1;
      transform: translateY(-50%);
    }

    .sccc-event-impact :is(
      .sccc-event-impact__intro,
      .sccc-event-impact__step-description,
      .sccc-event-impact__closing
    ) :is(ul, ol) :is(ul, ol) {
      margin-top: 0.65rem;
      margin-bottom: 0;
      padding-inline-start: 0.25rem;
    }

    /**
     * Step track.
     */
    .sccc-event-impact__steps {
      display: flex;
      align-items: stretch;
      gap: 0;
      margin-top: clamp(2rem, 5vw, 3.5rem);
    }

    .sccc-event-impact--layout-horizontal .sccc-event-impact__steps {
      flex-direction: row;
    }

    .sccc-event-impact--layout-vertical .sccc-event-impact__steps {
      flex-direction: column;
    }

    /**
     * Step item.
     */
    .sccc-event-impact__step {
      flex: 1 1 0;
      min-width: 0;
    }

    .sccc-event-impact__step-inner {
      position: relative;
      min-height: 13.5rem;
      height: 100%;
      padding: clamp(1.35rem, 2.4vw, 1.85rem);
      padding-bottom: clamp(3rem, 4.5vw, 3.75rem);
      border-radius: var(--sccc-impact-radius);
      overflow: hidden;
    }

    .sccc-event-impact--align-center .sccc-event-impact__step-inner {
      text-align: center;
    }

    .sccc-event-impact--align-center .sccc-event-impact__step-title,
    .sccc-event-impact--align-center .sccc-event-impact__step-description {
      margin-inline: auto;
    }

    .sccc-event-impact--align-justify .sccc-event-impact__step-description {
      text-align: justify;
      text-justify: inter-word;
    }

    /**
     * Step card styles.
     */
    .sccc-event-impact--step-glass .sccc-event-impact__step-inner {
      background:
        linear-gradient(
          145deg,
          color-mix(in oklab, var(--sccc-impact-surface) 92%, white 8%),
          color-mix(in oklab, var(--sccc-impact-surface) 84%, transparent)
        );
      border: 1px solid var(--sccc-impact-line);
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.08),
        var(--sccc-impact-shadow);
      backdrop-filter: blur(12px);
    }

    .sccc-event-impact--step-solid .sccc-event-impact__step-inner {
      background: var(--sccc-impact-surface);
      border: 1px solid var(--sccc-impact-line);
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.06),
        var(--sccc-impact-shadow);
    }

    .sccc-event-impact--step-minimal .sccc-event-impact__step-inner {
      min-height: 10.5rem;
      padding-bottom: 3rem;
      border-bottom: 1px solid var(--sccc-impact-line);
      border-radius: 0;
      background: transparent;
      box-shadow: none;
    }

    /**
     * Subtle gradient border hint.
     */
    .sccc-event-impact--step-glass .sccc-event-impact__step-inner::before,
    .sccc-event-impact--step-solid .sccc-event-impact__step-inner::before {
      content: "";
      position: absolute;
      inset: 0;
      padding: 1px;
      border-radius: inherit;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--sccc-impact-primary) 64%, transparent),
          transparent 30%,
          transparent 62%,
          color-mix(in oklab, var(--sccc-impact-accent) 58%, transparent)
        );
      -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      opacity: 0.72;
      pointer-events: none;
    }

    /**
     * Large decorative step number.
     *
     * Only displays when Card Decoration is set to "Large step number".
     */
    .sccc-event-impact__step-inner::after {
      content: none;
    }

    .sccc-event-impact--decoration-number .sccc-event-impact__step-inner::after {
      content: attr(data-step-number);
      position: absolute;
      right: 0.75rem;
      bottom: 0.15rem;
      z-index: 0;
      color: color-mix(in oklab, var(--sccc-impact-text) 4%, transparent);
      font-family: var(--font-headline, var(--font-display, inherit));
      font-size: clamp(4.5rem, 8vw, 7rem);
      font-weight: 800;
      line-height: 0.9;
      letter-spacing: -0.08em;
      pointer-events: none;
      user-select: none;
    }

    html[data-theme="light"] .sccc-event-impact--decoration-number .sccc-event-impact__step-inner::after {
      color: color-mix(in oklab, var(--sccc-impact-text) 5%, transparent);
    }

    /**
     * Mirrored ghost label.
     *
     * Only displays when Card Decoration is set to "Mirrored small label".
     */
    .sccc-event-impact__step-label-ghost {
      position: absolute;
      left: 1rem;
      right: 0.85rem;
      bottom: 0.5rem;
      z-index: 0;
      display: block;
      color: color-mix(in oklab, var(--sccc-impact-text) 4.5%, transparent);
      font-family: var(--font-headline, var(--font-display, inherit));
      font-size: clamp(2.15rem, 2.2vw + 0.9rem, 3.5rem);
      font-stretch: condensed;
      font-weight: 900;
      line-height: 0.82;
      letter-spacing: -0.06em;
      text-align: right;
      text-transform: uppercase;
      white-space: nowrap;
      transform: scaleX(0.68);
      transform-origin: right bottom;
      pointer-events: none;
      user-select: none;
    }

    html[data-theme="light"] .sccc-event-impact__step-label-ghost {
      color: color-mix(in oklab, var(--sccc-impact-text) 6%, transparent);
    }

    /**
     * Step copy.
     */
    .sccc-event-impact__step-label {
      position: relative;
      z-index: 2;
      margin: 0 0 0.55rem;
      color: color-mix(in oklab, var(--sccc-impact-accent-active) 82%, white 18%);
      font-size: 0.72rem;
      font-weight: 900;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }

    .sccc-event-impact__step-title {
      position: relative;
      z-index: 2;
      max-width: 14ch;
      margin: 0;
      color: var(--sccc-impact-text);
      font-family: var(--font-headline, var(--font-display, inherit));
      font-size: clamp(1.55rem, 2.5vw, 2.15rem);
      line-height: 0.98;
      letter-spacing: -0.03em;
      text-wrap: balance;
    }

    .sccc-event-impact__step-description {
      position: relative;
      z-index: 2;
      max-width: 36rem;
      margin-top: 0.95rem;
      color: var(--sccc-impact-muted);
      font-size: 1rem;
      line-height: 1.68;
    }

    /**
     * Connector.
     */
    .sccc-event-impact__connector {
      position: relative;
      flex: 0 0 var(--sccc-impact-connector-size);
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }

    .sccc-event-impact--layout-horizontal .sccc-event-impact__connector {
      min-width: var(--sccc-impact-connector-size);
      padding-inline: 0.45rem;
    }

    .sccc-event-impact--layout-vertical .sccc-event-impact__connector {
      width: 100%;
      min-height: var(--sccc-impact-connector-size);
      padding-block: 0.45rem;
    }

    .sccc-event-impact__connector::before {
      content: "";
      position: absolute;
      border-radius: 999px;
      background: var(--sccc-impact-connector-gradient);
      opacity: 0.7;
      box-shadow: 0 0 20px color-mix(in oklab, var(--sccc-impact-connector-glow) 24%, transparent);
    }

    .sccc-event-impact--layout-horizontal .sccc-event-impact__connector::before {
      width: 100%;
      height: 2px;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
    }

    .sccc-event-impact--layout-vertical .sccc-event-impact__connector::before {
      width: 2px;
      height: 100%;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
    }

    .sccc-event-impact__connector-badge {
      position: relative;
      z-index: 1;
      display: grid;
      place-items: center;
      width: var(--sccc-impact-connector-badge-size);
      height: var(--sccc-impact-connector-badge-size);
      border-radius: 999px;
      background:
        radial-gradient(circle at 35% 28%, rgba(255, 255, 255, 0.42) 0%, transparent 34%),
        var(--sccc-impact-connector-gradient);
      border: 1px solid rgba(255, 255, 255, 0.24);
      box-shadow:
        0 0 0 6px color-mix(in oklab, var(--sccc-impact-connector-glow) 16%, transparent),
        0 14px 34px color-mix(in oklab, var(--sccc-impact-connector-glow) 26%, transparent);
    }

    .sccc-event-impact__connector-badge::before {
      content: "";
      position: absolute;
      inset: -0.45rem;
      border-radius: inherit;
      border: 1px solid color-mix(in oklab, var(--sccc-impact-connector-glow) 36%, transparent);
      opacity: 0.68;
      pointer-events: none;
    }

    .sccc-event-impact__connector-icon {
      position: relative;
      z-index: 1;
      width: 1.55rem;
      height: 1.55rem;
      color: #ffffff;
      filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.16));
    }

    .sccc-event-impact--connector-line .sccc-event-impact__connector-badge {
      display: none;
    }

    .sccc-event-impact--connector-none .sccc-event-impact__connector {
      display: none;
    }

    .sccc-event-impact--layout-vertical .sccc-event-impact__connector-badge {
      transform: rotate(90deg);
    }

    /**
     * Responsive behavior.
     */
    @media (max-width: 900px) {
      .sccc-event-impact--layout-horizontal .sccc-event-impact__steps {
        flex-direction: column;
      }

      .sccc-event-impact--layout-horizontal .sccc-event-impact__connector {
        width: 100%;
        min-height: var(--sccc-impact-connector-size);
        min-width: 0;
        padding-block: 0.45rem;
        padding-inline: 0;
      }

      .sccc-event-impact--layout-horizontal .sccc-event-impact__connector::before {
        width: 2px;
        height: 100%;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
      }

      .sccc-event-impact--layout-horizontal .sccc-event-impact__connector-badge {
        transform: rotate(90deg);
      }
    }

    @media (max-width: 640px) {
      .sccc-event-impact__step-inner {
        min-height: 12.5rem;
        padding: 1.15rem;
        padding-bottom: 3rem;
      }

      .sccc-event-impact__step-title {
        max-width: 100%;
      }

      .sccc-event-impact--decoration-number .sccc-event-impact__step-inner::after {
        right: 0.55rem;
        bottom: 0.2rem;
        font-size: clamp(4rem, 18vw, 5.6rem);
      }

      .sccc-event-impact__step-label-ghost {
        left: 0.85rem;
        right: 0.65rem;
        bottom: 0.45rem;
        font-size: clamp(1.85rem, 9vw, 2.95rem);
        letter-spacing: -0.05em;
        transform: scaleX(0.72);
      }

      .sccc-event-impact :is(
        .sccc-event-impact__intro,
        .sccc-event-impact__step-description,
        .sccc-event-impact__closing
      ) blockquote {
        margin: 2rem 0;
        padding: 0.85rem 0;
      }

      .sccc-event-impact .sccc-event-impact__closing blockquote {
        width: 100%;
      }

      .sccc-event-impact :is(
        .sccc-event-impact__intro,
        .sccc-event-impact__step-description,
        .sccc-event-impact__closing
      ) blockquote::before {
        font-size: clamp(4rem, 16vw, 5.5rem);
        top: -0.15em;
      }

      .sccc-event-impact :is(
        .sccc-event-impact__intro,
        .sccc-event-impact__step-description,
        .sccc-event-impact__closing
      ) blockquote::after {
        font-size: clamp(3.5rem, 14vw, 4.75rem);
        bottom: -0.28em;
      }

      .sccc-event-impact :is(
        .sccc-event-impact__intro,
        .sccc-event-impact__step-description,
        .sccc-event-impact__closing
      ) :is(ul, ol) li {
        padding-inline-start: 2.15rem;
      }

      .sccc-event-impact--align-justify .sccc-event-impact__intro,
      .sccc-event-impact--align-justify .sccc-event-impact__step-description,
      .sccc-event-impact--align-justify .sccc-event-impact__closing,
      .sccc-event-impact--align-justify :is(
        .sccc-event-impact__intro,
        .sccc-event-impact__step-description,
        .sccc-event-impact__closing
      ) :is(p, li, blockquote p) {
        text-align: left;
      }
    }

    /**
     * Motion.
     */
    @media (prefers-reduced-motion: no-preference) {
      .sccc-event-impact__step-inner,
      .sccc-event-impact__connector-badge {
        transition:
          transform 220ms ease,
          border-color 220ms ease,
          box-shadow 220ms ease,
          filter 220ms ease,
          opacity 220ms ease;
      }

      .sccc-event-impact__step-inner:hover {
        transform: translateY(-4px);
        border-color: color-mix(in oklab, var(--sccc-impact-accent-active) 42%, var(--sccc-impact-line));
        box-shadow:
          inset 0 1px 0 rgba(255, 255, 255, 0.08),
          0 24px 64px rgba(0, 0, 0, 0.28);
      }

      .sccc-event-impact__connector-badge:hover {
        transform: scale(1.04);
        filter: brightness(1.05);
      }

      .sccc-event-impact--layout-vertical .sccc-event-impact__connector-badge:hover {
        transform: rotate(90deg) scale(1.04);
      }

      @media (max-width: 900px) {
        .sccc-event-impact--layout-horizontal .sccc-event-impact__connector-badge:hover {
          transform: rotate(90deg) scale(1.04);
        }
      }
    }
  </style>
@endonce

<section
  id="{{ esc_attr($blockId) }}"
  class="{{ esc_attr(implode(' ', $classes)) }}"
  style="--sccc-impact-step-count: {{ esc_attr((string) max(1, $stepCount)) }};"
>
  <div class="sccc-event-impact__inner">
    @if (! empty($headline) || ! empty($intro))
      <header class="sccc-event-impact__header">
        @if (! empty($headline))
          <h2 class="sccc-event-impact__headline">
            {!! wp_kses_post($headline) !!}
          </h2>
        @endif

        @if (! empty($intro))
          <div class="sccc-event-impact__intro">
            {!! wp_kses_post($intro) !!}
          </div>
        @endif
      </header>
    @endif

    @if (! empty($steps))
      <div
        class="sccc-event-impact__steps"
        role="list"
        aria-label="{{ esc_attr($headline ?: 'Event impact flow') }}"
      >
        @foreach ($steps as $index => $step)
          @php
            $stepNumber = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $isLastStep = $index === ($stepCount - 1);
            $stepLabel = ! empty($step['label']) ? trim((string) $step['label']) : '';
          @endphp

          <article class="sccc-event-impact__step" role="listitem">
            <div class="sccc-event-impact__step-inner" data-step-number="{{ esc_attr($stepNumber) }}">
              @if ($cardDecoration === 'label' && $stepLabel !== '')
                <span class="sccc-event-impact__step-label-ghost" aria-hidden="true">
                  {!! esc_html($stepLabel) !!}
                </span>
              @endif

              @if ($stepLabel !== '')
                <p class="sccc-event-impact__step-label">
                  {!! esc_html($stepLabel) !!}
                </p>
              @endif

              @if (! empty($step['title']))
                <h3 class="sccc-event-impact__step-title">
                  {!! wp_kses_post($step['title']) !!}
                </h3>
              @endif

              @if (! empty($step['description']))
                <div class="sccc-event-impact__step-description">
                  {!! wp_kses_post($step['description']) !!}
                </div>
              @endif
            </div>
          </article>

          @if (! $isLastStep && $connectorStyle !== 'none')
            <div class="sccc-event-impact__connector" aria-hidden="true">
              <span class="sccc-event-impact__connector-badge">
                <svg
                  class="sccc-event-impact__connector-icon"
                  viewBox="0 0 48 48"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M10 24H28"
                    stroke="currentColor"
                    stroke-width="4.25"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M22 16L30 24L22 32"
                    stroke="currentColor"
                    stroke-width="4.25"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </span>
            </div>
          @endif
        @endforeach
      </div>
    @endif

    @if (! empty($closingCopy))
      <div class="sccc-event-impact__closing">
        {!! wp_kses_post($closingCopy) !!}
      </div>
    @endif
  </div>
</section>