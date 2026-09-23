<?php
/**
 * File path + filename: app/Options/ThemeSettings.php
 *
 * Purpose:
 * - Register the top-level "Theme Settings" admin page.
 * - Act as the parent container for all Theme Settings child pages.
 * - Inject a shared left-side in-page navigation for all Theme Settings pages.
 * - Move the native Update box into a right-side actions sidebar.
 *
 * Why this file exists:
 * - The previous single-page tabbed options UI became too crowded.
 * - Splitting settings into child pages is cleaner and scales better.
 * - Each child page now needs a shared local navigation menu so editors can
 *   jump between sibling settings pages without relying only on the WordPress
 *   admin submenu.
 * - The Update button should live in a proper right-side admin actions area,
 *   not float above the main content column.
 *
 * Important notes:
 * - This parent page intentionally has no editable fields.
 * - Field names used by the child pages remain unchanged, so existing frontend
 *   partials using get_field('field_name', 'option') do not need to change.
 * - The parent page redirects to the first child page.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class ThemeSettings extends Field
{
    /**
     * The top-level admin menu label.
     *
     * @var string
     */
    public $name = 'Theme Settings';

    /**
     * The admin page title.
     *
     * @var string
     */
    public $title = 'Theme Settings | Options';

    /**
     * Explicit slug for the parent options page.
     *
     * Why this exists:
     * - Child pages use this slug as their parent.
     * - The shared in-page navigation also uses this stable slug family.
     *
     * @var string
     */
    public $slug = 'theme-settings';

    /**
     * The parent page should redirect to the first child page.
     *
     * Why this exists:
     * - The parent page acts as a container only.
     * - Editors should land on a real settings page immediately.
     *
     * @var bool
     */
    public $redirect = true;

    /**
     * The option page field group.
     *
     * Why this is intentionally empty:
     * - All real settings live on the child pages.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('theme_settings_parent');

        return $fields->build();
    }
}

/**
 * -----------------------------------------------------------------------------
 * Shared Theme Settings page registry
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Define the sibling settings pages once so both the in-page navigation and
 *   page guards can use the same source of truth.
 *
 * Why this exists:
 * - Prevents repeating slugs/titles in multiple places.
 * - Makes the custom left menu easy to extend later.
 * -----------------------------------------------------------------------------
 */
if (! function_exists(__NAMESPACE__ . '\\themeSettingsPages')) {
    /**
     * Return the full list of Theme Settings pages used by the shared nav.
     *
     * @return array<int, array<string, string>>
     */
    function themeSettingsPages(): array
    {
        return [
            [
                'slug'  => 'theme-settings-social-connections',
                'label' => 'Social Connections',
                'icon'  => 'dashicons-share',
            ],
            [
                'slug'  => 'theme-settings-newsletter-section',
                'label' => 'Newsletter Section',
                'icon'  => 'dashicons-email-alt',
            ],
            [
                'slug'  => 'theme-settings-business-directory-cta',
                'label' => 'Business Directory CTA',
                'icon'  => 'dashicons-store',
            ],
            [
                'slug'  => 'theme-settings-shop-settings',
                'label' => 'Shop Settings',
                'icon'  => 'dashicons-cart',
            ],
            [
                'slug'  => 'theme-settings-sponsor-single-cta',
                'label' => 'Sponsor Single CTA',
                'icon'  => 'dashicons-awards',
            ],
            [
                'slug'  => 'theme-settings-faq-help-cta',
                'label' => 'FAQ Help CTA',
                'icon'  => 'dashicons-editor-help',
            ],
            [
                'slug'  => 'theme-settings-protected-frontend-pages',
                'label' => 'Protected Frontend Pages',
                'icon'  => 'dashicons-lock',
            ],
        ];
    }
}

if (! function_exists(__NAMESPACE__ . '\\themeSettingsPageSlugs')) {
    /**
     * Flatten the page registry into an array of slugs.
     *
     * @return array<int, string>
     */
    function themeSettingsPageSlugs(): array
    {
        return array_map(
            static fn(array $item): string => (string) $item['slug'],
            themeSettingsPages()
        );
    }
}

/**
 * -----------------------------------------------------------------------------
 * Shared Theme Settings admin assets
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Add an in-page left navigation to each Theme Settings child page.
 * - Move the Update box into a right-side actions sidebar.
 * - Keep the styling scoped only to Theme Settings admin screens.
 *
 * Why this uses admin_enqueue_scripts:
 * - This is the correct WordPress hook for admin-only CSS and JS.
 * - It lets us scope assets to the exact admin pages we want.
 * -----------------------------------------------------------------------------
 */
\add_action('admin_enqueue_scripts', static function (string $hookSuffix): void {
    $page = isset($_GET['page']) ? \sanitize_key((string) $_GET['page']) : '';
    $allowedPages = themeSettingsPageSlugs();

    if (! in_array($page, $allowedPages, true)) {
        return;
    }

    $navItems = array_map(static function (array $item): array {
        return [
            'slug'  => $item['slug'],
            'label' => $item['label'],
            'icon'  => $item['icon'],
            'url'   => \admin_url('admin.php?page=' . $item['slug']),
        ];
    }, themeSettingsPages());

    \wp_register_style('sccc-theme-settings-pages-nav', false, [], null);
    \wp_enqueue_style('sccc-theme-settings-pages-nav');

    $css = <<<'CSS'
/* ==========================================================================
   File path + filename: app/Options/ThemeSettings.php
   Shared admin-only layout for Theme Settings child pages
   ========================================================================== */

body.sccc-theme-settings-enhanced .wrap > h1 {
  margin-bottom: 1rem;
  font-size: 1.8rem;
  line-height: 1.12;
  font-weight: 700;
  letter-spacing: -0.02em;
}

body.sccc-theme-settings-enhanced #poststuff {
  padding-top: 0;
  min-width: 0;
  margin-top: 0 !important;
}

body.sccc-theme-settings-enhanced #post-body,
body.sccc-theme-settings-enhanced #post-body-content,
body.sccc-theme-settings-enhanced .postbox-container {
  margin: 0 !important;
  width: 100% !important;
  float: none !important;
  min-width: 0;
}

/*
|--------------------------------------------------------------------------
| Blank-gap fix
|--------------------------------------------------------------------------
| Why this exists:
| - On options pages, WordPress still outputs the native postbox container
|   structure used by the classic editor screen.
| - After we move the submit box into our right actions sidebar, the original
|   container can remain behind as an empty block and push the content down.
| - These rules remove the old two-column spacing behavior and hide the empty
|   container once JS marks it.
|--------------------------------------------------------------------------
*/
body.sccc-theme-settings-enhanced #post-body.columns-2 {
  margin-right: 0 !important;
  min-height: 0 !important;
}

body.sccc-theme-settings-enhanced #post-body-content {
  margin-right: 0 !important;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-postbox-container--empty {
  display: none !important;
}

body.sccc-theme-settings-enhanced .postbox {
  border: 1px solid #d9e1ec;
  border-radius: 24px;
  overflow: hidden;
  box-shadow:
    0 16px 40px rgba(15, 23, 42, 0.06),
    0 4px 14px rgba(15, 23, 42, 0.04);
  background: #f6f8fc;
}

body.sccc-theme-settings-enhanced .postbox-header {
  display: none;
}

body.sccc-theme-settings-enhanced .inside {
  margin: 0 !important;
  padding: 0 !important;
  background:
    radial-gradient(1200px 700px at 0% 0%, rgba(55, 125, 255, 0.05), transparent 50%),
    linear-gradient(180deg, #f7f9fc 0%, #f3f6fb 100%);
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-shell {
  display: grid;
  grid-template-columns: 18rem minmax(0, 1fr) 18rem;
  gap: 1.5rem;
  align-items: start;
  margin-top: 0;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-sidebar,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions {
  position: sticky;
  top: 1rem;
  align-self: start;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-sidebar__card,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions__card {
  border: 1px solid #d9e1ec;
  border-radius: 24px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(246, 249, 255, 0.96));
  box-shadow:
    0 16px 40px rgba(15, 23, 42, 0.06),
    0 4px 14px rgba(15, 23, 42, 0.04);
  padding: 1rem;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-sidebar__eyebrow,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions__eyebrow {
  margin: 0 0 0.9rem;
  padding: 0.3rem 0.65rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border-radius: 999px;
  background: #edf4ff;
  color: #0e45b8;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-sidebar__title,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions__title {
  margin: 0 0 1rem;
  font-size: 1rem;
  line-height: 1.3;
  font-weight: 700;
  color: #162536;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-nav {
  display: grid;
  gap: 0.65rem;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-nav__link {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  min-height: 3.25rem;
  padding: 0.95rem 1rem;
  border: 1px solid #d8e0ec;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.84);
  color: #1d2b3a;
  text-decoration: none;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.35;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.7),
    0 6px 18px rgba(15, 23, 42, 0.04);
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    background-color 0.18s ease,
    color 0.18s ease;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-nav__link:hover {
  border-color: #b7c9e8;
  background: rgba(255, 255, 255, 0.96);
  color: #102030;
  transform: translateY(-1px);
  box-shadow:
    0 12px 24px rgba(15, 23, 42, 0.06),
    0 0 0 1px rgba(37, 99, 235, 0.06);
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-nav__link.is-active {
  border-color: #6ea8ff;
  background: linear-gradient(180deg, rgba(236, 245, 255, 0.98), rgba(228, 239, 255, 0.98));
  color: #0e45b8;
  box-shadow:
    0 14px 30px rgba(37, 99, 235, 0.10),
    0 0 0 1px rgba(37, 99, 235, 0.10);
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-nav__icon {
  width: 1.95rem;
  height: 1.95rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 999px;
  background: linear-gradient(180deg, #f0f5ff 0%, #e7efff 100%);
  color: #2563eb;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.75),
    0 0 0 1px rgba(37, 99, 235, 0.08);
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-nav__label {
  min-width: 0;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-main {
  min-width: 0;
}

/* --------------------------------------------------------------------------
   Right actions sidebar / moved submit box
   -------------------------------------------------------------------------- */

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions.is-hidden {
  display: none;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv {
  margin: 0 !important;
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  background: transparent !important;
  overflow: visible !important;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv .inside {
  margin: 0 !important;
  padding: 0 !important;
  background: transparent !important;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #minor-publishing,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #misc-publishing-actions,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv .misc-pub-section {
  display: none !important;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #major-publishing-actions {
  margin: 0 !important;
  padding: 0 !important;
  border: 0 !important;
  background: transparent !important;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #publishing-action {
  float: none !important;
  width: 100%;
  margin: 0 !important;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #publishing-action .button,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #publishing-action .button-primary,
body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #publishing-action .button-large {
  width: 100%;
  min-height: 3rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions #submitdiv #delete-action {
  display: none !important;
}

body.sccc-theme-settings-enhanced .sccc-theme-settings-actions .spinner {
  float: none !important;
  margin: 0.75rem 0 0 !important;
}

/* --------------------------------------------------------------------------
   Main form pane
   -------------------------------------------------------------------------- */

body.sccc-theme-settings-enhanced .acf-fields,
body.sccc-theme-settings-enhanced .acf-field {
  border-color: #e6ecf4;
}

body.sccc-theme-settings-enhanced .acf-label label {
  font-size: 0.96rem;
  font-weight: 700;
  color: #162536;
}

body.sccc-theme-settings-enhanced .description {
  color: #617086;
}

body.sccc-theme-settings-enhanced .acf-input input[type="text"],
body.sccc-theme-settings-enhanced .acf-input input[type="url"],
body.sccc-theme-settings-enhanced .acf-input textarea,
body.sccc-theme-settings-enhanced .acf-input select {
  border-color: #d5deeb;
  border-radius: 0.95rem;
  min-height: 2.85rem;
  box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.02);
}

body.sccc-theme-settings-enhanced .acf-button,
body.sccc-theme-settings-enhanced .acf-actions .button,
body.sccc-theme-settings-enhanced .button.button-primary {
  border-radius: 999px;
}

@media (max-width: 1380px) {
  body.sccc-theme-settings-enhanced .sccc-theme-settings-shell {
    grid-template-columns: 18rem minmax(0, 1fr);
  }

  body.sccc-theme-settings-enhanced .sccc-theme-settings-actions {
    grid-column: 1 / -1;
    position: relative;
    top: auto;
  }
}

@media (max-width: 1100px) {
  body.sccc-theme-settings-enhanced .sccc-theme-settings-shell {
    grid-template-columns: 1fr;
  }

  body.sccc-theme-settings-enhanced .sccc-theme-settings-sidebar,
  body.sccc-theme-settings-enhanced .sccc-theme-settings-actions {
    position: relative;
    top: auto;
  }

  body.sccc-theme-settings-enhanced .sccc-theme-settings-nav {
    grid-template-columns: 1fr;
  }
}
CSS;

    \wp_add_inline_style('sccc-theme-settings-pages-nav', $css);

    \wp_register_script('sccc-theme-settings-pages-nav', false, [], null, true);
    \wp_enqueue_script('sccc-theme-settings-pages-nav');

    $navJson = \wp_json_encode($navItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $js = <<<JS
document.addEventListener('DOMContentLoaded', function () {
  const navItems = {$navJson};
  const currentPage = new URLSearchParams(window.location.search).get('page');

  if (!currentPage || !Array.isArray(navItems) || !navItems.length) {
    return;
  }

  document.body.classList.add('sccc-theme-settings-enhanced');

  if (document.querySelector('.sccc-theme-settings-shell')) {
    return;
  }

  const poststuff = document.getElementById('poststuff');
  if (!poststuff || !poststuff.parentNode) {
    return;
  }

  const insertionParent = poststuff.parentNode;
  const submitBox = insertionParent.querySelector('#submitdiv');
  const submitContainer = submitBox ? submitBox.closest('.postbox-container') : null;

  const shell = document.createElement('div');
  shell.className = 'sccc-theme-settings-shell';

  const sidebar = document.createElement('aside');
  sidebar.className = 'sccc-theme-settings-sidebar';

  const sidebarCard = document.createElement('div');
  sidebarCard.className = 'sccc-theme-settings-sidebar__card';

  const eyebrow = document.createElement('div');
  eyebrow.className = 'sccc-theme-settings-sidebar__eyebrow';
  eyebrow.textContent = 'Theme Settings';

  const title = document.createElement('h2');
  title.className = 'sccc-theme-settings-sidebar__title';
  title.textContent = 'Settings Sections';

  const nav = document.createElement('nav');
  nav.className = 'sccc-theme-settings-nav';
  nav.setAttribute('aria-label', 'Theme Settings sections');

  navItems.forEach(function (item) {
    const link = document.createElement('a');
    link.className = 'sccc-theme-settings-nav__link';

    if (item.slug === currentPage) {
      link.classList.add('is-active');
      link.setAttribute('aria-current', 'page');
    }

    link.href = item.url;

    const icon = document.createElement('span');
    icon.className = 'sccc-theme-settings-nav__icon dashicons ' + item.icon;
    icon.setAttribute('aria-hidden', 'true');

    const label = document.createElement('span');
    label.className = 'sccc-theme-settings-nav__label';
    label.textContent = item.label;

    link.appendChild(icon);
    link.appendChild(label);
    nav.appendChild(link);
  });

  sidebarCard.appendChild(eyebrow);
  sidebarCard.appendChild(title);
  sidebarCard.appendChild(nav);
  sidebar.appendChild(sidebarCard);

  const main = document.createElement('div');
  main.className = 'sccc-theme-settings-main';

  const actions = document.createElement('aside');
  actions.className = 'sccc-theme-settings-actions';

  const actionsCard = document.createElement('div');
  actionsCard.className = 'sccc-theme-settings-actions__card';

  const actionsEyebrow = document.createElement('div');
  actionsEyebrow.className = 'sccc-theme-settings-actions__eyebrow';
  actionsEyebrow.textContent = 'Actions';

  const actionsTitle = document.createElement('h2');
  actionsTitle.className = 'sccc-theme-settings-actions__title';
  actionsTitle.textContent = 'Save Changes';

  actionsCard.appendChild(actionsEyebrow);
  actionsCard.appendChild(actionsTitle);

  if (submitBox) {
    actionsCard.appendChild(submitBox);
  } else {
    actions.classList.add('is-hidden');
  }

  actions.appendChild(actionsCard);

  shell.appendChild(sidebar);
  shell.appendChild(main);

  if (!actions.classList.contains('is-hidden')) {
    shell.appendChild(actions);
  }

  insertionParent.insertBefore(shell, poststuff);
  main.appendChild(poststuff);

  /*
  |--------------------------------------------------------------------------
  | Blank-gap fix
  |--------------------------------------------------------------------------
  | Why this exists:
  | - After moving the submit box, the original native postbox container can
  |   remain in the DOM as an empty block above the content.
  | - That empty block is what causes the main column to start too low.
  | - We mark it and let CSS hide it.
  |--------------------------------------------------------------------------
  */
  if (submitContainer) {
    const remainingPostboxes = submitContainer.querySelectorAll('.postbox');

    if (remainingPostboxes.length === 0) {
      submitContainer.classList.add('sccc-theme-settings-postbox-container--empty');
    }
  }
});
JS;

    \wp_add_inline_script('sccc-theme-settings-pages-nav', $js);
});