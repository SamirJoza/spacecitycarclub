<?php

namespace App\Blocks\Memberships;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use App\Support\Memberships\FoundingCap;

/**
 * app/Blocks/Memberships/MembershipsFoundingHero.php
 * File: app/Blocks/Memberships/MembershipsFoundingHero.php
 *
 * What this block does
 * -----------------------------------------------------------------------------
 * - Loads Blade view:
 *     resources/views/blocks/memberships-founding-hero.blade.php
 * - Pulls ALL membership copy from PMPro (name + description).
 * - ACF fields are only for presentation (heading/subheading/image/badges/price/cta label).
 *
 * Notes
 * -----------------------------------------------------------------------------
 * - ACF Composer v3+ (PHP 8.4): do NOT declare `$settings`.
 * - Use `attributes()` and set `$view` explicitly.
 */
class MembershipsFoundingHero extends Block
{
    /**
     * Blade view
     */
    public $view = 'blocks.memberships-founding-hero';

    /**
     * Block registration
     */
    public function attributes(): array
    {
        return [
            'name'        => 'memberships-founding-hero',
            'title'       => 'Memberships: Founding Hero',
            'description' => 'Hero section for Founding Membership with urgency badge.',
            'category'    => 'theme-blocks',
            'icon'        => 'tickets-alt',
            'keywords'    => ['pmpro', 'membership', 'founding', 'hero'],
            'mode'        => 'preview',
            'supports'    => [
                'align'           => false,
                'anchor'          => true,
                'customClassName' => true,
                'multiple'        => true,
                'jsx'             => true,
                'mode'            => false,
            ],
        ];
    }

    /**
     * ACF fields (editor controls)
     *
     * IMPORTANT: No description field here.
     * The membership tier description comes ONLY from PMPro.
     */
    public function fields(): array
    {
        $fields = Builder::make('memberships_founding_hero');

        $fields
            ->addText('heading', [
                'label'         => 'Heading',
                'default_value' => 'Join the Crew',
            ])
            ->addTextarea('subheading', [
                'label'         => 'Subheading',
                'rows'          => 3,
                'new_lines'     => 'br',
                'default_value' => 'Become a part of the fastest growing car community in Space City. From JDM legends to American muscle, all makes and models are welcome.',
            ])
            ->addImage('image', [
                'label'         => 'Left Image',
                'return_format' => 'url', // editor UX; we fetch raw for reliability
                'preview_size'  => 'medium',
            ])
            ->addText('badge_left', [
                'label'         => 'Left Pill Badge',
                'default_value' => 'Most Exclusive',
            ])
            ->addText('price', [
                'label'         => 'Price Display',
                'default_value' => '$350',
            ])
            ->addText('price_suffix', [
                'label'         => 'Price Suffix',
                'default_value' => '/ one-time',
            ])
            ->addText('cta_label', [
                'label'         => 'CTA Button Label',
                'default_value' => 'Secure Your Spot',
            ]);

        return $fields->build();
    }

    /**
     * Data passed to Blade
     */
    public function with(): array
    {
        $levelId = FoundingCap::FOUNDING_LEVEL_ID; // 2

        $pmproLevel = function_exists('pmpro_getLevel')
            ? pmpro_getLevel($levelId)
            : null;

        $levelName = (!empty($pmproLevel) && is_object($pmproLevel) && !empty($pmproLevel->name))
            ? (string) $pmproLevel->name
            : 'Founding Member Status';

        // PMPro description is the ONLY source of truth.
        $pmproDesc = (!empty($pmproLevel) && is_object($pmproLevel) && isset($pmproLevel->description))
            ? (string) $pmproLevel->description
            : '';

        // Presentation fields (safe defaults)
        $heading     = (string) (get_field('heading') ?: 'Join the Crew');
        $subheading  = (string) (get_field('subheading') ?: 'Become a part of the fastest growing car community in Space City. From JDM legends to American muscle, all makes and models are welcome.');
        $price       = (string) (get_field('price') ?: '$350');
        $priceSuffix = (string) (get_field('price_suffix') ?: '/ one-time');
        $badgeLeft   = (string) (get_field('badge_left') ?: 'Most Exclusive');
        $ctaLabel    = (string) (get_field('cta_label') ?: 'Secure Your Spot');

        // Image: fetch RAW value to work reliably in block preview + frontend.
        $rawImage = get_field('image', false, false);
        $imageUrl = $this->resolveImageUrlFromRaw($rawImage);

        return [
            'heading'      => $heading,
            'subheading'   => $subheading,
            'level_name'   => $levelName,
            'description'  => $pmproDesc, // ✅ ONLY PMPro
            'price'        => $price,
            'price_suffix' => $priceSuffix,
            'cta_label'    => $ctaLabel,
            'cta_url'      => FoundingCap::allowsCheckout()
                ? $this->checkoutUrlForLevel($levelId)
                : '',
            'image_url'    => $imageUrl,
            'badge_left'   => $badgeLeft,

            // REQUIRED: Only “Only XX spots available” (or '' if sold out)
            'badge_text'   => FoundingCap::badgeText(),
        ];
    }

    /**
     * Raw resolver (works regardless of ACF return_format).
     *
     * Typical raw values:
     * - int attachment ID
     * - string numeric ID
     * - string URL (rare)
     */
    protected function resolveImageUrlFromRaw($raw): string
    {
        if (empty($raw)) {
            return '';
        }

        if (is_int($raw)) {
            return (string) (wp_get_attachment_image_url($raw, 'full') ?: '');
        }

        if (is_string($raw)) {
            $raw = trim($raw);

            if ($raw === '') {
                return '';
            }

            if (ctype_digit($raw)) {
                $id = (int) $raw;
                return (string) (wp_get_attachment_image_url($id, 'full') ?: '');
            }

            if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                return $raw;
            }

            return '';
        }

        return '';
    }

    /**
     * Checkout URL for a PMPro level.
     */
    protected function checkoutUrlForLevel(int $levelId): string
    {
        if (function_exists('pmpro_url')) {
            $checkout = (string) pmpro_url('checkout');
            return (string) add_query_arg('level', $levelId, $checkout);
        }

        return (string) add_query_arg('level', $levelId, home_url('/membership-account/membership-checkout/'));
    }
}
