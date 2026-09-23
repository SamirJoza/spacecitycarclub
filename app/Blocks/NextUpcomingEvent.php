<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/NextUpcomingEvent.php
 * ============================================================================
 *
 * Purpose:
 * - Register a dynamic ACF Composer block that shows exactly one upcoming
 *   Mage EventPress event in a compact sidebar-friendly card.
 *
 * Why this file exists:
 * - The stock Mage EventPress Gutenberg block is geared toward multi-column
 *   event lists.
 * - This custom block applies club-specific access rules at render time:
 *   - Visitors with the `sccc_member` role can see the next upcoming event
 *     that is actually available to them.
 *   - Visitors without the `sccc_member` role skip member-only events and
 *     see the next public event instead.
 *   - If no matching event exists, the block renders a graceful fallback.
 *
 * Important implementation notes:
 * - This block reads the real Mage EventPress post type and meta keys found
 *   in the plugin zip the user provided:
 *   - post type: `mep_events`
 *   - date meta: `event_upcoming_datetime`, `event_start_datetime`,
 *     `event_start_date`, `event_start_time`
 *   - access meta: `mep_member_only_event`, `mep_member_only_user_role`
 * - We do not override plugin templates or change plugin behavior globally.
 *   This block only reads plugin data and renders one card.
 * - Querying is intentionally done in two steps:
 *   1) gather a reasonable pool of future event IDs from the plugin’s date keys
 *   2) sort them in PHP and select the first event the current viewer may see
 *   This is safer than relying on one fragile meta query for multiple date keys.
 *
 * Assumptions:
 * - ACF Composer is already bootstrapped in the Sage 11 theme.
 * - The `sccc_member` role is the club membership role the user wants to use
 *   for this block’s visibility logic.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_Post;

class NextUpcomingEvent extends Block
{
    /**
     * The block name shown in the inserter.
     *
     * @var string
     */
    public $name = 'Next Upcoming Event';

    /**
     * The block description shown in the inserter.
     *
     * @var string
     */
    public $description = 'Displays the next upcoming Mage EventPress event the current visitor is allowed to see.';

    /**
     * Explicit slug so the view name and namespace stay predictable.
     *
     * @var string
     */
    public $slug = 'next-upcoming-event';

    /**
     * Place the block in a sensible Gutenberg category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Dashicon / block icon.
     *
     * @var string|array
     */
    public $icon = 'calendar-alt';

    /**
     * Help editors find the block quickly.
     *
     * @var array
     */
    public $keywords = ['events', 'event', 'meeting', 'sidebar', 'club'];

    /**
     * Use the Blade view explicitly to avoid any ambiguity.
     *
     * @var string
     */
    public $view = 'blocks.next-upcoming-event';

    /**
     * Default block mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Limit block supports to what is actually useful here.
     *
     * Why these settings:
     * - No alignment controls: this is intended to be a fluid sidebar card.
     * - Anchor support is useful for editors.
     * - Multiple instances are allowed, although most pages will likely use one.
     *
     * @var array
     */
    public $supports = [
        'align' => false,
        'align_text' => false,
        'align_content' => false,
        'anchor' => true,
        'mode' => false,
        'multiple' => true,
        'jsx' => false,
    ];

    /**
     * Data passed into the Blade view before rendering.
     *
     * @return array
     */
    public function with(): array
    {
        $event = $this->resolveAccessibleEvent();

        return [
            'card' => $event ? $this->mapEventForView($event) : null,
            'emptyMessage' => 'No upcoming events are scheduled.',
            'isPreview' => (bool) $this->preview,
        ];
    }

    /**
     * The block field group.
     *
     * Why this field group is intentionally minimal:
     * - The block is fully automated and does not need editor-driven content.
     * - A small message field helps future editors understand that the output
     *   changes automatically based on event data and visitor role.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('next_upcoming_event');

        $fields->addMessage('automation_notice', 'Automatic Event Card', [
            'label' => 'Automatic Event Card',
            'message' => 'This block automatically shows the next upcoming event the current visitor is allowed to view. Visitors without the sccc_member role will only see public events.',
            'new_lines' => 'wpautop',
            'esc_html' => 0,
        ]);

        return $fields->build();
    }

    /**
     * Resolve the single event this visitor should see.
     *
     * @return \WP_Post|null
     */
    protected function resolveAccessibleEvent(): ?WP_Post
    {
        $viewerRoles = $this->currentUserRoles();

        foreach ($this->candidateEvents() as $event) {
            if ($this->viewerCanSeeEvent((int) $event->ID, $viewerRoles)) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Build a pool of upcoming candidate events and sort them by the plugin's
     * next meaningful date.
     *
     * Why gather multiple date keys:
     * - Mage EventPress primarily uses `event_upcoming_datetime` for upcoming
     *   events, but `event_start_datetime` is a useful fallback if that value
     *   has not yet been computed or updated for a given event.
     *
     * @return array<int, \WP_Post>
     */
    protected function candidateEvents(): array
    {
        $candidateIds = array_unique(array_merge(
            $this->queryFutureEventIds('event_upcoming_datetime'),
            $this->queryFutureEventIds('event_start_datetime')
        ));

        $eventsWithTimestamps = [];

        foreach ($candidateIds as $eventId) {
            $post = get_post($eventId);

            if (! $post instanceof WP_Post || $post->post_type !== 'mep_events' || $post->post_status !== 'publish') {
                continue;
            }

            $date = $this->eventDateObject($eventId);

            if (! $date) {
                continue;
            }

            $eventsWithTimestamps[] = [
                'post' => $post,
                'timestamp' => $date->getTimestamp(),
            ];
        }

        usort($eventsWithTimestamps, static function (array $left, array $right): int {
            return $left['timestamp'] <=> $right['timestamp'];
        });

        return array_map(static fn (array $item): WP_Post => $item['post'], $eventsWithTimestamps);
    }

    /**
     * Query a reasonable set of future event IDs for one date meta key.
     *
     * Why the pool is capped:
     * - We only need one upcoming event for display.
     * - A modest limit keeps the query lightweight while still allowing the
     *   access filter to skip over several inaccessible events if needed.
     *
     * @param  string  $metaKey
     * @return array<int, int>
     */
    protected function queryFutureEventIds(string $metaKey): array
    {
        $now = current_time('Y-m-d H:i:s');

        return get_posts([
            'post_type' => 'mep_events',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'fields' => 'ids',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => $metaKey,
            'meta_type' => 'DATETIME',
            'meta_query' => [
                [
                    'key' => $metaKey,
                    'value' => $now,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
            ],
            'no_found_rows' => true,
            'suppress_filters' => false,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
    }

    /**
     * Determine whether the current viewer may see a given event.
     *
     * Access rules requested by the user:
     * - If the viewer does NOT have the `sccc_member` role, member-only events
     *   must be skipped.
     * - If the viewer DOES have the `sccc_member` role, the block should show
     *   the very next event they are allowed to see.
     *
     * Additional safety:
     * - If an event is member-only and also stores allowed roles in
     *   `mep_member_only_user_role`, we honor that list instead of assuming
     *   every member-only event is universally visible.
     *
     * @param  int    $eventId
     * @param  array  $viewerRoles
     * @return bool
     */
    protected function viewerCanSeeEvent(int $eventId, array $viewerRoles): bool
    {
        $memberOnlyFlag = (string) get_post_meta($eventId, 'mep_member_only_event', true);
        $isMemberOnly = $memberOnlyFlag === 'member_only';

        // Public events are always allowed.
        if (! $isMemberOnly) {
            return true;
        }

        // Non-members must skip member-only events entirely.
        if (! in_array('sccc_member', $viewerRoles, true)) {
            return false;
        }

        /**
         * The plugin stores allowed roles as an array.
         * If the field is empty, we allow the club member to see it because the
         * event is already explicitly marked member-only and the user requested
         * `sccc_member`-based behavior.
         */
        $allowedRoles = get_post_meta($eventId, 'mep_member_only_user_role', true);
        $allowedRoles = is_array($allowedRoles) ? array_values(array_filter($allowedRoles)) : [];

        if (empty($allowedRoles)) {
            return true;
        }

        // Plugin convention: `all` means any logged-in user.
        if (in_array('all', $allowedRoles, true)) {
            return true;
        }

        return (bool) array_intersect($viewerRoles, $allowedRoles);
    }

    /**
     * Convert the selected event into a Blade-friendly view model.
     *
     * @param  \WP_Post  $event
     * @return array<string, mixed>
     */
    protected function mapEventForView(WP_Post $event): array
    {
        $eventId = (int) $event->ID;
        $date = $this->eventDateObject($eventId);

        $rawDateValue = (string) (
            get_post_meta($eventId, 'event_upcoming_datetime', true)
            ?: get_post_meta($eventId, 'event_start_datetime', true)
            ?: trim(
                (string) get_post_meta($eventId, 'event_start_date', true) . ' ' .
                (string) get_post_meta($eventId, 'event_start_time', true)
            )
        );

        $hasExplicitTime = str_contains($rawDateValue, ':');
        $isMemberOnly = ((string) get_post_meta($eventId, 'mep_member_only_event', true) === 'member_only');
        $timestamp = $date ? $date->getTimestamp() : current_time('timestamp');

        return [
            'id' => $eventId,
            'title' => get_the_title($eventId),
            'url' => get_permalink($eventId),
            'imageUrl' => get_the_post_thumbnail_url($eventId, 'large') ?: '',
            'monthText' => wp_date('M', $timestamp, wp_timezone()),
            'dayText' => wp_date('d', $timestamp, wp_timezone()),
            'fullDateText' => wp_date(get_option('date_format'), $timestamp, wp_timezone()),
            'timeText' => $hasExplicitTime ? wp_date(get_option('time_format'), $timestamp, wp_timezone()) : '',
            'locationText' => $this->eventLocation($eventId),
            'statusText' => $isMemberOnly ? 'Members Only' : 'Open Event',
            'buttonText' => 'Event Details',
        ];
    }

    /**
     * Resolve the event location from the plugin's venue-related meta.
     *
     * Why this helper exists:
     * - The sidebar card only needs a compact human-readable location line.
     * - We intentionally avoid full template coupling and simply compose the
     *   most useful pieces of venue data directly.
     *
     * @param  int  $eventId
     * @return string
     */
    protected function eventLocation(int $eventId): string
    {
        $parts = array_filter([
            trim((string) get_post_meta($eventId, 'mep_location_venue', true)),
            trim((string) get_post_meta($eventId, 'mep_city', true)),
            trim((string) get_post_meta($eventId, 'mep_state', true)),
        ]);

        $parts = array_values(array_unique($parts));

        return implode(', ', $parts);
    }

    /**
     * Resolve the most meaningful upcoming date object for an event.
     *
     * Priority:
     * 1) event_upcoming_datetime
     * 2) event_start_datetime
     * 3) event_start_date + event_start_time
     *
     * @param  int  $eventId
     * @return \DateTimeImmutable|null
     */
    protected function eventDateObject(int $eventId): ?\DateTimeImmutable
    {
        $rawUpcoming = (string) get_post_meta($eventId, 'event_upcoming_datetime', true);
        $rawStartDateTime = (string) get_post_meta($eventId, 'event_start_datetime', true);
        $rawStartDate = (string) get_post_meta($eventId, 'event_start_date', true);
        $rawStartTime = (string) get_post_meta($eventId, 'event_start_time', true);

        $combinedStart = trim($rawStartDate . ' ' . $rawStartTime);

        return $this->parseEventDateValue($rawUpcoming)
            ?: $this->parseEventDateValue($rawStartDateTime)
            ?: $this->parseEventDateValue($combinedStart)
            ?: $this->parseEventDateValue($rawStartDate);
    }

    /**
     * Parse an EventPress date string using the site's timezone.
     *
     * Why this helper exists:
     * - EventPress may store values with seconds, without seconds, or date-only.
     * - Parsing with the site timezone is safer than relying on server defaults.
     *
     * @param  string|null  $rawValue
     * @return \DateTimeImmutable|null
     */
    protected function parseEventDateValue(?string $rawValue): ?\DateTimeImmutable
    {
        $rawValue = trim((string) $rawValue);

        if ($rawValue === '') {
            return null;
        }

        $timezone = wp_timezone();

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $rawValue, $timezone);

            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        $timestamp = strtotime($rawValue);

        if ($timestamp === false) {
            return null;
        }

        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    }

    /**
     * Return the current user's role slugs.
     *
     * @return array<int, string>
     */
    protected function currentUserRoles(): array
    {
        $user = wp_get_current_user();

        if (! $user || ! isset($user->roles) || ! is_array($user->roles)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $user->roles)));
    }
}