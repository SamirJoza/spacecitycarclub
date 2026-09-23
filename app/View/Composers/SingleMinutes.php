<?php

namespace App\View\Composers;

use Roots\Acorn\View\Composer;
use WP_Post;

/**
 * SingleMinutes (Sage Composer)
 *
 * SOURCE OF TRUTH
 * -----------------------------------------------------------------------------
 * Meeting type comes from the TAXONOMY: meeting_type
 * - The ACF radio "meeting_type_select" is UI/display only and is not relied on.
 *
 * UX RULES
 * -----------------------------------------------------------------------------
 * - Prev/Next navigation stays WITHIN the same meeting_type term
 * - Navigation is ordered by ACF meeting date (stored in DB as Ymd)
 *
 * WHAT THIS COMPOSER PROVIDES TO THE TEMPLATE
 * -----------------------------------------------------------------------------
 * Adds previously missing ACF fields to the view model:
 * - recap_summary
 * - motions
 * - action_items
 * - attachments
 */
class SingleMinutes extends Composer
{
    protected static $views = [
        'partials.content-single-minutes',
        'single-minutes',
    ];

    public function with(): array
    {
        return [
            'minutesSingle' => $this->buildSingleData(),
        ];
    }

    protected function buildSingleData(): array
    {
        $post = get_post();

        if (!($post instanceof WP_Post)) {
            return [
                'not_found'   => true,
                'archive_url' => '',
                'title'       => '',
            ];
        }

        $archive_url = get_post_type_archive_link('minutes') ?: '';

        if ($post->post_status !== 'publish' && !current_user_can('edit_post', $post->ID)) {
            return [
                'not_found'   => true,
                'archive_url' => $archive_url,
                'title'       => '',
            ];
        }

        /**
         * Meeting Type (taxonomy: meeting_type)
         * -----------------------------------------------------------------------------
         * We take the first term as the primary type for:
         * - UI labels
         * - Prev/Next filtering
         */
        $type_terms = get_the_terms($post->ID, 'meeting_type');
        $type_term  = (is_array($type_terms) && !empty($type_terms)) ? $type_terms[0] : null;

        $meeting_type = $type_term ? [
            'name'    => (string) $type_term->name,
            'slug'    => (string) $type_term->slug,
            'term_id' => (int) $type_term->term_id,
        ] : null;

        /**
         * Meeting Details (ACF group)
         * -----------------------------------------------------------------------------
         * meeting_details:
         * - meeting_date (DatePicker)
         * - meeting_location (text)
         *
         * Note: DB meta for date is typically Ymd under key:
         *   meeting_details_meeting_date
         */
        $meeting_details  = function_exists('get_field') ? (get_field('meeting_details', $post->ID) ?: []) : [];
        $meeting_date_raw = isset($meeting_details['meeting_date']) ? (string) $meeting_details['meeting_date'] : '';

        $meeting_date = $this->parseDateOnly($meeting_date_raw);
        $meeting_date_label = $meeting_date
            ? wp_date('F j, Y', $meeting_date->getTimestamp(), wp_timezone())
            : '';

        $location = isset($meeting_details['meeting_location'])
            ? (string) $meeting_details['meeting_location']
            : '';

        /**
         * Recap Summary (ACF textarea) — previously missing in UI
         */
        $recap_summary = function_exists('get_field')
            ? (string) (get_field('recap_summary', $post->ID) ?: '')
            : (string) get_post_meta($post->ID, 'recap_summary', true);

        /**
         * Attendance
         */
        $attendance = $this->attendanceData($post->ID, $meeting_type['slug'] ?? '');

        /**
         * Structured ACF fields — previously missing in UI
         */
        $motions      = function_exists('get_field') ? (get_field('motions', $post->ID) ?: []) : [];
        $action_items = function_exists('get_field') ? (get_field('action_items', $post->ID) ?: []) : [];
        $attachments  = function_exists('get_field') ? (get_field('attachments', $post->ID) ?: []) : [];

        $official_pdf = function_exists('get_field') ? get_field('official_pdf', $post->ID) : null;
        $official_pdf_url = is_array($official_pdf) && !empty($official_pdf['url'])
            ? (string) $official_pdf['url']
            : '';

        /**
         * Prev/Next (same meeting_type term, ordered by meeting date)
         */
        $navigation = $this->getNavigation(
            $post->ID,
            $meeting_date_raw,
            $meeting_type['term_id'] ?? 0
        );

        return [
            'not_found'        => false,
            'archive_url'      => $archive_url,

            'id'               => $post->ID,
            'title'            => get_the_title($post),

            'meeting_type'     => $meeting_type,
            'meeting_date'     => $meeting_date_label,
            'meeting_date_raw' => $meeting_date_raw,
            'location'         => $location,

            'recap_summary'    => $recap_summary,

            'attendance'       => $attendance,

            'official_pdf_url' => $official_pdf_url,
            'attachments'      => $this->mapAttachments($attachments),

            'motions'          => $this->mapMotions($motions),
            'action_items'     => $this->mapActionItems($action_items),

            'content'          => apply_filters('the_content', $post->post_content),

            'navigation'       => $navigation,
        ];
    }

    protected function attendanceData(int $post_id, string $type_slug): array
    {
        if ($type_slug === 'membership') {
            $count = get_post_meta($post_id, 'attendance_count', true);
            $count = is_numeric($count) ? (int) $count : 0;

            return [
                'type'  => 'membership',
                'label' => $count > 0 ? ($count . ' attendees') : '',
                'count' => $count,
                'names' => [],
            ];
        }

        if (in_array($type_slug, ['board', 'committee'], true)) {
            $rows  = function_exists('get_field') ? (get_field('attendees', $post_id) ?: []) : [];
            $names = [];

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $name = isset($row['name']) ? (string) $row['name'] : '';
                    if (!$name) continue;

                    $names[] = [
                        'name'         => $name,
                        'role'         => isset($row['role']) ? (string) $row['role'] : '',
                        'initials'     => $this->getInitials($name),
                        'avatar_color' => $this->getAvatarColor($name),
                    ];
                }
            }

            return [
                'type'  => $type_slug,
                'label' => '',
                'count' => count($names),
                'names' => $names,
            ];
        }

        return [
            'type'  => 'unknown',
            'label' => '',
            'count' => 0,
            'names' => [],
        ];
    }

    /**
     * Prev/Next navigation by meeting date, filtered to the same meeting_type term.
     */
    protected function getNavigation(int $post_id, string $meeting_date_raw = '', int $meeting_type_term_id = 0): array
    {
        $meta_key = 'meeting_details_meeting_date';

        // Normalize current date to Ymd integer (ACF stores DatePicker in DB as Ymd).
        $current_ymd = 0;
        $meeting_date = $this->parseDateOnly($meeting_date_raw);

        if ($meeting_date) {
            $current_ymd = (int) $meeting_date->format('Ymd');
        } else {
            $db_value = (string) get_post_meta($post_id, $meta_key, true);
            if ($db_value && is_numeric($db_value)) {
                $current_ymd = (int) $db_value;
            }
        }

        $tax_query = [];
        if ($meeting_type_term_id > 0) {
            $tax_query = [[
                'taxonomy' => 'meeting_type',
                'field'    => 'term_id',
                'terms'    => [$meeting_type_term_id],
            ]];
        }

        $base = [
            'post_type'           => 'minutes',
            'post_status'         => 'publish',
            'posts_per_page'      => 1,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'fields'              => 'ids',
        ];

        if (!empty($tax_query)) {
            $base['tax_query'] = $tax_query;
        }

        if ($current_ymd > 0) {
            $prev_q = new \WP_Query($base + [
                'meta_key'   => $meta_key,
                'orderby'    => 'meta_value_num',
                'order'      => 'DESC',
                'meta_query' => [[
                    'key'     => $meta_key,
                    'value'   => $current_ymd,
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                ]],
            ]);

            $next_q = new \WP_Query($base + [
                'meta_key'   => $meta_key,
                'orderby'    => 'meta_value_num',
                'order'      => 'ASC',
                'meta_query' => [[
                    'key'     => $meta_key,
                    'value'   => $current_ymd,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ]],
            ]);
        } else {
            $post_date = (string) get_post_field('post_date', $post_id);

            $prev_q = new \WP_Query($base + [
                'orderby'    => 'date',
                'order'      => 'DESC',
                'date_query' => [[ 'before' => $post_date ]],
            ]);

            $next_q = new \WP_Query($base + [
                'orderby'    => 'date',
                'order'      => 'ASC',
                'date_query' => [[ 'after' => $post_date ]],
            ]);
        }

        $prev_id = !empty($prev_q->posts[0]) ? (int) $prev_q->posts[0] : 0;
        $next_id = !empty($next_q->posts[0]) ? (int) $next_q->posts[0] : 0;

        return [
            'prev' => $prev_id ? ['url' => get_permalink($prev_id), 'title' => get_the_title($prev_id)] : null,
            'next' => $next_id ? ['url' => get_permalink($next_id), 'title' => get_the_title($next_id)] : null,
        ];
    }

    protected function mapAttachments($rows): array
    {
        if (!is_array($rows) || empty($rows)) return [];

        $out = [];
        foreach ($rows as $row) {
            $file = $row['file'] ?? null;
            $url  = is_array($file) && !empty($file['url']) ? (string) $file['url'] : '';
            if (!$url) continue;

            $out[] = [
                'label' => !empty($row['label']) ? (string) $row['label'] : 'Attachment',
                'url'   => $url,
            ];
        }

        return $out;
    }

    protected function mapMotions($rows): array
    {
        if (!is_array($rows) || empty($rows)) return [];

        $out = [];
        foreach ($rows as $row) {
            $outcome = (string) ($row['outcome'] ?? 'info');

            $out[] = [
                'text'          => (string) ($row['motion_text'] ?? ''),
                'outcome'       => $outcome,
                'outcome_label' => $this->labelOutcome($outcome),
                'notes'         => (string) ($row['notes'] ?? ''),
            ];
        }

        return $out;
    }

    protected function mapActionItems($rows): array
    {
        if (!is_array($rows) || empty($rows)) return [];

        $out = [];
        foreach ($rows as $row) {
            $due = (string) ($row['due_date'] ?? '');
            $due_date = $this->parseDateOnly($due);
            $due_label = $due_date
                ? wp_date('M j, Y', $due_date->getTimestamp(), wp_timezone())
                : '';

            $status = (string) ($row['status'] ?? 'open');

            $out[] = [
                'task'         => (string) ($row['task'] ?? ''),
                'owner'        => (string) ($row['owner'] ?? ''),
                'due'          => $due_label,
                'status'       => $status,
                'status_label' => $this->labelActionStatus($status),
            ];
        }

        return $out;
    }

    /**
     * Parse an ACF date-only value in the WordPress site timezone.
     *
     * ACF returns these fields as Y-m-d. Parsing the value explicitly in the
     * site timezone prevents a date-only value from shifting backward when it
     * is formatted for display.
     */
    protected function parseDateOnly(string $date): ?\DateTimeImmutable
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

    protected function labelOutcome(string $o): string
    {
        return match ($o) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'tabled'   => 'Tabled',
            default    => 'Info / Discussed',
        };
    }

    protected function labelActionStatus(string $s): string
    {
        return match ($s) {
            'done'        => 'Done',
            'in_progress' => 'In Progress',
            default       => 'Open',
        };
    }

    protected function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    protected function getAvatarColor(string $name): string
    {
        $colors = ['bg-primary-500', 'bg-indigo-500', 'bg-emerald-500', 'bg-slate-400'];
        $index  = crc32($name) % count($colors);
        return $colors[$index];
    }
}