<?php

/**
 * File: app/View/Composers/ContentSingle.php
 *
 * View Composer for the single blog post partial.
 * Binds to: resources/views/partials/content-single.blade.php
 *
 * ── WHY ob_start() / ob_get_clean() is used here ────────────────────────
 * WordPress functions like the_content(), post_class(), and
 * comments_template() echo directly to PHP's output stream. When called
 * inside Blade's @section capture buffer, WordPress filter hooks
 * (particularly Gutenberg block rendering) call ob_start()/ob_end_clean()
 * internally, disrupting Blade's own output buffer.
 *
 * Fix: capture every echoing WordPress function here in the Composer,
 * BEFORE Blade starts any section buffers. Captured strings are passed
 * as plain variables and rendered with {!! $var !!} in the partial.
 * ────────────────────────────────────────────────────────────────────────
 *
 * Sidebar:
 *   The sidebar is no longer handled here. dynamic_sidebar('blog-sidebar')
 *   is called directly in resources/views/single.blade.php via
 *   @section('sidebar'), exactly as blog.blade.php handles its sidebar.
 *   The layout (layouts/single.blade.php) uses @hasSection('sidebar') to
 *   decide whether to render a two-column grid or single column.
 */

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class ContentSingle extends Composer
{
    /**
     * Bind this composer to the single post content partial only.
     *
     * @var array<string>
     */
    protected static $views = [
        'partials.content-single',
    ];

    /**
     * Data passed to the view before rendering.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            // ── Article element ──────────────────────────────────────
            'postClasses'       => $this->postClasses(),
            'postContent'       => $this->postContent(),
            'pagingHtml'        => $this->pagingHtml(),
            'commentsHtml'      => $this->commentsHtml(),

            // ── Article header ───────────────────────────────────────
            'readingTime'       => $this->readingTime(),
            'firstCat'          => $this->firstCat(),
            'heroImgUrl'        => $this->heroImgUrl(),
            'heroImgCaption'    => $this->heroImgCaption(),

            // ── Author (header row + bio card) ───────────────────────
            'authorName'        => $this->authorName(),
            'authorBio'         => $this->authorBio(),
            'authorUrl'         => $this->authorUrl(),
            // Two sizes — both use get_avatar() to honour all avatar hooks
            // (including LocalAvatar.php) rather than get_avatar_url().
            'authorAvatarSmall' => $this->authorAvatarSmall(),
            'authorAvatarLarge' => $this->authorAvatarLarge(),
            'postDate'          => $this->postDate(),
            'postDateMachine'   => $this->postDateMachine(),

            // ── Tags ─────────────────────────────────────────────────
            'tags'              => $this->tags(),

            // ── Read Next ────────────────────────────────────────────
            'readNextPosts'     => $this->readNextPosts(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Article element helpers (ob_start capture)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Returns post CSS classes as a plain string.
     * Uses get_post_class() (array, no echo) instead of post_class()
     * to avoid any buffer side-effects. 'h-entry' added for microformats2.
     */
    protected function postClasses(): string
    {
        return implode(' ', get_post_class('h-entry'));
    }

    /**
     * Captures the_content() — Gutenberg block renderers call ob_start/
     * ob_end_clean internally. Capturing here (before any Blade buffers)
     * prevents those calls from disrupting Blade's @section capture.
     */
    protected function postContent(): string
    {
        ob_start();
        the_content();

        return ob_get_clean() ?: '';
    }

    /**
     * wp_link_pages() with echo=>false — safe, no ob_start needed.
     */
    protected function pagingHtml(): string
    {
        return wp_link_pages([
            'before' => '',
            'after'  => '',
            'echo'   => false,
        ]) ?: '';
    }

    /**
     * Captures comments_template() — same ob_start pattern as postContent().
     */
    protected function commentsHtml(): string
    {
        if (! (comments_open() || get_comments_number())) {
            return '';
        }

        ob_start();
        comments_template();

        return ob_get_clean() ?: '';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Article header
    // ─────────────────────────────────────────────────────────────────────

    protected function readingTime(): int
    {
        $words = str_word_count(strip_tags(get_the_content()));

        return max(1, (int) ceil($words / 200));
    }

    protected function firstCat(): ?\WP_Term
    {
        $cats = get_the_category();

        return ! empty($cats) ? $cats[0] : null;
    }

    protected function heroImgUrl(): string
    {
        return get_the_post_thumbnail_url(get_the_ID(), 'full') ?: '';
    }

    protected function heroImgCaption(): string
    {
        $thumbId = get_post_thumbnail_id(get_the_ID());

        if (! $thumbId) {
            return '';
        }

        $attach = get_post($thumbId);

        return $attach ? wp_strip_all_tags($attach->post_excerpt) : '';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Author
    // ─────────────────────────────────────────────────────────────────────

    protected function authorId(): int
    {
        return (int) get_the_author_meta('ID');
    }

    protected function authorName(): string
    {
        return get_the_author() ?: '';
    }

    protected function authorBio(): string
    {
        return get_the_author_meta('description') ?: '';
    }

    protected function authorUrl(): string
    {
        return get_author_posts_url($this->authorId()) ?: '';
    }

    /**
     * 40px <img> tag for the article header author row.
     *
     * get_avatar() is used instead of get_avatar_url() because it respects
     * ALL WordPress avatar filter hooks, including custom avatar plugins
     * such as LocalAvatar.php. get_avatar_url() honours only a subset of
     * those hooks and may fall back to Gravatar (which can 404 in local dev)
     * when a local avatar plugin is active.
     */
    protected function authorAvatarSmall(): string
    {
        return get_avatar($this->authorId(), 40, '', esc_attr($this->authorName()), [
            'class' => 'size-10 rounded-full shrink-0 object-cover',
        ]) ?: '';
    }

    /**
     * 80px <img> tag for the author bio card below the article.
     * Same get_avatar() rationale as authorAvatarSmall().
     * The primary-colour ring is applied on the wrapper div in the partial.
     */
    protected function authorAvatarLarge(): string
    {
        return get_avatar($this->authorId(), 80, '', esc_attr($this->authorName()), [
            'class' => 'size-20 rounded-full shrink-0 object-cover',
        ]) ?: '';
    }

    protected function postDate(): string
    {
        return get_the_date('F j, Y') ?: '';
    }

    protected function postDateMachine(): string
    {
        return get_the_date('Y-m-d') ?: '';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tags
    // ─────────────────────────────────────────────────────────────────────

    /** @return array<\WP_Term>|false */
    protected function tags(): array|false
    {
        return get_the_tags();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Read Next
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Up to 3 recent posts (excluding current) as plain arrays.
     * Direct property access — no the_post() calls, no wp_reset_postdata().
     *
     * @return array<int, array{title:string,permalink:string,imgUrl:string,cat:string,date:string,readTime:int}>
     */
    protected function readNextPosts(): array
    {
        $query = new \WP_Query([
            'post_type'           => 'post',
            'posts_per_page'      => 3,
            'post__not_in'        => [(int) get_the_ID()],
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ]);

        $posts = [];

        foreach ($query->posts as $post) {
            $cats  = get_the_terms($post->ID, 'category');
            $words = str_word_count(strip_tags($post->post_content));

            $posts[] = [
                'title'     => get_the_title($post->ID),
                'permalink' => get_permalink($post->ID) ?: '',
                'imgUrl'    => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: '',
                'cat'       => (! empty($cats) && ! is_wp_error($cats)) ? $cats[0]->name : '',
                'date'      => get_the_date('M j, Y', $post->ID) ?: '',
                'readTime'  => max(1, (int) ceil($words / 200)),
            ];
        }

        return $posts;
    }
}