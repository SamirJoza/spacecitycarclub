/**
 * File: resources/scripts/sccc-event-grid-cleanup.js
 * Path: resources/scripts/sccc-event-grid-cleanup.js
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Frontend cleanup for the MagePeople / WpEvently event grid cards.
 *
 * Why this exists
 * -----------------------------------------------------------------------------
 * The plugin outputs a price wrapper like:
 *
 *   .list_price
 *     .list_price_label  -> Price:
 *     .list_price_value  -> Free
 *
 * CSS cannot reliably hide a parent element based on a child element's text
 * content. This script checks the rendered text value and hides the whole
 * .list_price block only when the value is exactly "Free".
 *
 * This preserves paid event prices while removing useless "Price: Free" output
 * from free event cards.
 *
 * Plugin update safety
 * -----------------------------------------------------------------------------
 * A MutationObserver is used because the event grid can load more cards or
 * update markup dynamically through pagination/filtering.
 */

(() => {
    /**
     * Event grid scope.
     *
     * Keep this narrow so the script only touches MagePeople event cards and does
     * not accidentally hide price text elsewhere on the site.
     */
    const eventGridSelector = '#mage-container.mep_event_list';
  
    /**
     * Plugin price selectors.
     *
     * .list_price is the full wrapper we want to hide.
     * .list_price_value is the value we inspect.
     */
    const priceBlockSelector = '.list_price';
    const priceValueSelector = '.list_price_value';
  
    /**
     * Normalize plugin text output.
     *
     * This makes the check resilient against extra spaces, tabs, line breaks, and
     * casing differences such as "Free", "FREE", or " free ".
     */
    const normalizeText = (value) => value.trim().replace(/\s+/g, ' ').toLowerCase();
  
    /**
     * Hide or restore one price block.
     *
     * Inline display is used intentionally because the existing CSS file styles
     * .list_price with display rules and !important. This keeps the JS decision
     * stronger without needing another CSS-only workaround.
     */
    const updatePriceBlock = (priceBlock) => {
      const priceValue = priceBlock.querySelector(priceValueSelector);
  
      if (!priceValue) {
        return;
      }
  
      const value = normalizeText(priceValue.textContent || '');
      const shouldHide = value === 'free';
  
      if (shouldHide) {
        priceBlock.style.setProperty('display', 'none', 'important');
        priceBlock.setAttribute('data-sccc-free-price-hidden', 'true');
        priceBlock.setAttribute('aria-hidden', 'true');
        return;
      }
  
      /**
       * Restore the block only if this script hid it before.
       * That prevents us from undoing display styles set elsewhere.
       */
      if (priceBlock.getAttribute('data-sccc-free-price-hidden') === 'true') {
        priceBlock.style.removeProperty('display');
        priceBlock.removeAttribute('data-sccc-free-price-hidden');
        priceBlock.removeAttribute('aria-hidden');
      }
    };
  
    /**
     * Scan all current event cards.
     */
    const cleanupFreePrices = () => {
      document
        .querySelectorAll(`${eventGridSelector} ${priceBlockSelector}`)
        .forEach(updatePriceBlock);
    };
  
    /**
     * Observe dynamic plugin updates.
     *
     * requestAnimationFrame prevents repeated rapid DOM changes from triggering
     * the cleanup too aggressively.
     */
    const watchEventGridChanges = () => {
      let frame = null;
  
      const scheduleCleanup = () => {
        if (frame) {
          return;
        }
  
        frame = window.requestAnimationFrame(() => {
          frame = null;
          cleanupFreePrices();
        });
      };
  
      cleanupFreePrices();
  
      const observer = new MutationObserver(scheduleCleanup);
  
      observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true,
      });
    };
  
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', watchEventGridChanges, { once: true });
    } else {
      watchEventGridChanges();
    }
  })();