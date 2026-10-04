<?php

/**
 * File path + filename: app/Blocks/FeaturedVendors.php
 *
 * Purpose:
 * - "Featured Vendors" block: logos of vendors who are ACTIVE (paid) for a
 *   show and allow us to feature them. Use it on the home page, show pages
 *   and in the lead-up to a show.
 *
 * Options:
 * - Show: "Next upcoming club show" (updates itself) or a specific show.
 * - Layout: logo grid or scrolling rail.
 * - Optional business name, vendor type pill (in the type color), and link to
 *   the vendor's website / Instagram.
 *
 * Data rules (App\Support\Vendors\Vendors):
 * - Vendor has an "active" row for the show (`_vendor_active_show_id`),
 *   "OK to feature" is on, and a logo exists. Other organizations' events are
 *   never used — only club shows with vendor applications switched on.
 */

declare(strict_types=1);

namespace App\Blocks;

use App\Support\Vendors\Vendors;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class FeaturedVendors extends Block
{
    public $name = 'Featured Vendors';

    public $slug = 'featured-vendors';

    public $view = 'blocks.featured-vendors';

    public $description = 'Logos of confirmed vendors for a show (vendors who allow featuring). Grid or scrolling rail.';

    public $category = 'theme-blocks';

    public $icon = 'store';

    public $keywords = ['vendor', 'vendors', 'show', 'logos', 'featured'];

    public $mode = 'preview';

    public $apiVersion = 3;

    public $supports = [
        'align' => ['wide', 'full'],
        'anchor' => true,
        'mode' => false,
        'multiple' => true,
        'jsx' => true,
    ];

    public function with(): array
    {
        $source = (string) (get_field('show_source') ?: 'next');
        $showId = $source === 'specific' ? (int) get_field('show') : Vendors::nextClubShow();

        if ($showId && ! Vendors::isClubShow($showId)) {
            $showId = 0;
        }

        $vendors = $showId ? self::vendorsFor($showId) : [];
        $layout = (string) (get_field('layout') ?: 'grid');

        return [
            'heading' => (string) (get_field('heading') ?: ''),
            'intro' => (string) (get_field('intro') ?: ''),
            'showTitle' => $showId ? Vendors::eventTitle($showId) : '',
            'showDate' => $showId && ($ts = Vendors::eventTimestamp($showId)) ? wp_date('F j, Y', $ts) : '',
            'showLine' => (bool) get_field('show_show_name'),
            'vendors' => $vendors,
            'layout' => in_array($layout, ['grid', 'rail'], true) ? $layout : 'grid',
            'columns' => max(2, min(6, (int) (get_field('columns') ?: 4))),
            'showNames' => (bool) get_field('show_names'),
            'showTypes' => (bool) get_field('show_types'),
            'linkVendors' => (bool) get_field('link_vendors'),
            'emptyMessage' => (string) get_field('empty_message'),
            'isPreview' => (bool) ($this->preview ?? false),
            'duration' => max(20, count($vendors) * 5),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function vendorsFor(int $showId): array
    {
        $ids = get_posts([
            'post_type' => Vendors::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => Vendors::META_ACTIVE_SHOW_ID, 'value' => $showId],
                ['key' => Vendors::FIELD_FEATURE_OK, 'value' => '1'],
            ],
        ]);

        $items = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $logo = Vendors::logoId($id);

            if (! $logo) {
                continue;
            }

            $term = Vendors::typeTerm($id);
            $website = (string) get_post_meta($id, Vendors::FIELD_WEBSITE, true);
            $instagram = ltrim(trim((string) get_post_meta($id, Vendors::FIELD_INSTAGRAM, true)), '@');

            $url = $website !== '' ? $website : '';
            if ($url === '' && $instagram !== '') {
                $url = str_starts_with($instagram, 'http') ? $instagram : 'https://instagram.com/'.rawurlencode($instagram);
            }

            $items[] = [
                'name' => Vendors::businessName($id),
                'logo' => $logo,
                'type' => $term ? $term->name : '',
                'color' => $term ? Vendors::typeColor($term) : '#64748b',
                'url' => $url,
            ];
        }

        return $items;
    }

    public function fields(): array
    {
        $fields = Builder::make('featured_vendors');

        $fields
            ->addText('heading', ['label' => 'Heading', 'default_value' => 'Meet our vendors'])
            ->addTextarea('intro', ['label' => 'Intro', 'rows' => 2, 'new_lines' => 'br'])
            ->addSelect('show_source', [
                'label' => 'Show',
                'choices' => ['next' => 'Next upcoming club show (automatic)', 'specific' => 'A specific show'],
                'default_value' => 'next',
                'return_format' => 'value',
                'wrapper' => ['width' => '50'],
            ])
            ->addPostObject('show', [
                'label' => 'Specific show',
                'post_type' => [Vendors::EVENT_POST_TYPE],
                'return_format' => 'id',
                'ui' => 1,
                'wrapper' => ['width' => '50'],
            ])
                ->conditional('show_source', '==', 'specific')
            ->addTrueFalse('show_show_name', ['label' => 'Show the show name and date', 'default_value' => 1, 'ui' => 1, 'wrapper' => ['width' => '50']])
            ->addSelect('layout', [
                'label' => 'Layout',
                'choices' => ['grid' => 'Logo grid', 'rail' => 'Scrolling rail'],
                'default_value' => 'grid',
                'return_format' => 'value',
                'wrapper' => ['width' => '25'],
            ])
            ->addNumber('columns', ['label' => 'Grid columns', 'default_value' => 4, 'min' => 2, 'max' => 6, 'wrapper' => ['width' => '25']])
                ->conditional('layout', '==', 'grid')
            ->addTrueFalse('show_names', ['label' => 'Show business names', 'default_value' => 1, 'ui' => 1, 'wrapper' => ['width' => '33']])
            ->addTrueFalse('show_types', ['label' => 'Show vendor type', 'default_value' => 1, 'ui' => 1, 'wrapper' => ['width' => '33']])
            ->addTrueFalse('link_vendors', ['label' => 'Link to vendor website / Instagram', 'default_value' => 1, 'ui' => 1, 'wrapper' => ['width' => '34']])
            ->addText('empty_message', [
                'label' => 'Message when no vendors are confirmed yet',
                'instructions' => 'Leave empty to hide the block until vendors are confirmed.',
            ]);

        return $fields->build();
    }
}
