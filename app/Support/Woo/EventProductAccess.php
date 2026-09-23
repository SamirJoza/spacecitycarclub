<?php

/**
 * File path + filename: app/Support/Woo/EventProductAccess.php
 *
 * Space City Car Club event access and event-mode behavior for
 * MagePeople / WooCommerce Event Manager.
 *
 * Purpose:
 * - Adds a fixed checkbox-style "Event Flags" taxonomy to MagePeople events.
 * - Keeps event categories free for navigation.
 * - Keeps WooCommerce product tags free for products.
 * - Controls visibility for members-only events.
 * - Treats RSVP events as free WooCommerce checkout registrations.
 * - Treats info-only events as non-registration events.
 *
 * Functional event flags:
 * - sccc-event-info
 *   Info only. No checkout / no registration.
 *
 * - sccc-event-rsvp
 *   Free registration through the normal event checkout flow.
 *   Set ticket/registration price to $0.00 in the event plugin.
 *
 * - sccc-event-ticketed
 *   Paid registration through the normal event checkout flow.
 *
 * - sccc-event-members-only
 *   Event visible only to users with the sccc_member role.
 *
 * Important:
 * - Logged-in WooCommerce customers are NOT members unless they have the sccc_member role.
 * - By default, events are public.
 * - RSVP events are allowed through checkout.
 * - Only info-only events are blocked from checkout/registration.
 * - Event Flags are displayed as checkboxes in the event editor.
 * - Event Flags cannot be created from the event editor.
 * - Event Flags do not print frontend notices/messages.
 * - Only one event mode flag can be selected at a time.
 */

namespace App\Support\Woo;

use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;
use WP_User;

final class EventProductAccess
{
    /**
     * Prevent duplicate hook registration if file is loaded more than once.
     */
    private static bool $registered = false;

    /**
     * MagePeople event post type.
     */
    private const EVENT_POST_TYPE = 'mep_events';

    /**
     * SCCC member role.
     */
    private const MEMBER_ROLE = 'sccc_member';

    /**
     * Functional event flag taxonomy.
     */
    private const EVENT_FLAG_TAXONOMY = 'sccc_event_flag';

    /**
     * Event mode: information only.
     */
    private const FLAG_INFO = 'sccc-event-info';

    /**
     * Event mode: free RSVP / free registration through checkout.
     */
    private const FLAG_RSVP = 'sccc-event-rsvp';

    /**
     * Event mode: paid/ticketed event.
     */
    private const FLAG_TICKETED = 'sccc-event-ticketed';

    /**
     * Event audience: members only.
     */
    private const FLAG_MEMBERS_ONLY = 'sccc-event-members-only';

    /**
     * Keep false for accurate frontend testing.
     */
    private const FRONTEND_ADMIN_BYPASS = false;

    /**
     * Set true temporarily if you want debug.log entries.
     */
    private const DEBUG = false;

    /**
     * Register hooks.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        /*
         * Functional event flags.
         */
        add_action('init', [self::class, 'registerEventFlagsTaxonomy'], 5);
        add_action('init', [self::class, 'ensureDefaultEventFlags'], 20);

        /*
         * Custom checkbox save handling.
         */
        add_action('save_post_' . self::EVENT_POST_TYPE, [self::class, 'saveEventFlags'], 20, 2);

        /*
         * Frontend visibility controls.
         */
        add_action('template_redirect', [self::class, 'protectSingleEventPage'], 20);
        add_action('pre_get_posts', [self::class, 'filterEventQueries'], 20);
        add_filter('posts_clauses', [self::class, 'filterEventSqlClauses'], 20, 2);
        add_filter('the_posts', [self::class, 'filterReturnedPosts'], 20, 2);
        add_filter('rest_post_dispatch', [self::class, 'filterRestEventsResponse'], 20, 3);

        /*
         * Body classes and info-only registration hiding.
         *
         * No frontend notices are printed for Event Flags.
         */
        add_filter('body_class', [self::class, 'addEventBodyClasses'], 20);
        add_action('wp_head', [self::class, 'printInfoOnlyEventCss'], 30);

        /*
         * WooCommerce checkout safety net.
         *
         * RSVP events are allowed.
         * Ticketed events are allowed.
         * Info-only events are blocked if the event can be detected.
         */
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validateAddToCart'], 20, 6);
        add_action('woocommerce_check_cart_items', [self::class, 'validateCartItems'], 20);
    }

    /**
     * Register the functional Event Flags taxonomy.
     */
    public static function registerEventFlagsTaxonomy(): void
    {
        register_taxonomy(self::EVENT_FLAG_TAXONOMY, [self::EVENT_POST_TYPE], [
            'labels' => [
                'name'                       => __('Event Flags', 'sccc'),
                'singular_name'              => __('Event Flag', 'sccc'),
                'menu_name'                  => __('Event Flags', 'sccc'),
                'all_items'                  => __('All Event Flags', 'sccc'),
                'edit_item'                  => __('Edit Event Flag', 'sccc'),
                'view_item'                  => __('View Event Flag', 'sccc'),
                'update_item'                => __('Update Event Flag', 'sccc'),
                'add_new_item'               => __('Add New Event Flag', 'sccc'),
                'new_item_name'              => __('New Event Flag Name', 'sccc'),
                'search_items'               => __('Search Event Flags', 'sccc'),
                'not_found'                  => __('No event flags found.', 'sccc'),
            ],
            'public'            => false,
            'publicly_queryable' => false,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu'      => true,
            'show_in_rest'      => false,
            'query_var'         => false,
            'rewrite'           => false,
            'meta_box_cb'       => [self::class, 'renderEventFlagsChecklistMetabox'],
            'capabilities'      => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts',
            ],
        ]);
    }

    /**
     * Create the default functional event flags.
     */
    public static function ensureDefaultEventFlags(): void
    {
        if (! taxonomy_exists(self::EVENT_FLAG_TAXONOMY)) {
            return;
        }

        foreach (self::defaultEventFlags() as $slug => $term) {
            $existing = get_term_by('slug', $slug, self::EVENT_FLAG_TAXONOMY);

            if ($existing instanceof WP_Term) {
                continue;
            }

            wp_insert_term($term['name'], self::EVENT_FLAG_TAXONOMY, [
                'slug'        => $slug,
                'description' => $term['description'],
            ]);
        }
    }

    /**
     * Render fixed checkbox-style Event Flags metabox.
     *
     * @param WP_Post $post
     * @param array<string,mixed> $box
     */
    public static function renderEventFlagsChecklistMetabox(WP_Post $post, array $box = []): void
    {
        if (! taxonomy_exists(self::EVENT_FLAG_TAXONOMY)) {
            echo '<p>' . esc_html__('Event Flags are not available yet.', 'sccc') . '</p>';
            return;
        }

        $terms = get_terms([
            'taxonomy'   => self::EVENT_FLAG_TAXONOMY,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            echo '<p>' . esc_html__('No Event Flags have been created yet.', 'sccc') . '</p>';
            return;
        }

        $terms = self::sortEventFlags($terms);

        $assignedTerms = wp_get_object_terms($post->ID, self::EVENT_FLAG_TAXONOMY, [
            'fields' => 'ids',
        ]);

        if (is_wp_error($assignedTerms)) {
            $assignedTerms = [];
        }

        $assignedTerms = array_map('absint', (array) $assignedTerms);

        wp_nonce_field('sccc_save_event_flags', 'sccc_event_flags_nonce');

        echo '<div class="sccc-event-flags-metabox">';
        echo '<p class="description">';
        echo esc_html__('Select the functional behavior for this event. These flags are predefined and cannot be added from this screen.', 'sccc');
        echo '</p>';

        echo '<ul class="sccc-event-flags-checklist" style="margin: 0;">';

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            $fieldId = 'sccc-event-flag-' . absint($term->term_id);
            $checked = in_array((int) $term->term_id, $assignedTerms, true);
            $isModeFlag = self::isModeFlagSlug($term->slug);

            echo '<li style="margin: 0 0 10px;">';
            echo '<label for="' . esc_attr($fieldId) . '" style="display: block;">';
            echo '<input type="checkbox" class="sccc-event-flag-checkbox" ' . ($isModeFlag ? 'data-sccc-event-mode="1"' : '') . ' id="' . esc_attr($fieldId) . '" name="sccc_event_flags[]" value="' . esc_attr((string) $term->term_id) . '" ' . checked($checked, true, false) . '> ';
            echo '<strong>' . esc_html($term->name) . '</strong>';
            echo '</label>';

            if (! empty($term->description)) {
                echo '<p class="description" style="margin: 3px 0 0 24px;">' . esc_html($term->description) . '</p>';
            }

            echo '</li>';
        }

        echo '</ul>';

        echo '<div class="sccc-event-flags-rule" style="margin-top: 12px; padding: 10px 12px; border-left: 4px solid #dc2626; background: #fff1f2; color: #991b1b; font-weight: 700;">';
        echo '<strong>' . esc_html__('Important:', 'sccc') . '</strong> ';
        echo esc_html__('Choose exactly one event mode: Info Only, RSVP / Free Registration, or Ticketed Event. Members Only is optional and may be combined with any one of those three modes.', 'sccc');
        echo '</div>';

        ?>
        <script>
            (function () {
                const metabox = document.currentScript.closest('.sccc-event-flags-metabox');

                if (!metabox) {
                    return;
                }

                const modeCheckboxes = Array.from(
                    metabox.querySelectorAll('input[data-sccc-event-mode="1"]')
                );

                if (!modeCheckboxes.length) {
                    return;
                }

                const checkedModes = modeCheckboxes.filter((checkbox) => checkbox.checked);

                if (checkedModes.length > 1) {
                    checkedModes.slice(1).forEach((checkbox) => {
                        checkbox.checked = false;
                    });
                }

                modeCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', function () {
                        if (!this.checked) {
                            return;
                        }

                        modeCheckboxes.forEach((otherCheckbox) => {
                            if (otherCheckbox !== this) {
                                otherCheckbox.checked = false;
                            }
                        });
                    });
                });
            })();
        </script>
        <?php

        echo '</div>';
    }

    /**
     * Save selected Event Flags from the custom checkbox metabox.
     */
    public static function saveEventFlags(int $postId, WP_Post $post): void
    {
        if (! isset($_POST['sccc_event_flags_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['sccc_event_flags_nonce']));

        if (! wp_verify_nonce($nonce, 'sccc_save_event_flags')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if ($post->post_type !== self::EVENT_POST_TYPE) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        if (! taxonomy_exists(self::EVENT_FLAG_TAXONOMY)) {
            return;
        }

        $selectedTermIds = [];

        if (! empty($_POST['sccc_event_flags']) && is_array($_POST['sccc_event_flags'])) {
            $selectedTermIds = array_map('absint', wp_unslash($_POST['sccc_event_flags']));
            $selectedTermIds = array_values(array_filter(array_unique($selectedTermIds)));
        }

        $allowedTermIds = self::existingEventFlagTermIds();
        $selectedTermIds = array_values(array_intersect($selectedTermIds, $allowedTermIds));
        $selectedTermIds = self::normalizeSelectedEventFlagTermIds($selectedTermIds);

        wp_set_object_terms($postId, $selectedTermIds, self::EVENT_FLAG_TAXONOMY, false);
    }

    /**
     * Hide members-only events from frontend event queries for non-members.
     */
    public static function filterEventQueries(WP_Query $query): void
    {
        if (! self::shouldFilterEventQuery($query)) {
            return;
        }

        $taxQuery = $query->get('tax_query');

        $query->set(
            'tax_query',
            self::appendMembersOnlyExclusionTaxQuery(is_array($taxQuery) ? $taxQuery : [])
        );
    }

    /**
     * SQL-level safety net for members-only events.
     *
     * @param array<string,string> $clauses
     *
     * @return array<string,string>
     */
    public static function filterEventSqlClauses(array $clauses, WP_Query $query): array
    {
        if (! self::shouldFilterEventQuery($query)) {
            return $clauses;
        }

        $termTaxonomyId = self::termTaxonomyIdForFlag(self::FLAG_MEMBERS_ONLY);

        if (! $termTaxonomyId) {
            return $clauses;
        }

        global $wpdb;

        $termTaxonomyId = absint($termTaxonomyId);

        $clauses['where'] .= "
            AND NOT EXISTS (
                SELECT 1
                FROM {$wpdb->term_relationships} AS sccc_event_access_tr
                WHERE sccc_event_access_tr.term_taxonomy_id = {$termTaxonomyId}
                AND sccc_event_access_tr.object_id = {$wpdb->posts}.ID
            )
        ";

        return $clauses;
    }

    /**
     * Final post-array safety net.
     *
     * @param array<int,WP_Post|mixed> $posts
     *
     * @return array<int,WP_Post|mixed>
     */
    public static function filterReturnedPosts(array $posts, WP_Query $query): array
    {
        if (! self::shouldFilterReturnedPosts($query)) {
            return $posts;
        }

        $filtered = [];

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post) {
                $filtered[] = $post;
                continue;
            }

            if ($post->post_type !== self::EVENT_POST_TYPE) {
                $filtered[] = $post;
                continue;
            }

            if (self::isEventVisibleForCurrentUser((int) $post->ID)) {
                $filtered[] = $post;
            }
        }

        return array_values($filtered);
    }

    /**
     * Filter REST API event list responses.
     *
     * @param mixed $response
     *
     * @return mixed
     */
    public static function filterRestEventsResponse($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (self::currentUserIsScccMember() || self::isFrontendAdminBypassed()) {
            return $response;
        }

        if (! $response instanceof WP_REST_Response) {
            return $response;
        }

        if (! self::isEventsRestListRoute($request->get_route())) {
            return $response;
        }

        $data = $response->get_data();

        if (! is_array($data) || ! self::isListArray($data)) {
            return $response;
        }

        $filtered = array_filter($data, static function ($item): bool {
            if (! is_array($item) || empty($item['id'])) {
                return true;
            }

            return self::isEventVisibleForCurrentUser((int) $item['id']);
        });

        $response->set_data(array_values($filtered));

        return $response;
    }

    /**
     * Protect direct members-only event URLs.
     */
    public static function protectSingleEventPage(): void
    {
        if (self::isFrontendAdminBypassed()) {
            return;
        }

        if (! is_singular(self::EVENT_POST_TYPE)) {
            return;
        }

        $eventId = get_queried_object_id();

        if (! $eventId) {
            return;
        }

        if (self::isEventVisibleForCurrentUser((int) $eventId)) {
            return;
        }

        self::addNotice(
            __('This event is reserved for active Space City Car Club members.', 'sccc'),
            'notice'
        );

        wp_safe_redirect(self::getEventRedirectUrl());
        exit;
    }

    /**
     * Add body classes for behavior/styling hooks only.
     *
     * @param array<int,string> $classes
     *
     * @return array<int,string>
     */
    public static function addEventBodyClasses(array $classes): array
    {
        if (! is_singular(self::EVENT_POST_TYPE)) {
            return $classes;
        }

        $eventId = get_queried_object_id();

        if (! $eventId) {
            return $classes;
        }

        $classes[] = 'sccc-event';

        if (self::isInfoOnlyEvent((int) $eventId)) {
            $classes[] = 'sccc-event-info';
        }

        if (self::isRsvpEvent((int) $eventId)) {
            $classes[] = 'sccc-event-rsvp';
        }

        if (self::isTicketedEvent((int) $eventId)) {
            $classes[] = 'sccc-event-ticketed';
        }

        if (self::isMembersOnlyEvent((int) $eventId)) {
            $classes[] = 'sccc-event-members-only';
        }

        return array_values(array_unique($classes));
    }

    /**
     * Print CSS for info-only events.
     *
     * This hides registration/cart areas without printing any frontend flag notice.
     */
    public static function printInfoOnlyEventCss(): void
    {
        if (! is_singular(self::EVENT_POST_TYPE)) {
            return;
        }

        $eventId = get_queried_object_id();

        if (! $eventId || ! self::isInfoOnlyEvent((int) $eventId)) {
            return;
        }

        ?>
        <style id="sccc-event-info-only-css">
            body.single-mep_events.sccc-event-info .mep-default-feature-cart,
            body.single-mep_events.sccc-event-info .mep_event_add_cart,
            body.single-mep_events.sccc-event-info .mep-events-cart,
            body.single-mep_events.sccc-event-info .mep_event_cart,
            body.single-mep_events.sccc-event-info .mep-event-cart,
            body.single-mep_events.sccc-event-info .mep-cart,
            body.single-mep_events.sccc-event-info .mep_ticket_type,
            body.single-mep_events.sccc-event-info .mep-ticket-type,
            body.single-mep_events.sccc-event-info .mep_ticket,
            body.single-mep_events.sccc-event-info .mep-tickets,
            body.single-mep_events.sccc-event-info .mep_attendee_form,
            body.single-mep_events.sccc-event-info .mep-attendee-form,
            body.single-mep_events.sccc-event-info .mpwem_registration_area,
            body.single-mep_events.sccc-event-info .mpwem-registration-area,
            body.single-mep_events.sccc-event-info form.cart,
            body.single-mep_events.sccc-event-info form[action*="add-to-cart"],
            body.single-mep_events.sccc-event-info .woocommerce.add_to_cart_inline {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Block add-to-cart for info-only events only.
     *
     * RSVP and ticketed events are allowed through checkout.
     *
     * @param mixed $variations
     * @param mixed $cartItemData
     */
    public static function validateAddToCart(
        bool $passed,
        int $productId,
        int $quantity,
        int $variationId = 0,
        $variations = [],
        $cartItemData = []
    ): bool {
        if (! $passed) {
            return false;
        }

        $eventId = self::resolveEventIdFromRequestOrProduct($productId, $variationId);

        if (! $eventId) {
            return true;
        }

        if (! self::isEventVisibleForCurrentUser($eventId)) {
            self::addNotice(
                __('This event is reserved for active Space City Car Club members.', 'sccc'),
                'error'
            );

            return false;
        }

        if (self::isInfoOnlyEvent($eventId)) {
            self::addNotice(
                __('This event is listed for information only and does not require registration or checkout.', 'sccc'),
                'error'
            );

            return false;
        }

        return true;
    }

    /**
     * Remove info-only or unauthorized events from cart if detected.
     */
    public static function validateCartItems(): void
    {
        if (! function_exists('WC') || ! WC()->cart) {
            return;
        }

        foreach (WC()->cart->get_cart() as $cartItemKey => $cartItem) {
            $eventId = self::resolveEventIdFromCartItem($cartItem);

            if (! $eventId) {
                continue;
            }

            if (! self::isEventVisibleForCurrentUser($eventId)) {
                WC()->cart->remove_cart_item($cartItemKey);

                self::addNotice(
                    __('A members-only event was removed from your cart because it is reserved for active Space City Car Club members.', 'sccc'),
                    'notice'
                );

                continue;
            }

            if (self::isInfoOnlyEvent($eventId)) {
                WC()->cart->remove_cart_item($cartItemKey);

                self::addNotice(
                    __('An information-only event was removed from your cart because it does not require checkout.', 'sccc'),
                    'notice'
                );
            }
        }
    }

    /**
     * Determine if an event is visible for the current user.
     */
    private static function isEventVisibleForCurrentUser(int $eventId): bool
    {
        if (self::isFrontendAdminBypassed()) {
            return true;
        }

        if (! self::isMembersOnlyEvent($eventId)) {
            return true;
        }

        return self::currentUserIsScccMember();
    }

    /**
     * Event is information only.
     */
    private static function isInfoOnlyEvent(int $eventId): bool
    {
        return self::eventHasAnyFlag($eventId, [self::FLAG_INFO]);
    }

    /**
     * Event is RSVP / free registration.
     */
    private static function isRsvpEvent(int $eventId): bool
    {
        return self::eventHasAnyFlag($eventId, [self::FLAG_RSVP]);
    }

    /**
     * Event is ticketed.
     */
    private static function isTicketedEvent(int $eventId): bool
    {
        return self::eventHasAnyFlag($eventId, [self::FLAG_TICKETED]);
    }

    /**
     * Event is members only.
     */
    private static function isMembersOnlyEvent(int $eventId): bool
    {
        return self::eventHasAnyFlag($eventId, [self::FLAG_MEMBERS_ONLY]);
    }

    /**
     * Check event for one or more functional flags.
     *
     * @param array<int,string> $flagSlugs
     */
    private static function eventHasAnyFlag(int $eventId, array $flagSlugs): bool
    {
        if (! taxonomy_exists(self::EVENT_FLAG_TAXONOMY)) {
            return false;
        }

        return has_term($flagSlugs, self::EVENT_FLAG_TAXONOMY, $eventId);
    }

    /**
     * Add members-only exclusion to event queries.
     */
    private static function appendMembersOnlyExclusionTaxQuery(array $taxQuery): array
    {
        if (self::currentUserIsScccMember()) {
            return $taxQuery;
        }

        $accessTaxQuery = [
            'taxonomy' => self::EVENT_FLAG_TAXONOMY,
            'field'    => 'slug',
            'terms'    => [self::FLAG_MEMBERS_ONLY],
            'operator' => 'NOT IN',
        ];

        if (empty($taxQuery)) {
            return [$accessTaxQuery];
        }

        return [
            'relation' => 'AND',
            $taxQuery,
            $accessTaxQuery,
        ];
    }

    /**
     * Should this query be filtered for event visibility?
     */
    private static function shouldFilterEventQuery(WP_Query $query): bool
    {
        if (is_admin() || self::isFrontendAdminBypassed() || self::currentUserIsScccMember()) {
            return false;
        }

        if ($query->is_main_query() && $query->is_singular(self::EVENT_POST_TYPE)) {
            return false;
        }

        return self::isEventQuery($query);
    }

    /**
     * Should this returned-post array be filtered?
     */
    private static function shouldFilterReturnedPosts(WP_Query $query): bool
    {
        if (is_admin() || self::isFrontendAdminBypassed() || self::currentUserIsScccMember()) {
            return false;
        }

        if ($query->is_main_query() && $query->is_singular(self::EVENT_POST_TYPE)) {
            return false;
        }

        return self::isEventQuery($query);
    }

    /**
     * Detect event-related queries.
     */
    private static function isEventQuery(WP_Query $query): bool
    {
        $postType = $query->get('post_type');

        if ($postType === self::EVENT_POST_TYPE) {
            return true;
        }

        if (is_array($postType) && in_array(self::EVENT_POST_TYPE, $postType, true)) {
            return true;
        }

        if ($query->is_post_type_archive(self::EVENT_POST_TYPE)) {
            return true;
        }

        if ($query->is_tax(self::EVENT_FLAG_TAXONOMY)) {
            return true;
        }

        if ($query->is_tax('mep_cat')) {
            return true;
        }

        return false;
    }

    /**
     * Detect MagePeople events REST list route.
     */
    private static function isEventsRestListRoute(string $route): bool
    {
        return (bool) preg_match('#^/wp/v2/mep_events/?$#', $route);
    }

    /**
     * Return term_taxonomy_id for an event flag slug.
     */
    private static function termTaxonomyIdForFlag(string $flagSlug): int
    {
        if (! taxonomy_exists(self::EVENT_FLAG_TAXONOMY)) {
            return 0;
        }

        $term = get_term_by('slug', $flagSlug, self::EVENT_FLAG_TAXONOMY);

        if (! $term instanceof WP_Term || empty($term->term_taxonomy_id)) {
            return 0;
        }

        return (int) $term->term_taxonomy_id;
    }

    /**
     * Resolve event ID from common MagePeople/WooCommerce request fields or product meta.
     */
    private static function resolveEventIdFromRequestOrProduct(int $productId, int $variationId = 0): int
    {
        $requestKeys = [
            'mep_event_id',
            'event_id',
            'event',
            'mep_event',
            '_event_id',
        ];

        foreach ($requestKeys as $key) {
            if (! isset($_REQUEST[$key])) {
                continue;
            }

            $eventId = absint(wp_unslash($_REQUEST[$key]));

            if ($eventId > 0 && get_post_type($eventId) === self::EVENT_POST_TYPE) {
                return $eventId;
            }
        }

        $lookupProductId = $variationId > 0 ? $variationId : $productId;

        return self::resolveEventIdFromProductMeta($lookupProductId);
    }

    /**
     * Resolve event ID from a WooCommerce cart item.
     *
     * @param array<string,mixed> $cartItem
     */
    private static function resolveEventIdFromCartItem(array $cartItem): int
    {
        $cartKeys = [
            'mep_event_id',
            'event_id',
            'event',
            'mep_event',
            '_event_id',
        ];

        foreach ($cartKeys as $key) {
            if (empty($cartItem[$key])) {
                continue;
            }

            $eventId = absint($cartItem[$key]);

            if ($eventId > 0 && get_post_type($eventId) === self::EVENT_POST_TYPE) {
                return $eventId;
            }
        }

        $productId = 0;

        if (! empty($cartItem['variation_id'])) {
            $productId = (int) $cartItem['variation_id'];
        } elseif (! empty($cartItem['product_id'])) {
            $productId = (int) $cartItem['product_id'];
        }

        if (! $productId) {
            return 0;
        }

        return self::resolveEventIdFromProductMeta($productId);
    }

    /**
     * Resolve event ID from product/variation meta.
     */
    private static function resolveEventIdFromProductMeta(int $productId): int
    {
        if (! $productId) {
            return 0;
        }

        $metaKeys = [
            '_mep_event_id',
            'mep_event_id',
            '_event_id',
            'event_id',
            'mep_event',
            '_mep_event',
        ];

        foreach ($metaKeys as $metaKey) {
            $value = get_post_meta($productId, $metaKey, true);
            $eventId = absint($value);

            if ($eventId > 0 && get_post_type($eventId) === self::EVENT_POST_TYPE) {
                return $eventId;
            }
        }

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($productId);

            if ($product && method_exists($product, 'is_type') && $product->is_type('variation')) {
                $parentId = (int) $product->get_parent_id();

                if ($parentId > 0 && $parentId !== $productId) {
                    foreach ($metaKeys as $metaKey) {
                        $value = get_post_meta($parentId, $metaKey, true);
                        $eventId = absint($value);

                        if ($eventId > 0 && get_post_type($eventId) === self::EVENT_POST_TYPE) {
                            return $eventId;
                        }
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Default Event Flags.
     *
     * @return array<string,array{name:string,description:string}>
     */
    private static function defaultEventFlags(): array
    {
        return [
            self::FLAG_INFO => [
                'name'        => 'Info Only',
                'description' => 'Event is listed for information only. No registration or checkout.',
            ],
            self::FLAG_RSVP => [
                'name'        => 'RSVP / Free Registration',
                'description' => 'Free registration through checkout. Event ticket price should be $0.00.',
            ],
            self::FLAG_TICKETED => [
                'name'        => 'Ticketed Event',
                'description' => 'Paid registration through checkout.',
            ],
            self::FLAG_MEMBERS_ONLY => [
                'name'        => 'Members Only',
                'description' => 'Only users with the sccc_member role can view this event.',
            ],
        ];
    }

    /**
     * Event mode flag slugs.
     *
     * @return array<int,string>
     */
    private static function eventModeFlagSlugs(): array
    {
        return [
            self::FLAG_INFO,
            self::FLAG_RSVP,
            self::FLAG_TICKETED,
        ];
    }

    /**
     * Check if a slug is one of the mutually exclusive mode flags.
     */
    private static function isModeFlagSlug(string $slug): bool
    {
        return in_array($slug, self::eventModeFlagSlugs(), true);
    }

    /**
     * Normalize selected Event Flag IDs.
     *
     * This enforces one mode flag server-side even if someone bypasses the UI.
     *
     * @param array<int> $selectedTermIds
     *
     * @return array<int>
     */
    private static function normalizeSelectedEventFlagTermIds(array $selectedTermIds): array
    {
        if (empty($selectedTermIds)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => self::EVENT_FLAG_TAXONOMY,
            'hide_empty' => false,
            'include'    => $selectedTermIds,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return $selectedTermIds;
        }

        $modeSlugs = self::eventModeFlagSlugs();
        $selectedModeTermIds = [];
        $selectedNonModeTermIds = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            if (in_array($term->slug, $modeSlugs, true)) {
                $selectedModeTermIds[$term->slug] = (int) $term->term_id;
                continue;
            }

            $selectedNonModeTermIds[] = (int) $term->term_id;
        }

        $normalized = [];

        foreach ($modeSlugs as $modeSlug) {
            if (! empty($selectedModeTermIds[$modeSlug])) {
                $normalized[] = (int) $selectedModeTermIds[$modeSlug];
                break;
            }
        }

        foreach ($selectedNonModeTermIds as $termId) {
            $normalized[] = (int) $termId;
        }

        return array_values(array_unique(array_filter($normalized)));
    }

    /**
     * Sort Event Flags in a useful admin order.
     *
     * @param array<int,WP_Term> $terms
     *
     * @return array<int,WP_Term>
     */
    private static function sortEventFlags(array $terms): array
    {
        $order = array_keys(self::defaultEventFlags());

        usort($terms, static function ($a, $b) use ($order): int {
            if (! $a instanceof WP_Term || ! $b instanceof WP_Term) {
                return 0;
            }

            $aIndex = array_search($a->slug, $order, true);
            $bIndex = array_search($b->slug, $order, true);

            $aIndex = $aIndex === false ? 999 : $aIndex;
            $bIndex = $bIndex === false ? 999 : $bIndex;

            if ($aIndex === $bIndex) {
                return strcasecmp($a->name, $b->name);
            }

            return $aIndex <=> $bIndex;
        });

        return $terms;
    }

    /**
     * Existing Event Flag term IDs.
     *
     * @return array<int>
     */
    private static function existingEventFlagTermIds(): array
    {
        $terms = get_terms([
            'taxonomy'   => self::EVENT_FLAG_TAXONOMY,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', (array) $terms)));
    }

    /**
     * Check current user role.
     */
    private static function currentUserIsScccMember(): bool
    {
        $user = wp_get_current_user();

        if (! $user instanceof WP_User || ! $user->exists()) {
            return false;
        }

        return in_array(self::MEMBER_ROLE, (array) $user->roles, true);
    }

    /**
     * Optional frontend admin bypass.
     */
    private static function isFrontendAdminBypassed(): bool
    {
        if (! self::FRONTEND_ADMIN_BYPASS) {
            return false;
        }

        if (is_admin()) {
            return false;
        }

        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * Add WooCommerce notice if WooCommerce is available.
     */
    private static function addNotice(string $message, string $type = 'notice'): void
    {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type);
        }
    }

    /**
     * Redirect target for blocked members-only events.
     */
    private static function getEventRedirectUrl(): string
    {
        $archive = get_post_type_archive_link(self::EVENT_POST_TYPE);

        if (is_string($archive) && $archive !== '') {
            return $archive;
        }

        $eventsPage = get_page_by_path('events');

        if ($eventsPage instanceof WP_Post) {
            return get_permalink($eventsPage);
        }

        return home_url('/');
    }

    /**
     * PHP 8.0-safe list-array check.
     */
    private static function isListArray(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Debug helper.
     *
     * @param array<string,mixed> $context
     */
    private static function debug(string $message, array $context = []): void
    {
        if (! self::DEBUG) {
            return;
        }

        error_log('[SCCC EventProductAccess] ' . $message . ' ' . wp_json_encode($context));
    }
}

/*
|--------------------------------------------------------------------------
| Auto-register
|--------------------------------------------------------------------------
|
| The file only needs to be loaded once with require_once.
| No separate EventProductAccess::register() call is needed elsewhere.
|
*/

EventProductAccess::register();