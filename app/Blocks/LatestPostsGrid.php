<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * File: app/Blocks/LatestPostsGrid.php
 *
 * Registers the Latest Posts Grid Gutenberg block for the Space City Car Club
 * Sage theme.
 *
 * Why this file exists:
 * - It defines the Gutenberg block in ACF Composer.
 * - It queries the latest 3 published blog posts.
 * - It prepares each post's data for the Blade view.
 *
 * Important implementation notes:
 * - This block intentionally has no user-configurable content fields because
 *   the requested behavior is automatic: always show the latest 3 posts.
 * - The `content_type` custom taxonomy is used for the eyebrow.
 * - If a post has no `content_type` term assigned, the eyebrow area is still
 *   reserved in the view so all cards remain visually aligned.
 */
class LatestPostsGrid extends Block
{
    /**
     * Gutenberg block title.
     *
     * @var string
     */
    public $name = 'Latest Posts Grid';

    /**
     * Gutenberg block description.
     *
     * @var string
     */
    public $description = 'Displays the latest three posts in a themed card grid.';

    /**
     * Block category.
     *
     * Note:
     * This assumes your project already uses this custom block category.
     *
     * @var string
     */
    public $category = 'theme-blocks';

    /**
     * Gutenberg inserter icon.
     *
     * @var string|array
     */
    public $icon = 'grid-view';

    /**
     * Search keywords in the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['posts', 'latest', 'news', 'content type', 'blog'];

    /**
     * Post types that can use this block.
     *
     * @var array<int, string>
     */
    public $post_types = ['post', 'page'];

    /**
     * Default Gutenberg editing mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Block supports.
     *
     * Why align is disabled:
     * - The user specifically wants the block to work *inside a container*.
     * - So we do not force a wide/full alignment model here.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'align' => false,
        'anchor' => true,
        'customClassName' => true,
    ];

    /**
     * Data passed into the Blade template.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'posts' => $this->latestPosts(),
        ];
    }

    /**
     * Block fields.
     *
     * Why this is intentionally minimal:
     * - The requested functionality is automatic and does not need settings.
     * - A small message helps editors understand what the block does.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('latest_posts_grid');

        $fields->addMessage(
            'latest_posts_grid_note',
            'This block automatically displays the latest three published posts. The eyebrow is pulled from the content_type taxonomy.'
        );

        return $fields->build();
    }

    /**
     * Query the latest 3 published posts.
     *
     * Query choices:
     * - `ignore_sticky_posts` keeps results strictly date-based.
     * - `no_found_rows` improves performance because pagination is not needed.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function latestPosts(): array
    {
        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
        ]);

        if (empty($query->posts)) {
            return [];
        }

        $posts = [];

        foreach ($query->posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }

            $posts[] = $this->formatPost($post);
        }

        wp_reset_postdata();

        return $posts;
    }

    /**
     * Prepare a single post for the view.
     *
     * @param WP_Post $post
     * @return array<string, mixed>
     */
    protected function formatPost(WP_Post $post): array
    {
        $imageId = (int) get_post_thumbnail_id($post);

        return [
            'id' => $post->ID,
            'eyebrow' => $this->contentTypeEyebrow($post->ID),
            'image' => $imageId ? $this->featuredImage($imageId) : '',
            'published' => get_the_date('F jS, Y', $post),
            'datetime' => get_the_date('c', $post),
            'title' => get_the_title($post),
            'excerpt' => $this->excerpt($post),
            'permalink' => (string) get_permalink($post),
        ];
    }

    /**
     * Get the first assigned content_type term as the eyebrow label.
     *
     * Why only the first term:
     * - The requested layout shows a single eyebrow.
     * - Using the first term keeps the card clean and predictable.
     *
     * @param int $postId
     * @return string
     */
    protected function contentTypeEyebrow(int $postId): string
    {
        $terms = get_the_terms($postId, 'content_type');

        if (empty($terms) || $terms instanceof WP_Error) {
            return '';
        }

        $term = $terms[0] ?? null;

        return $term ? (string) $term->name : '';
    }

    /**
     * Return the featured image markup.
     *
     * Why markup is prepared here:
     * - Keeps the Blade view cleaner.
     * - Ensures consistent image classes in one place.
     *
     * @param int $imageId
     * @return string
     */
    protected function featuredImage(int $imageId): string
    {
        return (string) wp_get_attachment_image($imageId, 'large', false, [
            'class' => 'sccc-latest-posts-grid__image',
            'loading' => 'lazy',
            'decoding' => 'async',
        ]);
    }

    /**
     * Build a card-friendly excerpt.
     *
     * Why trim the excerpt:
     * - Prevents card heights from becoming too inconsistent.
     * - Helps the 3-column layout remain cleaner.
     *
     * @param WP_Post $post
     * @return string
     */
    protected function excerpt(WP_Post $post): string
    {
        $excerpt = trim(wp_strip_all_tags(get_the_excerpt($post)));

        if ($excerpt === '') {
            return '';
        }

        return wp_trim_words($excerpt, 24, '…');
    }
}