{{--
  File path + filename: resources/views/partials/directory/styles.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Provide template-scoped styles for the business directory page.

  Why this file exists:
  - The user requested CSS that cannot bleed into other pages.
  - Keeping the styles in a partial included only by the business directory
    template guarantees that scope without requiring assumptions about the
    current global asset pipeline.

  Notes:
  - Every selector is scoped to `.sccc-business-directory-template`.
  - Theme support is handled with the site's real theme signals:
    light theme: `html[data-theme="light"]`
    dark theme: `html.dark[data-theme="dark"]` and `html[data-theme="dark"]`
  - The top page content area is intentionally left mostly unstyled so existing
    Gutenberg block styling remains intact.
  - Buttons use an outer shell for layout/glow, a masked pseudo-element for the
    neon border ring, and an inner face for the glass center.
--}}

<style>
  .sccc-business-directory-template {
    --sccc-directory-bg: #eef2f9;
    --sccc-directory-surface: #ffffff;
    --sccc-directory-surface-alt: #f4f7fc;
    --sccc-directory-border: rgba(26, 39, 68, 0.10);
    --sccc-directory-text: #1c2433;
    --sccc-directory-text-muted: #5f6c82;
    --sccc-directory-primary: #2e57ff;
    --sccc-directory-primary-dark: #2245d8;
    --sccc-directory-chip-bg: rgba(103, 115, 255, 0.10);
    --sccc-directory-chip-text: #4350a0;
    --sccc-directory-shadow: 0 24px 80px rgba(23, 31, 61, 0.08);
    --sccc-directory-shadow-hover: 0 28px 90px rgba(46, 87, 255, 0.12);
    --sccc-directory-gradient:
      radial-gradient(circle at top left, rgba(95, 146, 255, 0.12), transparent 34%),
      radial-gradient(circle at top right, rgba(179, 126, 255, 0.12), transparent 30%),
      linear-gradient(180deg, rgba(255, 255, 255, 0.74) 0%, rgba(245, 248, 255, 0.92) 100%);
    --sccc-header-underline-gradient: linear-gradient(
      90deg,
      rgba(113, 215, 255, 0) 0%,
      rgba(113, 215, 255, 0.96) 18%,
      rgba(67, 190, 255, 0.98) 48%,
      rgba(174, 113, 255, 0.92) 82%,
      rgba(174, 113, 255, 0) 100%
    );

    /* Button system: light theme */
    --sccc-directory-button-face-bg: rgba(255, 255, 255, 0.86);
    --sccc-directory-button-face-bg-hover: rgba(255, 255, 255, 0.94);
    --sccc-directory-button-text: #1c3b5a;
    --sccc-directory-button-text-muted: #274565;
    --sccc-directory-button-face-stroke: rgba(255, 255, 255, 0.82);
    --sccc-directory-button-edge-stroke: rgba(67, 190, 255, 0.24);
    --sccc-directory-button-ring-size: 2px;
    --sccc-directory-button-glow:
      0 10px 24px rgba(67, 190, 255, 0.12),
      0 8px 18px rgba(174, 113, 255, 0.10);
    --sccc-directory-button-glow-hover:
      0 14px 34px rgba(67, 190, 255, 0.18),
      0 12px 24px rgba(174, 113, 255, 0.16);

    color: var(--sccc-directory-text);
  }

  html[data-theme="light"] .sccc-business-directory-template {
    --sccc-directory-bg: #eef2f9;
    --sccc-directory-surface: #ffffff;
    --sccc-directory-surface-alt: #f4f7fc;
    --sccc-directory-border: rgba(26, 39, 68, 0.10);
    --sccc-directory-text: #1c2433;
    --sccc-directory-text-muted: #5f6c82;
    --sccc-directory-primary: #2e57ff;
    --sccc-directory-primary-dark: #2245d8;
    --sccc-directory-chip-bg: rgba(103, 115, 255, 0.10);
    --sccc-directory-chip-text: #4350a0;
    --sccc-directory-shadow: 0 24px 80px rgba(23, 31, 61, 0.08);
    --sccc-directory-shadow-hover: 0 28px 90px rgba(46, 87, 255, 0.12);
    --sccc-directory-gradient:
      radial-gradient(circle at top left, rgba(95, 146, 255, 0.12), transparent 34%),
      radial-gradient(circle at top right, rgba(179, 126, 255, 0.12), transparent 30%),
      linear-gradient(180deg, rgba(255, 255, 255, 0.74) 0%, rgba(245, 248, 255, 0.92) 100%);
    --sccc-directory-button-face-bg: rgba(255, 255, 255, 0.86);
    --sccc-directory-button-face-bg-hover: rgba(255, 255, 255, 0.94);
    --sccc-directory-button-text: #1c3b5a;
    --sccc-directory-button-text-muted: #274565;
    --sccc-directory-button-face-stroke: rgba(255, 255, 255, 0.82);
    --sccc-directory-button-edge-stroke: rgba(67, 190, 255, 0.24);
    --sccc-directory-button-ring-size: 2px;
    --sccc-directory-button-glow:
      0 10px 24px rgba(67, 190, 255, 0.12),
      0 8px 18px rgba(174, 113, 255, 0.10);
    --sccc-directory-button-glow-hover:
      0 14px 34px rgba(67, 190, 255, 0.18),
      0 12px 24px rgba(174, 113, 255, 0.16);
  }

  html.dark[data-theme="dark"] .sccc-business-directory-template,
  html[data-theme="dark"] .sccc-business-directory-template {
    --sccc-directory-bg: #101022;
    --sccc-directory-surface: #1c1c36;
    --sccc-directory-surface-alt: #111122;
    --sccc-directory-border: rgba(255, 255, 255, 0.10);
    --sccc-directory-text: #f8faff;
    --sccc-directory-text-muted: #b0b7c7;
    --sccc-directory-primary: #5563ff;
    --sccc-directory-primary-dark: #6e7bff;
    --sccc-directory-chip-bg: rgba(85, 99, 255, 0.16);
    --sccc-directory-chip-text: #d7dcff;
    --sccc-directory-shadow: 0 24px 70px rgba(0, 0, 0, 0.35);
    --sccc-directory-shadow-hover: 0 28px 80px rgba(85, 99, 255, 0.18);
    --sccc-directory-gradient:
      radial-gradient(circle at top left, rgba(85, 99, 255, 0.16), transparent 38%),
      radial-gradient(circle at top right, rgba(161, 56, 255, 0.12), transparent 36%);

    /* Button system: dark theme */
    --sccc-directory-button-face-bg: rgba(8, 11, 26, 0.42);
    --sccc-directory-button-face-bg-hover: rgba(10, 14, 32, 0.52);
    --sccc-directory-button-text: #eef6ff;
    --sccc-directory-button-text-muted: #dbe9ff;
    --sccc-directory-button-face-stroke: rgba(255, 255, 255, 0.08);
    --sccc-directory-button-edge-stroke: rgba(113, 215, 255, 0.18);
    --sccc-directory-button-ring-size: 2px;
    --sccc-directory-button-glow:
      0 10px 24px rgba(67, 190, 255, 0.10),
      0 8px 18px rgba(174, 113, 255, 0.08);
    --sccc-directory-button-glow-hover:
      0 14px 34px rgba(67, 190, 255, 0.16),
      0 12px 24px rgba(174, 113, 255, 0.14);
  }

  .sccc-business-directory-template__page-content {
    position: relative;
    z-index: 1;
  }

      /* ----------------------------------------------------------------------
     PMPro restricted modal state
     ----------------------------------------------------------------------
     Why:
     PMPro replaces protected page content with a membership message, but the
     business directory also renders custom Blade partials outside that
     normal content filter. The listing and CTA partials now stop rendering
     for non-members; this styling turns the remaining PMPro message into a
     centered theme modal and keeps the rest of the page visually locked.
     ---------------------------------------------------------------------- */
  html.sccc-restricted-modal-open,
  body.sccc-restricted-modal-open {
    overflow: hidden;
  }

  .sccc-business-directory-template.pmpro-no-access {
    --pmpro--color--accent: #8cc8ff;
    position: relative;
    isolation: isolate;
    min-height: 72svh;
    color: #f8faff;
    background:
      radial-gradient(circle at 20% 20%, rgba(67, 190, 255, 0.18), transparent 30%),
      radial-gradient(circle at 82% 18%, rgba(174, 113, 255, 0.16), transparent 32%),
      linear-gradient(180deg, rgba(7, 17, 34, 0.94) 0%, rgba(5, 13, 28, 0.98) 100%);
  }

  .sccc-business-directory-template.pmpro-no-access::before {
    content: "";
    position: fixed;
    inset: 0;
    z-index: 9990;
    background:
      radial-gradient(circle at 26% 18%, rgba(67, 190, 255, 0.13), transparent 28%),
      radial-gradient(circle at 78% 14%, rgba(174, 113, 255, 0.12), transparent 30%),
      rgba(3, 8, 18, 0.72);
    backdrop-filter: blur(18px) saturate(120%);
    -webkit-backdrop-filter: blur(18px) saturate(120%);
    pointer-events: auto;
  }

  .sccc-business-directory-template.pmpro-no-access .sccc-business-directory-template__page-content {
    position: fixed;
    inset: 0;
    z-index: 9991;
    display: grid;
    place-items: center;
    width: 100%;
    min-height: 100svh;
    padding: clamp(1rem, 3vw, 2rem);
    overflow: auto;
    overscroll-behavior: contain;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro {
    width: min(100%, 35rem);
    margin: 0;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card,
  .sccc-business-directory-template.pmpro-no-access .pmpro_card.pmpro_content_message {
    position: relative;
    overflow: hidden;
    width: 100%;
    margin: 0;
    padding: clamp(1.35rem, 4vw, 2rem);
    border: 1px solid rgba(113, 215, 255, 0.20);
    border-radius: 1.5rem;
    background:
      radial-gradient(circle at top left, rgba(67, 190, 255, 0.15), transparent 36%),
      radial-gradient(circle at top right, rgba(174, 113, 255, 0.13), transparent 34%),
      rgba(9, 14, 32, 0.90);
    color: #f8faff;
    box-shadow:
      0 30px 90px rgba(0, 0, 0, 0.46),
      0 0 0 1px rgba(255, 255, 255, 0.05),
      0 0 42px rgba(67, 190, 255, 0.11);
    backdrop-filter: blur(20px) saturate(125%);
    -webkit-backdrop-filter: blur(20px) saturate(125%);
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card::before {
    content: "";
    position: absolute;
    inset: 0;
    padding: 1px;
    border-radius: inherit;
    background: var(--sccc-header-underline-gradient);
    opacity: 0.72;
    pointer-events: none;
    -webkit-mask:
      linear-gradient(#000 0 0) content-box,
      linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
            mask-composite: exclude;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card::after {
    content: "";
    position: absolute;
    inset-inline: 12%;
    inset-block-start: -2px;
    height: 2px;
    border-radius: 999px;
    background: var(--sccc-header-underline-gradient);
    filter: blur(1px);
    opacity: 0.9;
    pointer-events: none;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card_title,
  .sccc-business-directory-template.pmpro-no-access .pmpro_card h2 {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin: 0 0 0.9rem;
    color: #ffffff;
    font-family: var(--font-headline, inherit);
    font-size: clamp(1.55rem, 3vw, 2rem);
    font-weight: 800;
    line-height: 1.12;
    letter-spacing: -0.01em;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card_title svg,
  .sccc-business-directory-template.pmpro-no-access .pmpro_card h2 svg {
    flex: 0 0 auto;
    width: 1.35rem;
    height: 1.35rem;
    stroke: #8cc8ff;
    filter: drop-shadow(0 0 10px rgba(67, 190, 255, 0.35));
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card_content,
  .sccc-business-directory-template.pmpro-no-access .pmpro_card_content p {
    margin: 0;
    color: #b0b7c7;
    font-size: 1rem;
    line-height: 1.7;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    color: #cfd7e8;
    font-size: 0.95rem;
    line-height: 1.5;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.15rem;
    padding: 0.35rem 0.85rem;
    border: 1px solid rgba(113, 215, 255, 0.20);
    border-radius: 999px;
    background: rgba(8, 11, 26, 0.52);
    color: #eef6ff;
    font-weight: 700;
    text-decoration: none;
    box-shadow:
      0 0 0 1px rgba(255, 255, 255, 0.035),
      0 10px 24px rgba(67, 190, 255, 0.12);
    transition:
      transform 180ms ease,
      border-color 180ms ease,
      background-color 180ms ease,
      box-shadow 180ms ease;
  }

  .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions a:hover,
  .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions a:focus-visible {
    transform: translateY(-1px);
    border-color: rgba(113, 215, 255, 0.36);
    background: rgba(10, 14, 32, 0.68);
    box-shadow:
      0 0 0 1px rgba(255, 255, 255, 0.055),
      0 14px 34px rgba(67, 190, 255, 0.16);
    outline: none;
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access {
    --pmpro--color--accent: #2e57ff;
    color: #1c2433;
    background:
      radial-gradient(circle at 20% 20%, rgba(67, 190, 255, 0.14), transparent 30%),
      radial-gradient(circle at 82% 18%, rgba(174, 113, 255, 0.14), transparent 32%),
      linear-gradient(180deg, rgba(236, 242, 251, 0.96) 0%, rgba(229, 236, 247, 0.98) 100%);
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access::before {
    background:
      radial-gradient(circle at 26% 18%, rgba(67, 190, 255, 0.14), transparent 28%),
      radial-gradient(circle at 78% 14%, rgba(174, 113, 255, 0.12), transparent 30%),
      rgba(238, 242, 249, 0.74);
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card,
  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card.pmpro_content_message {
    border-color: rgba(67, 190, 255, 0.22);
    background:
      radial-gradient(circle at top left, rgba(67, 190, 255, 0.13), transparent 36%),
      radial-gradient(circle at top right, rgba(174, 113, 255, 0.11), transparent 34%),
      rgba(255, 255, 255, 0.90);
    color: #1c2433;
    box-shadow:
      0 30px 90px rgba(23, 31, 61, 0.15),
      0 0 0 1px rgba(255, 255, 255, 0.72),
      0 0 38px rgba(67, 190, 255, 0.10);
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_title,
  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card h2 {
    color: #1c2433;
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_title svg,
  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card h2 svg {
    stroke: #2e57ff;
    filter: drop-shadow(0 0 10px rgba(46, 87, 255, 0.18));
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_content,
  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_content p {
    color: #5f6c82;
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions {
    border-top-color: rgba(26, 39, 68, 0.08);
    color: #5f6c82;
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions a {
    border-color: rgba(67, 190, 255, 0.24);
    background: rgba(255, 255, 255, 0.78);
    color: #1c3b5a;
    box-shadow:
      0 0 0 1px rgba(255, 255, 255, 0.68),
      0 10px 24px rgba(67, 190, 255, 0.10);
  }

  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions a:hover,
  html[data-theme="light"] .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions a:focus-visible {
    border-color: rgba(67, 190, 255, 0.38);
    background: rgba(255, 255, 255, 0.94);
    box-shadow:
      0 0 0 1px rgba(255, 255, 255, 0.86),
      0 14px 34px rgba(67, 190, 255, 0.15);
  }

  @media (max-width: 640px) {
    .sccc-business-directory-template.pmpro-no-access .pmpro_card,
    .sccc-business-directory-template.pmpro-no-access .pmpro_card.pmpro_content_message {
      border-radius: 1.2rem;
    }

    .sccc-business-directory-template.pmpro-no-access .pmpro_card_title,
    .sccc-business-directory-template.pmpro-no-access .pmpro_card h2 {
      align-items: flex-start;
    }

    .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions {
      align-items: stretch;
    }

    .sccc-business-directory-template.pmpro-no-access .pmpro_card_actions a {
      width: 100%;
    }
  }

.sccc-business-directory-template__shell {
    position: relative;
    z-index: 1;
    padding: clamp(2.5rem, 5vw, 4rem) 0 clamp(4rem, 8vw, 6rem);
    background:
      var(--sccc-directory-gradient),
      linear-gradient(180deg, transparent 0%, rgba(127, 127, 127, 0.02) 100%);
  }

  .sccc-business-directory-template__container {
    width: min(100% - 2rem, 1280px);
    margin-inline: auto;
  }

  .sccc-business-directory-template__panel {
    border: 1px solid var(--sccc-directory-border);
    border-radius: 1.75rem;
    background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.82) 0%, rgba(248, 250, 255, 0.94) 100%);
    box-shadow: var(--sccc-directory-shadow);
    overflow: hidden;
  }

  html.dark[data-theme="dark"] .sccc-business-directory-template__panel,
  html[data-theme="dark"] .sccc-business-directory-template__panel {
    background: var(--sccc-directory-surface);
  }

  .sccc-business-directory__filters {
    padding: 1.25rem;
  }

  .sccc-business-directory__filters-grid {
    display: grid;
    gap: 1rem;
  }

  .sccc-business-directory__search-label,
  .sccc-business-directory__select-label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--sccc-directory-text);
  }

  .sccc-business-directory__search-wrap {
    position: relative;
  }

  .sccc-business-directory__search-icon {
    position: absolute;
    inset-inline-start: 1rem;
    inset-block-start: 50%;
    transform: translateY(-50%);
    font-size: 1.1rem;
    line-height: 1;
    color: var(--sccc-directory-text-muted);
    pointer-events: none;
  }

  .sccc-business-directory__search-input,
  .sccc-business-directory__select {
    width: 100%;
    min-height: 3.25rem;
    border: 1px solid var(--sccc-directory-border);
    border-radius: 1rem;
    background: rgba(235, 240, 248, 0.72);
    color: var(--sccc-directory-text);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55);
    transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
  }

  html.dark[data-theme="dark"] .sccc-business-directory__search-input,
  html.dark[data-theme="dark"] .sccc-business-directory__select,
  html[data-theme="dark"] .sccc-business-directory__search-input,
  html[data-theme="dark"] .sccc-business-directory__select {
    background: var(--sccc-directory-surface-alt);
    box-shadow: none;
  }

  .sccc-business-directory__search-input {
    padding: 0.875rem 1rem 0.875rem 3rem;
  }

  .sccc-business-directory__select {
    padding: 0.875rem 1rem;
  }

  .sccc-business-directory__search-input::placeholder {
    color: var(--sccc-directory-text-muted);
  }

  .sccc-business-directory__search-input:focus,
  .sccc-business-directory__select:focus {
    outline: none;
    border-color: color-mix(in srgb, var(--sccc-directory-primary) 55%, white 0%);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--sccc-directory-primary) 15%, transparent);
  }

  .sccc-business-directory__controls {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
    justify-content: space-between;
    margin-top: 1rem;
  }

  .sccc-business-directory__summary {
    margin: 0;
    font-size: 0.95rem;
    color: var(--sccc-directory-text-muted);
  }

  .sccc-business-directory__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: center;
  }

  .sccc-business-directory__neo-button {
    position: relative;
    display: inline-flex;
    align-items: stretch;
    justify-content: center;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: transparent;
    text-decoration: none;
    white-space: nowrap;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
    isolation: isolate;
    box-shadow:
      0 0 0 1px var(--sccc-directory-button-edge-stroke),
      var(--sccc-directory-button-glow);
    transition:
      transform 180ms ease,
      box-shadow 180ms ease,
      opacity 180ms ease;
  }

  .sccc-business-directory__neo-button::before {
    content: "";
    position: absolute;
    inset: 0;
    padding: var(--sccc-directory-button-ring-size);
    border-radius: inherit;
    background: var(--sccc-header-underline-gradient);
    pointer-events: none;
    z-index: -1;
    -webkit-mask:
      linear-gradient(#000 0 0) content-box,
      linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
            mask-composite: exclude;
  }

  .sccc-business-directory__neo-button::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: var(--sccc-header-underline-gradient);
    filter: blur(10px);
    opacity: 0.22;
    pointer-events: none;
    z-index: -2;
    transition: opacity 180ms ease;
  }

  .sccc-business-directory__neo-button:hover {
    transform: translateY(-1px);
    box-shadow:
      0 0 0 1px color-mix(in srgb, var(--sccc-directory-button-edge-stroke) 135%, white 0%),
      var(--sccc-directory-button-glow-hover);
  }

  .sccc-business-directory__neo-button:hover::after {
    opacity: 0.32;
  }

  .sccc-business-directory__neo-button-face {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2rem;
    min-width: 0;
    width: 100%;
    padding: 0.28rem 0.9rem;
    border-radius: inherit;
    background: var(--sccc-directory-button-face-bg);
    color: var(--sccc-directory-button-text);
    backdrop-filter: blur(14px) saturate(120%);
    -webkit-backdrop-filter: blur(14px) saturate(120%);
    box-shadow:
      inset 0 1px 0 var(--sccc-directory-button-face-stroke),
      inset 0 -1px 0 rgba(255, 255, 255, 0.03);
    transition:
      background 180ms ease,
      box-shadow 180ms ease,
      color 180ms ease;
  }

  .sccc-business-directory__neo-button:hover .sccc-business-directory__neo-button-face {
    background: var(--sccc-directory-button-face-bg-hover);
    box-shadow:
      inset 0 1px 0 color-mix(in srgb, var(--sccc-directory-button-face-stroke) 120%, white 0%),
      inset 0 -1px 0 rgba(255, 255, 255, 0.04);
  }

  .sccc-business-directory__neo-button-label,
  .sccc-business-directory__neo-button-icon {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: inherit;
  }

  .sccc-business-directory__neo-button-label {
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.01em;
  }

  .sccc-business-directory__neo-button-icon {
    font-size: 0.92rem;
    line-height: 1;
  }

  .sccc-business-directory__neo-button--ghost .sccc-business-directory__neo-button-face {
    color: var(--sccc-directory-button-text-muted);
  }

  .sccc-business-directory__neo-button--ghost .sccc-business-directory__neo-button-label {
    font-weight: 600;
  }

  .sccc-business-directory__neo-button--block {
    flex: 1 1 auto;
  }

  .sccc-business-directory__neo-button--icon {
    flex: 0 0 auto;
  }

  .sccc-business-directory__neo-button--icon .sccc-business-directory__neo-button-face {
    width: 2.35rem;
    min-width: 2.35rem;
    min-height: 2.35rem;
    padding: 0;
  }

  .sccc-business-directory__neo-button--icon .sccc-business-directory__neo-button-icon {
    font-size: 1rem;
  }

  .sccc-business-directory__neo-button--disabled {
    cursor: not-allowed;
    opacity: 0.62;
  }

  .sccc-business-directory__neo-button--disabled::after {
    opacity: 0.08;
  }

  .sccc-business-directory__results {
    padding: 1.25rem;
    border-top: 1px solid var(--sccc-directory-border);
  }

  .sccc-business-directory__grid {
    display: grid;
    gap: 1.25rem;
  }

  .sccc-business-directory__card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 100%;
    border: 1px solid rgba(38, 51, 86, 0.10);
    border-radius: 1.5rem;
    background:
      radial-gradient(circle at top right, rgba(176, 186, 255, 0.22), transparent 26%),
      linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(247, 249, 255, 0.98) 100%);
    box-shadow: var(--sccc-directory-shadow);
    overflow: hidden;
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
  }

  html.dark[data-theme="dark"] .sccc-business-directory__card,
  html[data-theme="dark"] .sccc-business-directory__card {
    border: 1px solid var(--sccc-directory-border);
    background: var(--sccc-directory-surface);
  }

  .sccc-business-directory__card:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--sccc-directory-primary) 20%, var(--sccc-directory-border));
    box-shadow: var(--sccc-directory-shadow-hover);
  }

  .sccc-business-directory__card-glow {
    position: absolute;
    inset-inline-end: -1.75rem;
    inset-block-start: -1.75rem;
    width: 7rem;
    height: 7rem;
    border-radius: 999px;
    background: radial-gradient(circle, color-mix(in srgb, var(--sccc-directory-primary) 22%, transparent) 0%, transparent 72%);
    pointer-events: none;
  }

  .sccc-business-directory__card-body {
    position: relative;
    z-index: 1;
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    gap: 1rem;
    padding: 1.5rem;
  }

  .sccc-business-directory__brand-row {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
  }

  .sccc-business-directory__logo {
    flex: 0 0 3.5rem;
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 999px;
    overflow: hidden;
    background: var(--sccc-directory-surface-alt);
    border: 1px solid var(--sccc-directory-border);
  }

  .sccc-business-directory__logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .sccc-business-directory__logo-fallback {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--sccc-directory-primary);
    background: color-mix(in srgb, var(--sccc-directory-primary) 10%, var(--sccc-directory-surface-alt));
  }

  .sccc-business-directory__business-name {
    margin: 0 0 0.35rem;
    font-size: 1.15rem;
    line-height: 1.2;
    color: var(--sccc-directory-text);
  }

  .sccc-business-directory__category {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    background: var(--sccc-directory-chip-bg);
    color: var(--sccc-directory-chip-text);
  }

  .sccc-business-directory__description {
    margin: 0;
    color: var(--sccc-directory-text-muted);
    line-height: 1.65;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }

  .sccc-business-directory__owner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: auto;
    padding: 0.85rem 0.95rem;
    border-radius: 1rem;
    background: rgba(232, 237, 246, 0.78);
  }

  html.dark[data-theme="dark"] .sccc-business-directory__owner,
  html[data-theme="dark"] .sccc-business-directory__owner {
    background: var(--sccc-directory-surface-alt);
  }

  .sccc-business-directory__owner-avatar {
    flex: 0 0 3rem;
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    overflow: hidden;
    background: var(--sccc-directory-surface);
    border: 1px solid var(--sccc-directory-border);
  }

  .sccc-business-directory__owner-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .sccc-business-directory__owner-label {
    display: block;
    margin-bottom: 0.15rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--sccc-directory-text-muted);
  }

  .sccc-business-directory__owner-name {
    display: block;
    color: var(--sccc-directory-text);
    font-weight: 700;
    line-height: 1.3;
  }

  .sccc-business-directory__card-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
  }

  .sccc-business-directory__empty {
    padding: clamp(2rem, 4vw, 3rem);
    text-align: center;
    border: 1px dashed var(--sccc-directory-border);
    border-radius: 1.5rem;
    background: color-mix(in srgb, var(--sccc-directory-surface-alt) 65%, transparent);
  }

  .sccc-business-directory__empty-title {
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
    color: var(--sccc-directory-text);
  }

  .sccc-business-directory__empty-text {
    margin: 0 auto;
    max-width: 38rem;
    color: var(--sccc-directory-text-muted);
    line-height: 1.7;
  }

  .sccc-business-directory__pagination {
    display: flex;
    justify-content: center;
    margin-top: 1.75rem;
  }

  .sccc-business-directory__pagination .page-numbers {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.875rem;
    min-height: 2.875rem;
    margin: 0 0.2rem;
    padding: 0.65rem 0.85rem;
    border-radius: 999px;
    border: 1px solid var(--sccc-directory-border);
    background: var(--sccc-directory-surface);
    color: var(--sccc-directory-text);
    text-decoration: none;
    transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease, color 180ms ease;
  }

  .sccc-business-directory__pagination .page-numbers:hover,
  .sccc-business-directory__pagination .page-numbers.current {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--sccc-directory-primary) 24%, var(--sccc-directory-border));
    background: color-mix(in srgb, var(--sccc-directory-primary) 10%, var(--sccc-directory-surface));
    color: var(--sccc-directory-primary);
  }

  .sccc-business-directory__pagination .page-numbers.dots {
    transform: none;
    background: transparent;
    border-color: transparent;
    color: var(--sccc-directory-text-muted);
  }

  @media (min-width: 768px) {
    .sccc-business-directory__filters-grid {
      grid-template-columns: minmax(0, 1.45fr) repeat(2, minmax(0, 0.85fr));
      align-items: end;
    }

    .sccc-business-directory__grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (min-width: 1140px) {
    .sccc-business-directory__grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 767px) {
    .sccc-business-directory__filters,
    .sccc-business-directory__results {
      padding: 1rem;
    }

    .sccc-business-directory__controls,
    .sccc-business-directory__actions,
    .sccc-business-directory__card-actions {
      flex-direction: column;
      align-items: stretch;
    }

    .sccc-business-directory__actions .sccc-business-directory__neo-button {
      width: 100%;
    }

    .sccc-business-directory__card-actions .sccc-business-directory__neo-button--icon {
      width: 100%;
    }

    .sccc-business-directory__card-actions .sccc-business-directory__neo-button--icon .sccc-business-directory__neo-button-face {
      width: 100%;
      min-width: 0;
    }
  }
</style>

<script>
(() => {
  /**
   * Lock document scrolling when PMPro outputs a no-access message.
   *
   * Why:
   * The PMPro card is styled as a fixed modal. Adding a class to the html/body
   * elements prevents the header, footer, or any remaining page chrome from
   * scrolling behind the modal overlay.
   */
  const lockRestrictedDirectoryScroll = () => {
    const restrictedTemplate = document.querySelector('.sccc-business-directory-template.pmpro-no-access');

    if (!restrictedTemplate) {
      return;
    }

    document.documentElement.classList.add('sccc-restricted-modal-open');
    document.body.classList.add('sccc-restricted-modal-open');
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', lockRestrictedDirectoryScroll, { once: true });
    return;
  }

  lockRestrictedDirectoryScroll();
})();
</script>