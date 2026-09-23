<?php

namespace App\View\Composers;

use Roots\Acorn\View\Composer;
use WP_Post;
use WP_Query;

class ArchiveMinutes extends Composer
{
    protected static $views = [
        'archive-minutes',
    ];

    public function with(): array
    {
        return [
            'minutesArchive' => $this->buildArchiveData(),
        ];
    }

    protected function buildArchiveData(): array
    {
        $post_status = current_user_can('edit_posts')
            ? ['publish', 'draft']
            : ['publish'];

        // Native order by publish date
        $q = new WP_Query([
            'post_type'      => 'minutes',
            'post_status'    => $post_status,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        $groups = []; // month_key => ['month' => 'F Y', 'month_key' => 'Y-m', 'items' => []]

        foreach (($q->posts ?? []) as $post) {
            /** @var WP_Post $post */
            $item = $this->mapMinutesItem($post);

            // Get meeting date for grouping
            $meeting_details = function_exists('get_field') ? (get_field('meeting_details', $post->ID) ?: []) : [];
            $meeting_date_raw = isset($meeting_details['meeting_date']) ? (string) $meeting_details['meeting_date'] : '';
            
            // Fallback to post date if no meeting date
            if (empty($meeting_date_raw)) {
                $meeting_date_raw = get_the_date('Y-m-d', $post->ID);
            }

            if (empty($meeting_date_raw)) {
                continue;
            }

            $meeting_date = $this->parseMeetingDate($meeting_date_raw);
            if (!$meeting_date) {
                continue;
            }

            // Group by month (Y-m format for sorting)
            $month_key = $meeting_date->format('Y-m');
            $month_label = wp_date('F Y', $meeting_date->getTimestamp(), wp_timezone());

            if (!isset($groups[$month_key])) {
                $groups[$month_key] = [
                    'month'     => $month_label,
                    'month_key' => $month_key,
                    'items'     => [],
                ];
            }

            $groups[$month_key]['items'][] = $item;
        }

        // Sort items within each month by date (newest first)
        foreach ($groups as $month_key => &$group) {
            usort($group['items'], function($a, $b) {
                $date_a = isset($a['meeting_date_raw'])
                    ? $this->parseMeetingDate((string) $a['meeting_date_raw'])
                    : null;
                $date_b = isset($b['meeting_date_raw'])
                    ? $this->parseMeetingDate((string) $b['meeting_date_raw'])
                    : null;

                $timestamp_a = $date_a ? $date_a->getTimestamp() : 0;
                $timestamp_b = $date_b ? $date_b->getTimestamp() : 0;

                return $timestamp_b <=> $timestamp_a; // Descending order
            });
        }
        unset($group);

        // Sort groups by month (newest first)
        krsort($groups);

        // Handle jump-to month selection
        $jump_to_month = isset($_GET['jump_to']) ? sanitize_text_field($_GET['jump_to']) : '';
        if (!empty($jump_to_month) && isset($groups[$jump_to_month])) {
            // Move selected month to first position
            $selected_group = $groups[$jump_to_month];
            unset($groups[$jump_to_month]);
            $groups = [$jump_to_month => $selected_group] + $groups;
        }

        // Limit to first 3 months for initial display and mark current month
        $current_month = current_datetime()->format('Y-m');
        $limited_groups = [];
        $count = 0;
        
        foreach ($groups as $month_key => $group) {
            $group['is_current'] = ($month_key === $current_month);
            $limited_groups[$month_key] = $group;
            $count++;
            if ($count >= 3) {
                break;
            }
        }

        // Calculate pagination
        $total_months = count($groups);
        $months_per_page = 3;
        $total_pages = (int) ceil($total_months / $months_per_page);
        $current_page = isset($_GET['page']) ? max(1, min((int) $_GET['page'], $total_pages)) : 1;
        
        // If jump_to is set, always show page 1 (with selected month first)
        if (!empty($jump_to_month)) {
            $current_page = 1;
        }
        
        // Get months for current page
        $offset = ($current_page - 1) * $months_per_page;
        $paged_groups = array_slice(array_values($groups), $offset, $months_per_page, true);
        
        // Re-mark current month for paged groups
        foreach ($paged_groups as $month_key => &$group) {
            $group['is_current'] = ($month_key === $current_month);
        }
        unset($group);

        // Calculate page numbers to display
        $page_numbers = $this->calculatePageNumbers($current_page, $total_pages, $jump_to_month);

        return [
            'groups'        => array_values($paged_groups),
            'all_groups'    => array_values($groups), // For jump-to dropdown
            'selected_month' => $jump_to_month, // For jump-to dropdown selected value
            'show_drafts'   => current_user_can('edit_posts'),
            'archive_title' => 'Meeting Minutes Archive',
            'archive_intro' => 'Access official documentation from our club gatherings. Browse the last three months of activity or explore the full history.',
            'pagination'    => [
                'current_page'  => $current_page,
                'total_pages'   => $total_pages,
                'has_prev'      => $current_page > 1,
                'has_next'      => $current_page < $total_pages,
                'prev_page'     => $current_page > 1 ? $this->buildPaginationUrl($current_page - 1, $jump_to_month) : null,
                'next_page'     => $current_page < $total_pages ? $this->buildPaginationUrl($current_page + 1, $jump_to_month) : null,
                'page_numbers'  => $page_numbers,
                'jump_to_param'  => $jump_to_month, // For preserving in other links
            ],
        ];
    }

    protected function mapMinutesItem(WP_Post $post): array
    {
        // Meeting type taxonomy (single assigned via ACF sync)
        $type_terms = get_the_terms($post->ID, 'meeting_type');
        $type_term = (is_array($type_terms) && !empty($type_terms)) ? $type_terms[0] : null;

        $meeting_type = $type_term ? [
            'term_id' => (int) $type_term->term_id,
            'name'    => (string) $type_term->name,
            'slug'    => (string) $type_term->slug,
        ] : null;

        // Meeting date (optional display) - fields are inside meeting_details group
        $meeting_details = function_exists('get_field') ? (get_field('meeting_details', $post->ID) ?: []) : [];
        $meeting_date_raw = isset($meeting_details['meeting_date']) ? (string) $meeting_details['meeting_date'] : '';
        
        // Fallback to post date if no meeting date
        if (empty($meeting_date_raw)) {
            $meeting_date_raw = get_the_date('Y-m-d', $post->ID);
        }
        
        $meeting_date = $this->parseMeetingDate($meeting_date_raw);
        $meeting_date_label = $meeting_date
            ? wp_date('F j, Y', $meeting_date->getTimestamp(), wp_timezone())
            : '';

        $location = isset($meeting_details['meeting_location']) ? (string) $meeting_details['meeting_location'] : '';

        $excerpt = has_excerpt($post)
            ? get_the_excerpt($post)
            : (string) get_post_meta($post->ID, 'recap_summary', true);
        
        // If no excerpt, use a default
        if (empty($excerpt)) {
            $excerpt = 'Official meeting minutes documentation.';
        }

        // Attendance label derived from taxonomy slug
        $attendance_label = $this->attendanceLabel($post->ID, $meeting_type['slug'] ?? '');
        $attendance_type = $meeting_type['slug'] ?? '';

        $status = (string) $post->post_status;

        $type_slug = $meeting_type['slug'] ?? '';

        return [
            'id'                => $post->ID,
            'title'             => get_the_title($post),
            'permalink'         => get_permalink($post),
            'meeting_type'      => $meeting_type,
            'meeting_type_icon' => $this->getMeetingTypeIcon($type_slug),
            'meeting_date_raw'  => $meeting_date_raw,
            'meeting_date_label'=> $meeting_date_label,
            'location'          => $location,
            'excerpt'           => $excerpt,
            'attendance_label'  => $attendance_label,
            'attendance_type'   => $attendance_type,
            'post_status'       => $status,
            'post_status_label' => $status === 'publish' ? 'Published' : ucfirst($status),
        ];
    }

    /**
     * Parse a date-only value in the WordPress site timezone.
     *
     * ACF returns the meeting date as Y-m-d with no time component. Parsing it
     * explicitly in the site timezone prevents an unintended UTC conversion
     * from shifting the displayed calendar date backward by one day.
     */
    protected function parseMeetingDate(string $date): ?\DateTimeImmutable
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        $parsed_date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date,
            wp_timezone()
        );

        $errors = \DateTimeImmutable::getLastErrors();

        if (
            !$parsed_date
            || (
                is_array($errors)
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
            )
        ) {
            return null;
        }

        return $parsed_date;
    }

    protected function attendanceLabel(int $post_id, string $type_slug): string
    {
        if ($type_slug === 'membership') {
            $count = get_post_meta($post_id, 'attendance_count', true);
            $count = is_numeric($count) ? (int) $count : 0;
            return $count > 0 ? ($count . ' attendees') : '';
        }

        if (in_array($type_slug, ['board', 'committee'], true)) {
            // For board/committee, return names and positions instead of count
            $rows = function_exists('get_field') ? (get_field('attendees', $post_id) ?: []) : [];
            if (!is_array($rows) || empty($rows)) {
                return '';
            }

            $names = [];
            foreach ($rows as $row) {
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if (!$name) continue;

                $role = isset($row['role']) ? trim((string) $row['role']) : '';
                if ($role) {
                    $names[] = $name . ' (' . $role . ')';
                } else {
                    $names[] = $name;
                }
            }

            return !empty($names) ? implode(', ', $names) : '';
        }

        return '';
    }

    /**
     * Get Material Symbol icon name for meeting type
     */
    protected function getMeetingTypeIcon(string $type_slug): string
    {
        return match ($type_slug) {
            'board'      => 'description',
            'committee'  => 'event',
            'membership' => 'groups',
            default      => 'description',
        };
    }

    /**
     * Check if a month is the current month
     */
    protected function isCurrentMonth(string $month_key): bool
    {
        return $month_key === current_datetime()->format('Y-m');
    }

    /**
     * Calculate which page numbers to display in pagination
     * Returns array of page numbers with 'number', 'is_current', and 'url' keys
     */
    protected function calculatePageNumbers(int $current_page, int $total_pages, string $jump_to_month = ''): array
    {
        if ($total_pages <= 0) {
            return [];
        }

        $start = max(1, $current_page - 1);
        $end = min($total_pages, $current_page + 1);
        
        // Show at least 3 pages if possible
        if ($end - $start < 2) {
            if ($start === 1) {
                $end = min($total_pages, 3);
            } else {
                $start = max(1, $total_pages - 2);
            }
        }

        $page_numbers = [];
        for ($i = $start; $i <= $end; $i++) {
            $page_numbers[] = [
                'number'     => $i,
                'is_current' => $i === $current_page,
                'url'        => $this->buildPaginationUrl($i, $jump_to_month),
            ];
        }

        return $page_numbers;
    }

    /**
     * Build pagination URL with optional jump_to parameter
     */
    protected function buildPaginationUrl(int $page, string $jump_to_month = ''): string
    {
        $params = ['page' => $page];
        if (!empty($jump_to_month)) {
            $params['jump_to'] = $jump_to_month;
        }
        return '?' . http_build_query($params);
    }
}