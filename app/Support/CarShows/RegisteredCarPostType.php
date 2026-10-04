<?php

/**
 * File path + filename: app/Support/CarShows/RegisteredCarPostType.php
 *
 * Purpose:
 * - `registered_car` CPT: one record per car registered for a car show.
 * - Admin list: car number, car, owner, contact, show, paid, date; filter by
 *   show; sort by number; CSV export for the filtered show.
 * - "Car details" meta box so staff can fix typos or add a car by hand
 *   (walk-ups). A car added by hand gets the next number for its show.
 *
 * Notes:
 * - Created automatically by CarRegistrationForm after payment.
 * - Trash a car to cancel it: trashed cars no longer count as registered.
 *   Its number is not reused.
 */

namespace App\Support\CarShows;

use App\Support\Vendors\Vendors;

defined('ABSPATH') || exit;

final class RegisteredCarPostType
{
    private const NONCE = 'sccc_registered_car_details';

    private const FILTER_SHOW = 'car_show';

    private const EXPORT_ACTION = 'sccc_registered_cars_csv';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerPostType']);

        if (! is_admin()) {
            return;
        }

        add_filter('manage_'.CarShows::POST_TYPE.'_posts_columns', [self::class, 'columns']);
        add_action('manage_'.CarShows::POST_TYPE.'_posts_custom_column', [self::class, 'column'], 10, 2);
        add_filter('manage_edit-'.CarShows::POST_TYPE.'_sortable_columns', [self::class, 'sortable']);
        add_action('restrict_manage_posts', [self::class, 'filters']);
        add_action('pre_get_posts', [self::class, 'query']);
        add_action('manage_posts_extra_tablenav', [self::class, 'exportButton']);
        add_action('admin_post_'.self::EXPORT_ACTION, [self::class, 'exportCsv']);
        add_action('add_meta_boxes_'.CarShows::POST_TYPE, [self::class, 'metaBoxes']);
        add_action('save_post_'.CarShows::POST_TYPE, [self::class, 'save'], 10, 2);
        add_filter('post_row_actions', [self::class, 'rowActions'], 10, 2);
    }

    public static function registerPostType(): void
    {
        register_post_type(CarShows::POST_TYPE, [
            'labels' => [
                'name' => __('Registered Cars', 'sccc'),
                'singular_name' => __('Registered Car', 'sccc'),
                'add_new' => __('Add car', 'sccc'),
                'add_new_item' => __('Add a car by hand', 'sccc'),
                'edit_item' => __('Edit registered car', 'sccc'),
                'search_items' => __('Search cars', 'sccc'),
                'not_found' => __('No cars registered yet.', 'sccc'),
                'all_items' => __('Registered Cars', 'sccc'),
                'menu_name' => __('Registered Cars', 'sccc'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_position' => 27,
            'menu_icon' => 'dashicons-car',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /* -------------------------------------------------------------------------
     * List table
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    public static function columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'car_number' => __('#', 'sccc'),
            'title' => __('Car', 'sccc'),
            'car_owner' => __('Owner', 'sccc'),
            'car_contact' => __('Contact', 'sccc'),
            'car_show' => __('Show', 'sccc'),
            'car_paid' => __('Paid', 'sccc'),
            'date' => __('Registered', 'sccc'),
        ];
    }

    public static function column(string $column, int $postId): void
    {
        switch ($column) {
            case 'car_number':
                $number = (string) get_post_meta($postId, CarShows::META_NUMBER, true);
                echo '<strong style="font-size:1.25em;">'.esc_html($number !== '' ? '#'.$number : '—').'</strong>';
                break;

            case 'car_owner':
                echo esc_html(CarShows::ownerName($postId) ?: '—');
                $city = (string) get_post_meta($postId, CarShows::META_CITY, true);
                if ($city !== '') {
                    echo '<br><span class="description">'.esc_html($city).'</span>';
                }
                break;

            case 'car_contact':
                $email = (string) get_post_meta($postId, CarShows::META_EMAIL, true);
                $phone = (string) get_post_meta($postId, CarShows::META_PHONE, true);
                echo $email !== '' ? '<a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a>' : '';
                echo $phone !== '' ? '<br>'.esc_html($phone) : '';
                break;

            case 'car_show':
                $showId = (int) get_post_meta($postId, CarShows::META_SHOW, true);
                if ($showId) {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url(add_query_arg(['post_type' => CarShows::POST_TYPE, self::FILTER_SHOW => $showId], admin_url('edit.php'))),
                        esc_html(Vendors::eventLabel($showId, false))
                    );
                }
                break;

            case 'car_paid':
                $amount = (string) get_post_meta($postId, CarShows::META_AMOUNT, true);
                $source = (string) get_post_meta($postId, CarShows::META_SOURCE, true);
                echo esc_html($amount !== '' ? CarShows::money((float) $amount) : '—');
                $ticketName = (string) get_post_meta($postId, CarShows::META_TICKET_NAME, true);
                if ($ticketName !== '') {
                    echo '<br><span class="description">'.esc_html($ticketName).'</span>';
                }
                if ($source === 'manual') {
                    echo '<br><span class="description">'.esc_html__('added by hand', 'sccc').'</span>';
                }
                break;
        }
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    public static function sortable(array $columns): array
    {
        $columns['car_number'] = 'car_number';

        return $columns;
    }

    public static function filters(string $postType): void
    {
        if ($postType !== CarShows::POST_TYPE) {
            return;
        }

        $current = isset($_GET[self::FILTER_SHOW]) ? absint($_GET[self::FILTER_SHOW]) : 0; // phpcs:ignore WordPress.Security.NonceVerification

        echo '<select name="'.esc_attr(self::FILTER_SHOW).'"><option value="">'.esc_html__('All shows', 'sccc').'</option>';
        foreach (CarShows::carShowEventIds() as $eventId) {
            printf('<option value="%d"%s>%s</option>', $eventId, selected($current, $eventId, false), esc_html(Vendors::eventLabel($eventId, false)));
        }
        echo '</select>';
    }

    public static function query(\WP_Query $query): void
    {
        if (! $query->is_main_query() || $query->get('post_type') !== CarShows::POST_TYPE) {
            return;
        }

        $show = isset($_GET[self::FILTER_SHOW]) ? absint($_GET[self::FILTER_SHOW]) : 0; // phpcs:ignore WordPress.Security.NonceVerification

        if ($show) {
            $query->set('meta_query', [['key' => CarShows::META_SHOW, 'value' => (string) $show]]);
        }

        $orderby = (string) $query->get('orderby');

        if ($orderby === 'car_number' || ($show && $orderby === '')) {
            $query->set('meta_key', CarShows::META_NUMBER);
            $query->set('orderby', 'meta_value_num');
            $order = strtoupper((string) $query->get('order'));
            $query->set('order', $orderby === '' || ! in_array($order, ['ASC', 'DESC'], true) ? 'ASC' : $order);
        }
    }

    /**
     * @param  array<string, string>  $actions
     * @return array<string, string>
     */
    public static function rowActions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type === CarShows::POST_TYPE) {
            unset($actions['inline hide-if-no-js']);
            if (isset($actions['trash'])) {
                $actions['trash'] = str_replace('>'.__('Trash').'<', '>'.esc_html__('Cancel (trash)', 'sccc').'<', $actions['trash']);
            }
        }

        return $actions;
    }

    /* -------------------------------------------------------------------------
     * CSV export
     * ---------------------------------------------------------------------- */

    public static function exportButton(string $which): void
    {
        global $typenow;

        $show = isset($_GET[self::FILTER_SHOW]) ? absint($_GET[self::FILTER_SHOW]) : 0; // phpcs:ignore WordPress.Security.NonceVerification

        if ($which !== 'top' || $typenow !== CarShows::POST_TYPE || ! $show) {
            return;
        }

        $url = wp_nonce_url(admin_url('admin-post.php?action='.self::EXPORT_ACTION.'&show='.$show), self::EXPORT_ACTION);

        printf(
            '<div class="alignleft actions"><a class="button" href="%s">%s</a> <span class="description" style="line-height:30px;">%s</span></div>',
            esc_url($url),
            esc_html__('Export CSV', 'sccc'),
            esc_html(sprintf(__('%d cars registered', 'sccc'), CarShows::registeredCount($show)))
        );
    }

    public static function exportCsv(): void
    {
        check_admin_referer(self::EXPORT_ACTION);

        $type = get_post_type_object(CarShows::POST_TYPE);
        if (! $type || ! current_user_can($type->cap->edit_posts)) {
            wp_die(esc_html__('You are not allowed to export cars.', 'sccc'), 403);
        }

        $show = isset($_GET['show']) ? absint($_GET['show']) : 0;

        $ids = get_posts([
            'post_type' => CarShows::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => CarShows::META_NUMBER,
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => [['key' => CarShows::META_SHOW, 'value' => (string) $show]],
        ]);

        $filename = sanitize_file_name('registered-cars-'.sanitize_title(Vendors::eventTitle($show)).'-'.gmdate('Y-m-d').'.csv');

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Number', 'Year', 'Make', 'Model', 'Color', 'Notes', 'First name', 'Last name', 'Email', 'Phone', 'City', 'Ticket', 'Paid', 'Source', 'Registered']);

        foreach ($ids as $id) {
            $m = static fn (string $key): string => (string) get_post_meta((int) $id, $key, true);
            fputcsv($out, [
                $m(CarShows::META_NUMBER),
                $m(CarShows::META_YEAR),
                $m(CarShows::META_MAKE),
                $m(CarShows::META_MODEL),
                $m(CarShows::META_COLOR),
                $m(CarShows::META_NOTES),
                $m(CarShows::META_FIRST),
                $m(CarShows::META_LAST),
                $m(CarShows::META_EMAIL),
                $m(CarShows::META_PHONE),
                $m(CarShows::META_CITY),
                $m(CarShows::META_TICKET_NAME),
                $m(CarShows::META_AMOUNT),
                $m(CarShows::META_SOURCE) ?: 'online',
                get_the_date('Y-m-d H:i', (int) $id),
            ]);
        }

        fclose($out);
        exit;
    }

    /* -------------------------------------------------------------------------
     * Edit screen
     * ---------------------------------------------------------------------- */

    public static function metaBoxes(): void
    {
        remove_meta_box('slugdiv', CarShows::POST_TYPE, 'normal');
        add_meta_box('sccc-car-details', __('Car details', 'sccc'), [self::class, 'renderDetails'], CarShows::POST_TYPE, 'normal', 'high');
    }

    /** @return array<string, string> meta key => label */
    private static function editableFields(): array
    {
        return [
            CarShows::META_YEAR => __('Year', 'sccc'),
            CarShows::META_MAKE => __('Make', 'sccc'),
            CarShows::META_MODEL => __('Model', 'sccc'),
            CarShows::META_COLOR => __('Color', 'sccc'),
            CarShows::META_NOTES => __('Notes', 'sccc'),
            CarShows::META_FIRST => __('Owner first name', 'sccc'),
            CarShows::META_LAST => __('Owner last name', 'sccc'),
            CarShows::META_EMAIL => __('Email', 'sccc'),
            CarShows::META_PHONE => __('Phone', 'sccc'),
            CarShows::META_CITY => __('City', 'sccc'),
        ];
    }

    public static function renderDetails(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE, self::NONCE);

        $showId = (int) get_post_meta($post->ID, CarShows::META_SHOW, true);
        $number = (string) get_post_meta($post->ID, CarShows::META_NUMBER, true);

        echo '<p style="font-size:1.4em;margin:.5em 0 1em;"><strong>'.esc_html($number !== '' ? sprintf(__('Car #%s', 'sccc'), $number) : __('Number is assigned when you save with a show selected.', 'sccc')).'</strong></p>';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="sccc_car_show">'.esc_html__('Show', 'sccc').'</label></th><td>';
        if ($number !== '' && $showId) {
            // Numbers belong to a show — don't let a numbered car move to another show.
            echo esc_html(Vendors::eventLabel($showId)).'<input type="hidden" name="sccc_car[show]" value="'.esc_attr((string) $showId).'">';
        } else {
            echo '<select id="sccc_car_show" name="sccc_car[show]"><option value="">'.esc_html__('Choose a show…', 'sccc').'</option>';
            foreach (CarShows::carShowEventIds() as $eventId) {
                printf('<option value="%d"%s>%s</option>', $eventId, selected($showId, $eventId, false), esc_html(Vendors::eventLabel($eventId, false)));
            }
            echo '</select>';
        }
        echo '</td></tr>';

        foreach (self::editableFields() as $key => $label) {
            $value = (string) get_post_meta($post->ID, $key, true);
            $name = 'sccc_car['.esc_attr(ltrim($key, '_')).']';
            echo '<tr><th scope="row"><label>'.esc_html($label).'</label></th><td>';
            if ($key === CarShows::META_NOTES) {
                echo '<textarea name="'.$name.'" rows="3" class="large-text">'.esc_textarea($value).'</textarea>';
            } else {
                echo '<input type="'.($key === CarShows::META_EMAIL ? 'email' : 'text').'" name="'.$name.'" value="'.esc_attr($value).'" class="regular-text">';
            }
            echo '</td></tr>';
        }

        $entryId = (int) get_post_meta($post->ID, CarShows::META_ENTRY, true);
        $amount = (string) get_post_meta($post->ID, CarShows::META_AMOUNT, true);
        $transaction = (string) get_post_meta($post->ID, CarShows::META_TRANSACTION, true);

        echo '<tr><th scope="row">'.esc_html__('Payment', 'sccc').'</th><td>';
        echo esc_html($amount !== '' ? CarShows::money((float) $amount) : __('none recorded', 'sccc'));
        if ($transaction !== '') {
            echo ' · '.esc_html($transaction);
        }
        if ($entryId) {
            $formId = (int) CarRegistrationForm::formId();
            echo ' · <a href="'.esc_url(admin_url('admin.php?page=gf_entries&view=entry&id='.$formId.'&lid='.$entryId)).'">'.esc_html(sprintf(__('Form entry #%d', 'sccc'), $entryId)).'</a>';
        }
        echo '</td></tr></tbody></table>';
    }

    public static function save(int $postId, \WP_Post $post): void
    {
        if (
            ! isset($_POST[self::NONCE])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($postId)
            || ! current_user_can('edit_post', $postId)
        ) {
            return;
        }

        $input = isset($_POST['sccc_car']) && is_array($_POST['sccc_car']) ? wp_unslash($_POST['sccc_car']) : [];

        foreach (array_keys(self::editableFields()) as $key) {
            $raw = (string) ($input[ltrim($key, '_')] ?? '');
            $value = $key === CarShows::META_NOTES ? sanitize_textarea_field($raw)
                : ($key === CarShows::META_EMAIL ? sanitize_email($raw) : sanitize_text_field($raw));
            update_post_meta($postId, $key, $value);
        }

        $showId = absint($input['show'] ?? 0);
        $hasNumber = (string) get_post_meta($postId, CarShows::META_NUMBER, true) !== '';

        if ($showId && CarShows::isCarShow($showId) && ! $hasNumber) {
            update_post_meta($postId, CarShows::META_SHOW, (string) $showId);
            update_post_meta($postId, CarShows::META_NUMBER, (string) CarShows::reserveNumbers($showId, 1)[0]);
            if ((string) get_post_meta($postId, CarShows::META_SOURCE, true) === '') {
                update_post_meta($postId, CarShows::META_SOURCE, 'manual');
            }
        }

        // Keep the title in sync without re-triggering this hook.
        remove_action('save_post_'.CarShows::POST_TYPE, [self::class, 'save'], 10);
        wp_update_post(['ID' => $postId, 'post_title' => CarShows::buildTitle($postId)]);
        add_action('save_post_'.CarShows::POST_TYPE, [self::class, 'save'], 10, 2);
    }
}
