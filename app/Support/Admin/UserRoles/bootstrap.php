<?php
/**
 * File: app/Support/Admin/UserRoles/bootstrap.php
 *
 * Boots the Space City Car Club operational-role subsystem:
 * - Registers operational roles + marker capabilities (`OperationalRoles`)
 * - Adds the Edit User “secondary operational roles” UI (`SecondaryRoleAssignment`)
 * - Applies wp-admin / admin bar / WooCommerce access rules (`AdminDashboardAccess`)
 * - Strips admin menus + blocks direct URLs for operational roles (`AdminMenus`, `AdminAccess`)
 * - Colours the admin bar by highest operational role (`AdminBarAppearance`)
 * - Seeds first-time Dashboard widget layout from a template user (`AdminDashboardLayoutTemplate`)
 *
 * Load order
 * - Include this file from `app/setup.php` **before** dashboard widgets that rely
 *   on capabilities such as `sccc_use_dashboard_widgets`.
 *
 * Relationship to legacy `ManualRoleAssignment.php`
 * - That module exposed every role as a checkbox and could fight this flow.
 * - Keep it disabled while this bootstrap is active to avoid duplicate UIs.
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

require_once __DIR__ . '/OperationalRoles.php';
require_once __DIR__ . '/SecondaryRoleAssignment.php';
require_once __DIR__ . '/AdminDashboardAccess.php';
require_once __DIR__ . '/AdminBarAppearance.php';
require_once __DIR__ . '/AdminMenus.php';
require_once __DIR__ . '/AdminAccess.php';
require_once __DIR__ . '/AdminDashboardLayoutTemplate.php';

OperationalRoles::register();
SecondaryRoleAssignment::register();
AdminDashboardAccess::register();
AdminBarAppearance::register();
AdminMenus::register();
AdminAccess::register();
AdminDashboardLayoutTemplate::register();
