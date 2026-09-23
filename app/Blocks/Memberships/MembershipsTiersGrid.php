<?php

namespace App\Blocks\Memberships;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * app/Blocks/Memberships/MembershipsTiersGrid.php
 * File: app/Blocks/Memberships/MembershipsTiersGrid.php
 *
 * What this block does
 * -----------------------------------------------------------------------------
 * - Gutenberg block that renders a membership pricing grid driven by selected
 *   PMPro membership level IDs (NO manual fallback).
 * - Levels shown = ONLY what the editor selected.
 * - Pricing (price + suffix) and description come from PMPro.
 * - Supports one featured (“Most Popular”) tier (defaults to “Member” if present).
 *
 * Key: Blade variable contract
 * -----------------------------------------------------------------------------
 * Blade view expects:
 * - $heading, $subheading
 * - $plans = [
 *    [
 *      'id'          => int,
 *      'name'        => string,
 *      'tagline'     => string,   // ✅ now preserves sanitized PMPro WYSIWYG HTML
 *      'price'       => string,
 *      'suffix'      => string,
 *      'cta_label'   => string,
 *      'cta_url'     => string,
 *      'is_featured' => bool,
 *      'badge'       => string|null,
 *    ], ...
 * ]
 */
class MembershipsTiersGrid extends Block
{
    /**
     * Blade view.
     */
    public $view = 'blocks.memberships-tiers-grid';

    /**
     * Block registration.
     */
    public function attributes(): array
    {
        return [
            'name'        => 'memberships-tiers-grid',
            'title'       => 'Memberships: Tiers Grid',
            'description' => 'Pricing grid driven by selected PMPro membership levels.',
            'category'    => 'theme-blocks',
            'icon'        => 'screenoptions',
            'keywords'    => ['pmpro', 'membership', 'tiers', 'pricing', 'grid'],
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
     * ACF fields (editor controls).
     */
    public function fields(): array
    {
        $fields = Builder::make('memberships_tiers_grid');

        $choices = $this->pmproLevelChoices();

        $fields
            ->addText('heading', [
                'label'         => 'Heading',
                'default_value' => 'Choose Your Membership',
            ])
            ->addTextarea('subheading', [
                'label'         => 'Subheading',
                'rows'          => 3,
                'new_lines'     => 'br',
                'default_value' => 'Pick the tier that fits you best. Upgrade any time.',
            ])
            ->addSelect('membership_levels', [
                'label'         => 'Membership Levels to Display',
                'required'      => 1,
                'multiple'      => 1,
                'ui'            => 1,
                'return_format' => 'value', // IMPORTANT: store IDs
                'choices'       => $choices,
                'instructions'  => empty($choices)
                    ? 'No PMPro levels found. Confirm PMPro is active and levels exist.'
                    : 'Choose which PMPro levels appear in this grid (order matters).',
            ])
            ->addSelect('most_popular_level', [
                'label'         => 'Most Popular Level',
                'required'      => 0,
                'allow_null'    => 1,
                'ui'            => 1,
                'return_format' => 'value',
                'choices'       => $choices,
                'instructions'  => 'Optional. If not set, we auto-pick a “Member” level (or first selected).',
            ])
            ->addText('most_popular_badge_text', [
                'label'         => 'Most Popular Badge Text',
                'default_value' => 'Most Popular',
            ])
            ->addText('cta_button_label', [
                'label'         => 'CTA Button Label',
                'default_value' => 'Join Now',
            ]);

        return $fields->build();
    }

    /**
     * Data passed to Blade.
     */
    public function with(): array
    {
        $heading   = (string) (get_field('heading') ?: 'Choose Your Membership');
        $subheading = (string) (get_field('subheading') ?: 'Pick the tier that fits you best. Upgrade any time.');

        $selectedRaw = get_field('membership_levels');
        $selectedIds = $this->normalizeSelectedLevelIds($selectedRaw);

        // Build a lookup by name in case ACF ever returns labels (defensive).
        $allLevels = $this->allPmproLevels();
        $levelsByName = [];
        foreach ($allLevels as $lvl) {
            $name = isset($lvl->name) ? (string) $lvl->name : '';
            $id   = isset($lvl->id) ? (int) $lvl->id : 0;
            if ($name !== '' && $id > 0) {
                $levelsByName[mb_strtolower($name)] = $id;
            }
        }

        // If ACF returned labels for some reason, map them to IDs.
        $finalIds = [];
        foreach ($selectedIds as $maybeIdOrName) {
            if (is_int($maybeIdOrName) && $maybeIdOrName > 0) {
                $finalIds[] = $maybeIdOrName;
                continue;
            }

            if (is_string($maybeIdOrName)) {
                $key = mb_strtolower(trim($maybeIdOrName));
                if (isset($levelsByName[$key])) {
                    $finalIds[] = (int) $levelsByName[$key];
                }
            }
        }
        $finalIds = array_values(array_unique(array_filter($finalIds)));

        // ✅ Resolve Most Popular reliably (ID, array, or label)
        $mostPopularRaw = get_field('most_popular_level');
        $mostPopularId  = $this->resolveLevelId($mostPopularRaw, $levelsByName);

        // If not set, use default behavior.
        if ($mostPopularId <= 0) {
            $mostPopularId = $this->autoPickMostPopular($finalIds);
        }

        // If user picked something not in the displayed list, fallback to default.
        if ($mostPopularId > 0 && !in_array($mostPopularId, $finalIds, true)) {
            $mostPopularId = $this->autoPickMostPopular($finalIds);
        }

        $badgeText = (string) (get_field('most_popular_badge_text') ?: 'Most Popular');
        $ctaLabel  = (string) (get_field('cta_button_label') ?: 'Join Now');

        $plans = [];

        foreach ($finalIds as $levelId) {
            $level = function_exists('pmpro_getLevel') ? pmpro_getLevel((int) $levelId) : null;
            if (empty($level) || !is_object($level)) {
                continue;
            }

            $name = !empty($level->name) ? (string) $level->name : 'Membership';
            $desc = !empty($level->description) ? (string) $level->description : '';

            $tagline = $this->cleanTagline($desc);
            $pricing = $this->priceAndSuffixFromLevel($level);

            $isFeatured = ((int) $levelId === (int) $mostPopularId);

            $plans[] = [
                'id'          => (int) $levelId,
                'name'        => $name,
                'tagline'     => $tagline,
                'price'       => $pricing['price'],
                'suffix'      => $pricing['suffix'],
                'cta_label'   => $ctaLabel,
                'cta_url'     => $this->checkoutUrlForLevel((int) $levelId),
                'is_featured' => $isFeatured,
                'badge'       => $isFeatured ? $badgeText : null,
            ];
        }

        return [
            'heading'   => $heading,
            'subheading'=> $subheading,
            'plans'     => $plans,
        ];
    }

    /**
     * Resolve a level ID from ACF returns:
     * - numeric string "3"
     * - int 3
     * - array ['value' => '3']
     * - array ['id' => 3] or ['ID' => 3]
     * - label string "Member" (map via $levelsByName)
     */
    protected function resolveLevelId($raw, array $levelsByName): int
    {
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        if (is_array($raw)) {
            if (isset($raw['value']) && is_numeric($raw['value'])) {
                return (int) $raw['value'];
            }

            if (isset($raw['id']) && is_numeric($raw['id'])) {
                return (int) $raw['id'];
            }
            if (isset($raw['ID']) && is_numeric($raw['ID'])) {
                return (int) $raw['ID'];
            }

            if (isset($raw['label']) && is_string($raw['label'])) {
                $key = mb_strtolower(trim($raw['label']));
                return isset($levelsByName[$key]) ? (int) $levelsByName[$key] : 0;
            }
        }

        if (is_string($raw)) {
            $key = mb_strtolower(trim($raw));
            return isset($levelsByName[$key]) ? (int) $levelsByName[$key] : 0;
        }

        return 0;
    }

    /**
     * Build PMPro level choices for ACF selects.
     */
    protected function pmproLevelChoices(): array
    {
        $choices = [];

        foreach ($this->allPmproLevels() as $level) {
            if (!is_object($level)) {
                continue;
            }
            $id = isset($level->id) ? (int) $level->id : 0;
            $name = isset($level->name) ? (string) $level->name : '';
            if ($id > 0 && $name !== '') {
                $choices[(string) $id] = $name;
            }
        }

        return $choices;
    }

    /**
     * Get all PMPro levels (safe wrapper).
     */
    protected function allPmproLevels(): array
    {
        if (!function_exists('pmpro_getAllLevels')) {
            return [];
        }

        $levels = pmpro_getAllLevels();
        return is_array($levels) ? $levels : [];
    }

    /**
     * Normalize ACF multi-select return into ints/strings we can map.
     */
    protected function normalizeSelectedLevelIds($raw): array
    {
        if (empty($raw)) {
            return [];
        }

        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $v) {
                if (is_numeric($v)) {
                    $out[] = (int) $v;
                } elseif (is_string($v)) {
                    $out[] = $v;
                } elseif (is_array($v) && isset($v['value'])) {
                    $out[] = is_numeric($v['value']) ? (int) $v['value'] : (string) $v['value'];
                }
            }
            return $out;
        }

        if (is_string($raw)) {
            $parts = array_map('trim', explode(',', $raw));
            $out = [];
            foreach ($parts as $p) {
                if ($p === '') {
                    continue;
                }
                $out[] = is_numeric($p) ? (int) $p : $p;
            }
            return $out;
        }

        return [];
    }

    /**
     * Auto-pick “Most Popular”: prefer a selected level named “Member”, else first selected.
     */
    protected function autoPickMostPopular(array $selectedIds): int
    {
        if (empty($selectedIds)) {
            return 0;
        }

        foreach ($selectedIds as $id) {
            $lvl = function_exists('pmpro_getLevel') ? pmpro_getLevel((int) $id) : null;
            if (!empty($lvl) && is_object($lvl) && !empty($lvl->name)) {
                if (mb_strtolower(trim((string) $lvl->name)) === 'member') {
                    return (int) $id;
                }
            }
        }

        return (int) $selectedIds[0];
    }

    /**
     * Clean a short tagline from PMPro HTML description.
     *
     * IMPORTANT (project behavior)
     * -----------------------------------------------------------------------------
     * PMPro descriptions are WYSIWYG HTML. We must preserve list markup (<ul><li>)
     * so the Blade template can style bullets consistently.
     *
     * Behavior:
     * - If HTML tags are present: return sanitized HTML (NOT stripped).
     * - If plain text only: escape + soft truncate to keep cards tidy.
     */
    protected function cleanTagline(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // If PMPro stored real HTML (WYSIWYG), keep it (sanitized).
        $hasHtml = (bool) preg_match('/<[^>]+>/', $html);
        if ($hasHtml) {
            return function_exists('wp_kses_post')
                ? (string) wp_kses_post($html)
                : $html;
        }

        // Plain text fallback: normalize whitespace + truncate (old behavior, but only for text).
        $text = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($html, true)
            : strip_tags($html);

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        $max = 140;
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max - 1) . '…';
        }

        // Return as plain text (Blade can wrap it if needed).
        return $text;
    }

    /**
     * Build price + suffix from PMPro level billing configuration.
     *
     * Priority:
     * 1) Recurring (billing_amount + cycle) => "/ month", "/ year", etc
     * 2) Expiration-based (expiration_number + expiration_period) => "/ year", etc
     * 3) Otherwise => "/ one-time"
     */
    protected function priceAndSuffixFromLevel(object $level): array
    {
        $billingAmount  = isset($level->billing_amount) ? (float) $level->billing_amount : 0.0;
        $initialPayment = isset($level->initial_payment) ? (float) $level->initial_payment : 0.0;

        $cycleNumber = isset($level->cycle_number) ? (int) $level->cycle_number : 0;
        $cyclePeriod = isset($level->cycle_period) ? (string) $level->cycle_period : '';

        $expNumber = isset($level->expiration_number) ? (int) $level->expiration_number : 0;
        $expPeriod = isset($level->expiration_period) ? (string) $level->expiration_period : '';

        $hasCycle = ($cycleNumber > 0 && trim($cyclePeriod) !== '');
        $hasExp   = ($expNumber > 0 && trim($expPeriod) !== '');

        // 1) Recurring
        if ($hasCycle && $billingAmount > 0) {
            return [
                'price'  => $this->formatPmproMoney($billingAmount),
                'suffix' => $this->periodSuffix($cycleNumber, $cyclePeriod),
            ];
        }

        // Some installs have cycle fields but billing_amount is 0; fall back to initial payment.
        if ($hasCycle && $billingAmount <= 0 && $initialPayment > 0) {
            return [
                'price'  => $this->formatPmproMoney($initialPayment),
                'suffix' => $this->periodSuffix($cycleNumber, $cyclePeriod),
            ];
        }

        // 2) Expiration-based (common for annual dues)
        if ($hasExp && $initialPayment > 0) {
            return [
                'price'  => $this->formatPmproMoney($initialPayment),
                'suffix' => $this->periodSuffix($expNumber, $expPeriod),
            ];
        }

        // Free tier edge cases
        if ($initialPayment <= 0 && $billingAmount <= 0) {
            return [
                'price'  => 'Free',
                'suffix' => $hasCycle ? $this->periodSuffix($cycleNumber, $cyclePeriod)
                         : ($hasExp ? $this->periodSuffix($expNumber, $expPeriod) : ''),
            ];
        }

        // 3) True one-time (no cycle, no expiration)
        $amount = $initialPayment > 0 ? $initialPayment : $billingAmount;

        return [
            'price'  => $this->formatPmproMoney($amount),
            'suffix' => '/ one-time',
        ];
    }

    /**
     * Convert a PMPro period into a suffix like:
     * "/ month", "/ year", "/ 3 months", "/ 2 years"
     */
    protected function periodSuffix(int $n, string $period): string
    {
        $p = mb_strtolower(trim($period));
        $p = rtrim($p, 's'); // normalize "Months" -> "month"

        if ($p === '') {
            return '';
        }

        if ($n > 1) {
            return '/ ' . $n . ' ' . $p . 's';
        }

        return '/ ' . $p;
    }

    /**
     * Backward-compatible wrapper (keeps existing calls safe).
     */
    protected function cycleSuffix(int $n, string $period): string
    {
        return $this->periodSuffix($n, $period);
    }

    /**
     * Format money using PMPro if available, otherwise a safe fallback.
     */
    protected function formatPmproMoney(float $amount): string
    {
        $price = function_exists('pmpro_formatPrice')
            ? (string) pmpro_formatPrice($amount)
            : ('$' . number_format($amount, 0));

        return html_entity_decode($price, ENT_QUOTES | ENT_HTML5);
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
