<?php

/**
 * Space City Car Club
 * File: app/Support/CPT/TestimonialPostType.php
 *
 * Registers the Testimonial custom post type and customizes its admin list screen.
 *
 * Why this file exists:
 * - Testimonials should be managed as structured editorial records in wp-admin.
 * - The Gutenberg Testimonial block will later support:
 *   1) selecting an existing testimonial from this CPT
 *   2) manually entering a testimonial directly in the block
 *
 * Editorial model:
 * - Post Title     = Person's name
 * - Featured Image = Profile image
 * - ACF field      = testimonial_text
 * - ACF field      = testimonial_meta_line
 *
 * Important:
 * - The actual custom fields are registered separately in:
 *   app/Fields/TestimonialFields.php
 * - This file focuses on CPT registration and admin-list behavior.
 */

namespace App\Support\CPT;

class TestimonialPostType
{
    /**
     * Post type key.
     */
    public const POST_TYPE = 'testimonial';

    /**
     * Boot the CPT.
     *
     * Why this exists:
     * - WordPress requires post types to be registered on `init`.
     * - Admin list hooks are also attached here so the class is self-contained.
     *
     * @return void
     */
    public static function boot(): void
    {
        add_action('init', [static::class, 'register']);

        add_filter('enter_title_here', [static::class, 'filterTitlePlaceholder']);
        add_filter('manage_' . static::POST_TYPE . '_posts_columns', [static::class, 'filterAdminColumns']);
        add_action('manage_' . static::POST_TYPE . '_posts_custom_column', [static::class, 'renderAdminColumn'], 10, 2);
    }

    /**
     * Register the Testimonial custom post type.
     *
     * Why these choices exist:
     * - Admin only for now; no public archive or single pages yet.
     * - Uses title + thumbnail + revisions only.
     * - The main testimonial text is intentionally NOT the core editor;
     *   it lives in a dedicated ACF textarea field instead.
     *
     * @return void
     */
    public static function register(): void
    {
        register_post_type(
            static::POST_TYPE,
            [
                'labels' => [
                    'name' => 'Testimonials',
                    'singular_name' => 'Testimonial',
                    'menu_name' => 'Testimonials',
                    'name_admin_bar' => 'Testimonial',
                    'add_new' => 'Add New',
                    'add_new_item' => 'Add New Testimonial',
                    'edit_item' => 'Edit Testimonial',
                    'new_item' => 'New Testimonial',
                    'view_item' => 'View Testimonial',
                    'view_items' => 'View Testimonials',
                    'search_items' => 'Search Testimonials',
                    'not_found' => 'No testimonials found.',
                    'not_found_in_trash' => 'No testimonials found in Trash.',
                    'all_items' => 'All Testimonials',
                    'archives' => 'Testimonial Archives',
                    'attributes' => 'Testimonial Attributes',
                    'insert_into_item' => 'Insert into testimonial',
                    'uploaded_to_this_item' => 'Uploaded to this testimonial',
                    'featured_image' => 'Profile Image',
                    'set_featured_image' => 'Set profile image',
                    'remove_featured_image' => 'Remove profile image',
                    'use_featured_image' => 'Use as profile image',
                    'filter_items_list' => 'Filter testimonials list',
                    'filter_by_date' => 'Filter testimonials by date',
                    'items_list_navigation' => 'Testimonials list navigation',
                    'items_list' => 'Testimonials list',
                    'item_published' => 'Testimonial published.',
                    'item_updated' => 'Testimonial updated.',
                ],

                /**
                 * Visibility / behavior
                 */
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_in_admin_bar' => false,
                'show_in_nav_menus' => false,
                'show_in_rest' => true,

                /**
                 * Keep this admin-managed only for now.
                 */
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'has_archive' => false,
                'rewrite' => false,
                'query_var' => false,

                /**
                 * Core feature support
                 *
                 * - title      => person name
                 * - thumbnail  => profile image
                 * - revisions  => editorial safety
                 */
                'supports' => [
                    'title',
                    'thumbnail',
                    'revisions',
                ],

                /**
                 * Admin polish
                 */
                'menu_icon' => 'dashicons-format-quote',
                'menu_position' => 23,

                /**
                 * Testimonials are editorial records, not user-owned records.
                 */
                'delete_with_user' => false,
            ]
        );
    }

    /**
     * Adjust the title placeholder for testimonials only.
     *
     * Why this exists:
     * - Makes the edit screen clearer for content editors.
     *
     * @param string $placeholder Default placeholder text.
     * @return string
     */
    public static function filterTitlePlaceholder(string $placeholder): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen instanceof \WP_Screen) {
            return $placeholder;
        }

        if ($screen->post_type !== static::POST_TYPE) {
            return $placeholder;
        }

        return 'Name of Person';
    }

    /**
     * Define the admin list columns for the Testimonial CPT.
     *
     * Why this exists:
     * - The default post list is too generic.
     * - Editors should be able to scan profile image, name, quote preview,
     *   and supporting member line quickly.
     *
     * @param array<string, string> $columns Existing columns.
     * @return array<string, string>
     */
    public static function filterAdminColumns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'profile_image' => 'Profile Image',
            'title' => 'Name',
            'testimonial_preview' => 'Testimonial',
            'testimonial_meta_line' => 'Member Line',
            'date' => 'Date',
        ];
    }

    /**
     * Render custom admin column content.
     *
     * Why this exists:
     * - Profile image should be visible in the list view.
     * - Testimonial text should show as a short preview, not a giant block.
     * - Supporting member line should be directly visible.
     *
     * @param string $column  Current column key.
     * @param int    $postId  Current testimonial post ID.
     * @return void
     */
    public static function renderAdminColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'profile_image':
                static::renderProfileImageColumn($postId);
                break;

            case 'testimonial_preview':
                static::renderTestimonialPreviewColumn($postId);
                break;

            case 'testimonial_meta_line':
                static::renderMetaLineColumn($postId);
                break;
        }
    }

    /**
     * Render the profile image column.
     *
     * Why this exists:
     * - The featured image acts as the profile image for testimonials.
     *
     * @param int $postId Testimonial post ID.
     * @return void
     */
    protected static function renderProfileImageColumn(int $postId): void
    {
        if (! has_post_thumbnail($postId)) {
            echo '—';
            return;
        }

        echo get_the_post_thumbnail(
            $postId,
            [56, 56],
            [
                'style' => 'width:56px;height:56px;border-radius:9999px;object-fit:cover;',
                'alt' => '',
            ]
        );
    }

    /**
     * Render the testimonial preview column.
     *
     * Why this exists:
     * - Keeps the list table readable.
     * - Uses the dedicated ACF field stored in post meta.
     *
     * @param int $postId Testimonial post ID.
     * @return void
     */
    protected static function renderTestimonialPreviewColumn(int $postId): void
    {
        $testimonialText = get_post_meta($postId, 'testimonial_text', true);

        if (! is_string($testimonialText) || trim($testimonialText) === '') {
            echo '—';
            return;
        }

        echo esc_html(
            wp_trim_words(
                wp_strip_all_tags($testimonialText),
                18,
                '…'
            )
        );
    }

    /**
     * Render the supporting member line column.
     *
     * Example:
     * - Platinum Member since 2021
     *
     * @param int $postId Testimonial post ID.
     * @return void
     */
    protected static function renderMetaLineColumn(int $postId): void
    {
        $metaLine = get_post_meta($postId, 'testimonial_meta_line', true);

        if (! is_string($metaLine) || trim($metaLine) === '') {
            echo '—';
            return;
        }

        echo esc_html($metaLine);
    }
}

/**
 * Boot immediately when this file is loaded by the theme.
 */
TestimonialPostType::boot();