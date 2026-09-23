<?php

declare(strict_types=1);

namespace App\Support\Content;

use Log1x\AcfComposer\Builder;

use function acf_add_local_field_group;
use function add_action;
use function class_exists;
use function function_exists;
use function register_extended_post_type;
use function register_extended_taxonomy;
use function register_sidebar;

/**
 * -----------------------------------------------------------------------------
 * File path + filename: app/Support/CTA/Faq.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Register the FAQ content model used by the dedicated FAQ landing page.
 * - Keep the FAQ custom post type, taxonomy, ACF field registration, and the
 *   FAQ-specific widget area together in one feature file.
 *
 * Why this file exists:
 * - The FAQ page is a structured content source, not a normal archive.
 * - Each FAQ entry behaves like one question/answer record:
 *   question = post title, answer = ACF field, category = taxonomy term.
 * - The FAQ layout also needs a reusable widget area below the category nav so
 *   editors can place block-based sidebar content there later.
 *
 * Architectural decisions made here:
 * - FAQ items live in a dedicated `faq` post type.
 * - FAQ categories live in a dedicated `faq_category` taxonomy.
 * - FAQ answers are stored in a small ACF field group instead of the main post
 *   editor to keep content tight and controlled.
 * - A dedicated widget area is registered for the FAQ sidebar area beneath the
 *   category list.
 */
class Faq
{
    /**
     * FAQ custom post type key.
     */
    public const POST_TYPE = 'faq';

    /**
     * FAQ category taxonomy key.
     */
    public const TAXONOMY = 'faq_category';

    /**
     * FAQ template sidebar/widget area ID.
     */
    public const SIDEBAR = 'faq_sidebar_below_categories';

    /**
     * Register all hooks for this feature.
     */
    public static function register(): void
    {
        $instance = new self();

        add_action('init', [$instance, 'registerTaxonomy'], 5);
        add_action('init', [$instance, 'registerPostType'], 10);
        add_action('acf/init', [$instance, 'registerFields']);
        add_action('widgets_init', [$instance, 'registerSidebar']);
    }

    /**
     * Register the FAQ category taxonomy.
     */
    public function registerTaxonomy(): void
    {
        if (! function_exists('register_extended_taxonomy')) {
            return;
        }

        register_extended_taxonomy(
            self::TAXONOMY,
            [self::POST_TYPE],
            [
                'hierarchical'       => true,
                'public'             => false,
                'publicly_queryable' => false,
                'show_ui'            => true,
                'show_admin_column'  => true,
                'show_in_nav_menus'  => false,
                'show_tagcloud'      => false,
                'show_in_rest'       => false,
                'rewrite'            => false,
                'query_var'          => false,
                'exclusive'          => true,
            ],
            [
                'singular' => 'FAQ Category',
                'plural'   => 'FAQ Categories',
                'slug'     => 'faq-category',
            ]
        );
    }

    /**
     * Register the FAQ custom post type.
     */
    public function registerPostType(): void
    {
        if (! function_exists('register_extended_post_type')) {
            return;
        }

        register_extended_post_type(
            self::POST_TYPE,
            [
                'public'              => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_nav_menus'   => false,
                'show_in_admin_bar'   => true,
                'show_in_rest'        => false,
                'has_archive'         => false,
                'rewrite'             => false,
                'query_var'           => false,
                'menu_icon'           => 'dashicons-editor-help',
                'menu_position'       => 25,
                'supports'            => ['title', 'page-attributes'],
                'taxonomies'          => [self::TAXONOMY],
                'enter_title_here'    => 'Enter the FAQ question here',
                'quick_edit'          => false,
                'dashboard_glance'    => false,
                'dashboard_activity'  => false,
            ],
            [
                'singular' => 'FAQ',
                'plural'   => 'FAQs',
                'slug'     => 'faq',
            ]
        );
    }

    /**
     * Register the FAQ field group.
     */
    public function registerFields(): void
    {
        if (! class_exists(Builder::class) || ! function_exists('acf_add_local_field_group')) {
            return;
        }

        $fields = Builder::make('faq_fields');

        $fields
            ->setGroupConfig('title', 'FAQ Details')
            ->setGroupConfig('position', 'acf_after_title')
            ->setGroupConfig('style', 'seamless')
            ->setGroupConfig('label_placement', 'top')
            ->setGroupConfig('instruction_placement', 'label');

        $fields
            ->setLocation('post_type', '==', self::POST_TYPE);

        $fields
            ->addWysiwyg('faq_answer', [
                'label'        => 'Answer',
                'instructions' => 'Keep the answer concise. Basic formatting and links are allowed.',
                'required'     => 1,
                'tabs'         => 'visual',
                'toolbar'      => 'basic',
                'media_upload' => 0,
                'delay'        => 0,
            ]);

        acf_add_local_field_group($fields->build());
    }

    /**
     * Register the widget area shown below the FAQ category navigation.
     *
     * Why this exists:
     * - The FAQ sidebar needs a flexible editor-controlled area that can hold
     *   blocks now and other sidebar content later.
     * - Registering a dedicated sidebar keeps this area reusable and optional.
     */
    public function registerSidebar(): void
    {
        if (! function_exists('register_sidebar')) {
            return;
        }

        register_sidebar([
            'name'          => 'FAQ Sidebar Panel',
            'id'            => self::SIDEBAR,
            'description'   => 'Appears below the FAQ category navigation on the FAQ page template.',
            'before_widget' => '<section id="%1$s" class="widget %2$s sccc-faq-widget">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="sccc-faq-widget__title">',
            'after_title'   => '</h3>',
        ]);
    }
}

Faq::register();