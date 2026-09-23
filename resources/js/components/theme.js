// resources/scripts/theme.js

/**
 * Space City Car Club theme controller.
 *
 * This file controls the site's light/dark theme behavior.
 *
 * Project decision:
 * - The site defaults to dark mode.
 * - The user's operating system color preference is intentionally ignored.
 * - If the visitor manually chooses light or dark mode, that choice is saved
 *   in localStorage and reused on future visits.
 */

const STORAGE_KEY = 'scc-theme';
const DEFAULT_THEME = 'dark';

/**
 * Returns the theme that should be used when there is no saved user choice.
 *
 * We intentionally default to dark mode here instead of checking
 * prefers-color-scheme. This keeps the SCCC visual identity consistent
 * for first-time visitors.
 */
function computePreferred() {
  return DEFAULT_THEME;
}

/**
 * Applies the active theme to the document.
 *
 * The important output is:
 *
 * Dark:
 * <html class="dark" data-theme="dark">
 *
 * Light:
 * <html data-theme="light">
 *
 * The .dark class supports Tailwind-style dark selectors if needed,
 * while data-theme supports the project's token-driven CSS system.
 */
export function applyTheme(mode /* 'dark' | 'light' | null */) {
  const html = document.documentElement;
  const use = (mode === 'dark' || mode === 'light') ? mode : computePreferred();

  html.classList.toggle('dark', use === 'dark');
  html.setAttribute('data-theme', use);

  /**
   * Update every theme toggle on the page.
   *
   * This keeps desktop, mobile, and off-canvas toggle buttons visually
   * synchronized after the theme changes.
   */
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.setAttribute('aria-pressed', use === 'dark' ? 'true' : 'false');

    const sun = btn.querySelector('[data-icon="sun"]');
    const moon = btn.querySelector('[data-icon="moon"]');

    if (sun) {
      sun.classList.toggle('hidden', use !== 'light');
    }

    if (moon) {
      moon.classList.toggle('hidden', use !== 'dark');
    }
  });
}

/**
 * Initializes the theme system.
 *
 * Load order:
 * 1. Use the saved theme if one exists.
 * 2. Otherwise default to dark mode.
 * 3. Listen for clicks on any theme toggle.
 * 4. Re-sync toggles that are added later, such as mobile drawer copies.
 */
export function initTheme() {
  /**
   * Apply saved theme or default dark theme.
   *
   * localStorage returns null when the key does not exist, so applyTheme(null)
   * falls back to computePreferred(), which now returns "dark".
   */
  const saved = localStorage.getItem(STORAGE_KEY);
  applyTheme(saved || null);

  /**
   * Delegated click handler.
   *
   * This allows theme toggles to work even when they are added to the DOM later.
   */
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-theme-toggle]');

    if (!btn) {
      return;
    }

    const isDark = document.documentElement.classList.contains('dark');
    const next = isDark ? 'light' : 'dark';

    localStorage.setItem(STORAGE_KEY, next);
    applyTheme(next);
  });

  /**
   * Watch for newly added toggle buttons.
   *
   * This is useful for mobile/off-canvas drawers that may be injected after
   * the initial page load. MutationObserver is the correct browser API for
   * watching DOM changes like this. 
   */
  const mo = new MutationObserver((records) => {
    for (const r of records) {
      if (r.type === 'childList' && r.addedNodes && r.addedNodes.length) {
        const found = [...r.addedNodes].some((n) =>
          n.nodeType === 1 &&
          (
            n.matches?.('[data-theme-toggle]') ||
            n.querySelector?.('[data-theme-toggle]')
          )
        );

        if (found) {
          const current = localStorage.getItem(STORAGE_KEY) || null;
          applyTheme(current);
          break;
        }
      }
    }
  });

  mo.observe(document.body, {
    childList: true,
    subtree: true,
  });

  /**
   * Final sync pass.
   *
   * This makes sure existing toggles are correct after initialization.
   */
  applyTheme(saved || null);
}