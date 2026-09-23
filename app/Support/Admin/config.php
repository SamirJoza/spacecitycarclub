<?php

/**
 * SCCC Admin Branding (theme-based, no /public dependency)
 * Uses your Tailwind config palette + gradient-section vibe.
 */

/** Hide the scheme picker */
// add_action('admin_head-profile.php', function () {
//   remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
// });
// add_action('admin_head-user-edit.php', function () {
//   remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
// });

// /** Force scheme class so our CSS is scoped cleanly */
// add_filter('get_user_option_admin_color', function ($value) {
//   return 'sccc';
// }, 1);

// /** Inject the admin CSS */
// add_action('admin_head', function () {
//   $primary      = '#99b5ef';
//   $bg_dark      = '#101622';
//   $surface_dark = '#1a2233';

//   // ultra-subtle: basically bg-dark with a faint blue wash
//   $bg_grad = "linear-gradient(180deg,
//     rgba(16,22,34,1) 0%,
//     rgba(19,91,236,0.04) 40%,
//     rgba(16,22,34,1) 100%
//   )";

//   echo <<<HTML
// <style id="sccc-admin-colors">
// body.admin-color-sccc{
//   --sccc-primary: {$primary};
//   --sccc-bg: {$bg_dark};
//   --sccc-surface: {$surface_dark};

//   --wp-admin-theme-color: var(--sccc-primary);
//   --wp-admin-theme-color-darker-10: #0f4bca;
//   --wp-admin-theme-color-darker-20: #0c3fae;
//   --wp-admin-theme-color-lighter-10: #2f6cf0;
//   --wp-admin-theme-color-lighter-20: #4b82f4;
// }

// /* ============================================================
//    Canvas background (no grey gaps, subtle gradient everywhere)
// ============================================================ */
// body.admin-color-sccc,
// body.admin-color-sccc #wpwrap,
// body.admin-color-sccc #wpcontent,
// body.admin-color-sccc #wpbody,
// body.admin-color-sccc #wpbody-content{
//   background: {$bg_dark} !important;
// }

// body.admin-color-sccc #wpwrap{ min-height: 100vh; }

// /* Admin bar */
// body.admin-color-sccc #wpadminbar{
//   background: var(--sccc-surface) !important;
// }

// /* Sidebar */
// body.admin-color-sccc #adminmenu,
// body.admin-color-sccc #adminmenuback,
// body.admin-color-sccc #adminmenuwrap{
//   background: #0a0a0a !important;
// }

// body.admin-color-sccc #adminmenu a{ color: rgba(255,255,255,.72) !important; }
// body.admin-color-sccc #adminmenu a:hover,
// body.admin-color-sccc #adminmenu li.menu-top:hover > a{ color: #fff !important; }

// body.admin-color-sccc #adminmenu .wp-has-current-submenu > a,
// body.admin-color-sccc #adminmenu .current a.menu-top,
// body.admin-color-sccc #wpfooter{
//   background: var(--sccc-surface) !important;
//   color: #fff !important;
// }

// body.admin-color-sccc #adminmenu .wp-has-current-submenu > a::after,
// body.admin-color-sccc #adminmenu .current a.menu-top::after{
//   border-right-color: var(--sccc-primary) !important;
// }

// /* Links + buttons */
// body.admin-color-sccc a{ color: var(--sccc-primary) !important; }

// body.admin-color-sccc .wp-core-ui .button-primary{
//   background: var(--sccc-primary) !important;
//   border-color: #0f4bca !important;
// }
// body.admin-color-sccc .wp-core-ui .button-primary:hover,
// body.admin-color-sccc .wp-core-ui .button-primary:focus{
//   background: #2f6cf0 !important;
//   border-color: var(--sccc-primary) !important;
// }

// /* ============================================================
//    Widgets / cards: lift + soft glow (dashboard + most admin screens)
// ============================================================ */
// body.admin-color-sccc .postbox,
// body.admin-color-sccc .stuffbox,
// body.admin-color-sccc .card,
// body.admin-color-sccc .notice,
// body.admin-color-sccc .welcome-panel,
// body.admin-color-sccc .dashboard-widget{
//   border-color: rgba(255,255,255,.10) !important;
//   box-shadow:
//     0 10px 30px rgba(0,0,0,.35),
//     0 0 0 1px rgba(19,91,236,.06),
//     0 0 18px rgba(19,91,236,.08) !important;
// }

// /* Slight “pop” on hover without being cheesy */
// body.admin-color-sccc .postbox:hover,
// body.admin-color-sccc .card:hover{
//   box-shadow:
//     0 14px 40px rgba(0,0,0,.42),
//     0 0 0 1px rgba(19,91,236,.10),
//     0 0 24px rgba(19,91,236,.12) !important;
// }

// /* Optional: tighten the big white page title contrast */
// body.admin-color-sccc .wrap h1{ color:#fff !important; }
// </style>
// HTML;
// });
