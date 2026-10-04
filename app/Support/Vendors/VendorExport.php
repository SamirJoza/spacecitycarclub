<?php

/**
 * File path + filename: app/Support/Vendors/VendorExport.php
 *
 * Purpose:
 * - Per-show exports from the Vendors list (filter by a show first):
 *     • CSV  — every vendor on that show with status, fee, contact and booth
 *              details (show-day check-in, program, planning)
 *     • ZIP  — logos of active vendors who allow featuring (ad design)
 *
 * Why this file exists:
 * - Staff need vendor lists and logos outside WordPress to design ads and run
 *   the show. Both exports respect the same data as the Vendors screen.
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorExport
{
    public const CSV_ACTION = 'sccc_vendor_export_csv';

    public const ZIP_ACTION = 'sccc_vendor_export_logos';

    public static function register(): void
    {
        add_action('manage_posts_extra_tablenav', [self::class, 'renderButtons']);
        add_action('admin_post_'.self::CSV_ACTION, [self::class, 'exportCsv']);
        add_action('admin_post_'.self::ZIP_ACTION, [self::class, 'exportLogos']);
    }

    public static function renderButtons(string $which): void
    {
        global $typenow;

        $showId = isset($_GET['vendor_show_filter']) ? absint($_GET['vendor_show_filter']) : 0; // phpcs:ignore WordPress.Security.NonceVerification

        if ($which !== 'top' || $typenow !== Vendors::POST_TYPE) {
            return;
        }

        if (! $showId) {
            echo '<div class="alignleft actions"><span class="description">'.esc_html__('Filter by a show to export its vendor list or logos.', 'sccc').'</span></div>';

            return;
        }

        foreach ([self::CSV_ACTION => __('Export CSV', 'sccc'), self::ZIP_ACTION => __('Download logos (ZIP)', 'sccc')] as $action => $label) {
            $url = wp_nonce_url(admin_url('admin-post.php?action='.$action.'&show='.$showId), $action.'_'.$showId);
            echo '<div class="alignleft actions"><a class="button" href="'.esc_url($url).'">'.esc_html($label).'</a></div>';
        }
    }

    /**
     * Vendors that have a history row for the show.
     *
     * @return array<int, array{id: int, row: array<string, mixed>}>
     */
    private static function vendorsForShow(int $showId): array
    {
        $ids = get_posts([
            'post_type' => Vendors::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [['key' => Vendors::META_SHOW_ID, 'value' => $showId]],
        ]);

        $out = [];
        foreach ($ids as $id) {
            $rows = Vendors::history((int) $id);
            $index = Vendors::historyIndex($rows, $showId);
            if ($index >= 0) {
                $out[] = ['id' => (int) $id, 'row' => $rows[$index]];
            }
        }

        return $out;
    }

    private static function authorize(string $action): int
    {
        $showId = isset($_GET['show']) ? absint($_GET['show']) : 0;

        check_admin_referer($action.'_'.$showId);

        if (! $showId || ! current_user_can('edit_posts') && ! current_user_can('sccc_edit_shared_club_content')) {
            wp_die(esc_html__('You are not allowed to do that.', 'sccc'), 403);
        }

        return $showId;
    }

    public static function exportCsv(): void
    {
        $showId = self::authorize(self::CSV_ACTION);
        $filename = sanitize_file_name('vendors-'.sanitize_title(Vendors::eventTitle($showId)).'.csv');

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel.

        fputcsv($out, [
            'Business', 'Display name', 'Type', 'Show status', 'Fee', 'Paid', 'Paid on',
            'Contact', 'Email', 'Phone', 'OK to text', 'City', 'Show-day contact', 'Show-day phone',
            'Booth', 'Trailer length', 'Brings own', 'Electricity', 'Generator', 'Open flame', 'Staff',
            'OK to feature', 'Member offer', 'Website', 'Instagram', 'Facebook', 'TikTok', 'Notes', 'Vendor since',
        ]);

        $booths = Vendors::boothTypes();

        foreach (self::vendorsForShow($showId) as $item) {
            $id = $item['id'];
            $row = $item['row'];
            $meta = static fn (string $key): string => is_array($v = get_post_meta($id, $key, true)) ? implode(', ', $v) : (string) $v;

            fputcsv($out, [
                html_entity_decode(get_the_title($id), ENT_QUOTES, 'UTF-8'),
                $meta(Vendors::FIELD_DISPLAY_NAME),
                Vendors::typeTerm($id)?->name ?? '',
                Vendors::showStatusLabel((string) ($row[Vendors::ROW_STATUS] ?? '')),
                $row[Vendors::ROW_FEE] ?? '',
                $row[Vendors::ROW_PAID_AMOUNT] ?? '',
                $row[Vendors::ROW_PAID_AT] ?? '',
                Vendors::contactName($id),
                Vendors::contactEmail($id),
                $meta(Vendors::FIELD_PHONE),
                $meta(Vendors::FIELD_TEXT_OK) ? 'Yes' : 'No',
                $meta(Vendors::FIELD_CITY),
                $meta(Vendors::FIELD_DAYOF_NAME),
                $meta(Vendors::FIELD_DAYOF_PHONE),
                $booths[$meta(Vendors::FIELD_BOOTH_TYPE)] ?? '',
                $meta(Vendors::FIELD_TRAILER_LENGTH),
                $meta(Vendors::FIELD_EQUIPMENT),
                ucfirst($meta(Vendors::FIELD_POWER)),
                ucfirst($meta(Vendors::FIELD_GENERATOR)),
                ucfirst($meta(Vendors::FIELD_OPEN_FLAME)),
                $meta(Vendors::FIELD_STAFF_COUNT),
                $meta(Vendors::FIELD_FEATURE_OK) ? 'Yes' : 'No',
                $meta(Vendors::FIELD_OFFER),
                $meta(Vendors::FIELD_WEBSITE),
                $meta(Vendors::FIELD_INSTAGRAM),
                $meta(Vendors::FIELD_FACEBOOK),
                $meta(Vendors::FIELD_TIKTOK),
                $meta(Vendors::FIELD_NOTES),
                get_the_date('Y-m-d', $id),
            ]);
        }

        fclose($out);
        exit;
    }

    public static function exportLogos(): void
    {
        $showId = self::authorize(self::ZIP_ACTION);

        if (! class_exists('ZipArchive')) {
            wp_die(esc_html__('ZIP export is not available on this server (PHP ZipArchive missing).', 'sccc'));
        }

        $tmp = wp_tempnam('vendor-logos');
        $zip = new \ZipArchive();

        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('Could not create the ZIP file.', 'sccc'));
        }

        $count = 0;
        foreach (self::vendorsForShow($showId) as $item) {
            $id = $item['id'];

            if (($item['row'][Vendors::ROW_STATUS] ?? '') !== Vendors::SHOW_ACTIVE || ! get_post_meta($id, Vendors::FIELD_FEATURE_OK, true)) {
                continue;
            }

            $file = get_attached_file(Vendors::logoId($id));
            if ($file && file_exists($file)) {
                $zip->addFile($file, sanitize_file_name(sanitize_title(Vendors::businessName($id)).'.'.pathinfo($file, PATHINFO_EXTENSION)));
                $count++;
            }
        }

        if (! $count) {
            $zip->addFromString('README.txt', "No active vendors with feature permission and a logo for this show yet.\n");
        }

        $zip->close();

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.sanitize_file_name('vendor-logos-'.sanitize_title(Vendors::eventTitle($showId)).'.zip').'"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        exit;
    }
}
