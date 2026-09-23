<?php

namespace App\Blocks;

use App\Support\FeaturedVehicles\FeaturedVehicleArchiveRepository;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * File: app/Blocks/Feature.php
 *
 * Feature (ACF Block)
 * ===================
 *
 * PURPOSE
 * -------
 * Vehicle-only monthly feature block.
 *
 * WHAT THIS VERSION DOES
 * ----------------------
 * - Removes the older freeform/member/vehicle mode switching
 * - Pulls the featured member + vehicle from the saved monthly archive repository
 * - Keeps the block editor fields focused on presentation only
 * - Preserves the owner modal data contract already used by the Blade template
 * - Adds optional per-block section background image support
 * - Adds an optional per-block parallax toggle
 *
 * RUNTIME FLOW
 * ------------
 * This block does NOT build the eligible pool itself.
 * Instead, it asks the archive repository for the current month's data.
 *
 * The repository is responsible for:
 * - cheap cache-first reads
 * - looking up the saved monthly archive CPT record
 * - falling back to the resolver only if the current month has not been created yet
 *
 * WHY THIS MATTERS
 * ----------------
 * The block stays presentation-focused and page loads remain light.
 *
 * EDITOR FIELDS
 * -------------
 * The block allows editors to control:
 * - media position
 * - headline
 * - highlight style for [[...]] text
 * - subhead
 * - body copy
 * - CTA
 * - optional image fallback
 * - optional section background image
 * - optional parallax toggle
 * - section top/bottom padding
 * - parallax speed
 * - whether vehicle notes should be used when the body is empty
 *
 * NOTE ON BODY CONTENT
 * --------------------
 * If the editor leaves Body empty and enables "Use Vehicle Notes if Body is empty",
 * the block will use the archived vehicle notes snapshot for that month's feature.
 *
 * IMPORTANT
 * ---------
 * This file assumes the following support files are already available:
 * - app/Support/FeaturedVehicles/RegisterFeaturedVehiclePostType.php
 * - app/Support/FeaturedVehicles/FeaturedVehicleResolver.php
 * - app/Support/FeaturedVehicles/FeaturedVehicleArchiveRepository.php
 */
class Feature extends Block
{
    /**
     * The block name shown in the editor.
     */
    public $name = 'Feature';

    /**
     * Short editor description.
     */
    public $description = 'Monthly featured vehicle block with archived member/vehicle data.';

    /**
     * Block category.
     */
    public $category = 'theme-blocks';

    /**
     * Block icon.
     */
    public $icon = 'slides';

    /**
     * Search keywords in the editor.
     */
    public $keywords = ['feature', 'vehicle', 'monthly', 'spotlight'];

    /**
     * Allowed post types.
     */
    public $post_types = ['post', 'page'];

    /**
     * Default block mode in the editor.
     */
    public $mode = 'preview';

    /**
     * Standard block supports used elsewhere in the project.
     */
    public $supports = [
        'align'         => true,
        'anchor'        => true,
        'mode'          => true,
        'multiple'      => true,
        'jsx'           => true,
        'color'         => [
            'background' => true,
            'text'       => true,
            'gradients'  => true,
        ],
        'spacing'       => [
            'padding' => true,
            'margin'  => true,
        ],
    ];

    /**
     * Provide data to the Blade template.
     *
     * WHY THIS METHOD EXISTS
     * ----------------------
     * ACF Composer blocks pass render data to Blade through with().
     *
     * WHAT HAPPENS HERE
     * -----------------
     * 1. Read editor-controlled presentation fields
     * 2. Ask the repository for the current month's archived feature data
     * 3. Merge the archive data into the shape expected by the Blade template
     * 4. Apply a small amount of presentation logic, such as fallback image/body behavior
     */
    public function with(): array
    {
        /**
         * Editor-controlled presentation fields
         */
        $mediaPos = (string) (get_field('media_position') ?: 'left');

        $headlineRaw = (string) (get_field('headline') ?: '');
        $subhead     = (string) (get_field('subhead') ?: '');
        $body        = (string) (get_field('body') ?: '');
        $cta         = get_field('cta') ?: null;

        /**
         * Optional image fallback.
         *
         * WHY:
         * The archive should normally provide a valid vehicle image snapshot,
         * but keeping this fallback makes the block slightly more resilient.
         */
        $fallbackImageId = (int) (get_field('image') ?: 0);

        /**
         * Optional section background image.
         *
         * WHY:
         * This is separate from the featured vehicle image inside the card.
         * It allows editors to define a decorative background on a per-block basis.
         */
        $sectionBackgroundImage = (int) (get_field('section_background_image') ?: 0);

        /**
         * Optional parallax toggle for the section background layer.
         */
        $enableParallax = (bool) get_field('enable_parallax');

        /**
         * Section spacing controls.
         *
         * WHY:
         * The background image can be full-width while the actual card stays
         * inside the standard content width. Extra vertical padding gives the
         * parallax layer room to breathe without making the card itself wider.
         */
        $sectionPaddingTopRaw = get_field('section_padding_top');
        $sectionPaddingTop = is_numeric($sectionPaddingTopRaw) ? (int) $sectionPaddingTopRaw : 96;
        $sectionPaddingTop = max(0, min(240, $sectionPaddingTop));

        $sectionPaddingBottomRaw = get_field('section_padding_bottom');
        $sectionPaddingBottom = is_numeric($sectionPaddingBottomRaw) ? (int) $sectionPaddingBottomRaw : 96;
        $sectionPaddingBottom = max(0, min(240, $sectionPaddingBottom));

        /**
         * Parallax speed / travel control.
         *
         * WHY:
         * ScrollTrigger ties the movement to scroll progress. In practice, the
         * editor-facing "speed" works best as movement intensity: higher values
         * move the background farther across the same scroll distance.
         */
        $parallaxSpeedRaw = get_field('parallax_speed');
        $parallaxSpeed = is_numeric($parallaxSpeedRaw) ? (float) $parallaxSpeedRaw : 24.0;
        $parallaxSpeed = max(0.0, min(80.0, $parallaxSpeed));

        /**
         * Highlight style for [[...]] headline segments.
         */
        $headlineHighlight = $this->sanitizeHighlightStyle((string) (get_field('headline_highlight') ?: 'club-blue'));

        /**
         * Optional editor convenience:
         * use the archived vehicle notes when Body is empty.
         */
        $useVehNotes = (bool) get_field('use_vehicle_notes');

        /**
         * Pull the current month's saved archive data.
         *
         * EXPECTED SHAPE FROM THE REPOSITORY
         * ----------------------------------
         * [
         *   'archive'     => [...],
         *   'monthYear'   => 'March 2026',
         *   'imageId'     => 123,
         *   'member'      => [...],
         *   'ownerModal'  => [...],
         *   'vehicleData' => [...],
         * ]
         */
        $featureData = FeaturedVehicleArchiveRepository::getCurrentMonthFeatureData();

        $archive     = is_array($featureData) ? ($featureData['archive'] ?? []) : [];
        $member      = is_array($featureData) ? ($featureData['member'] ?? null) : null;
        $ownerModal  = is_array($featureData) ? ($featureData['ownerModal'] ?? null) : null;
        $vehicleData = is_array($featureData) ? ($featureData['vehicleData'] ?? null) : null;

        /**
         * Prefer the archived chosen image.
         * Fall back to the block image only if needed.
         */
        $archiveImageId = is_array($featureData) ? (int) ($featureData['imageId'] ?? 0) : 0;
        $finalImageId   = $archiveImageId ?: $fallbackImageId;

        /**
         * Body fallback behavior
         *
         * If the editor leaves Body empty and wants vehicle notes used,
         * use the archived vehicle notes snapshot.
         */
        $bodyFinal = $body;

        if (
            $useVehNotes &&
            trim($bodyFinal) === '' &&
            is_array($vehicleData) &&
            !empty($vehicleData['notes'])
        ) {
            $bodyFinal = $this->formatVehicleNotesBody((string) $vehicleData['notes']);
        }

        /**
         * Headline with [[highlighted text]] support.
         */
        $headlineHtml = $this->headlineWithHighlights($headlineRaw, $headlineHighlight);

        /**
         * Legacy compatibility values
         *
         * WHY:
         * Older iterations of the Blade template referenced these keys.
         * Keeping them here avoids unnecessary template breakage while the
         * latest vehicle-only template only uses the newer structure.
         */
        $membershipNumber = '';
        if (is_array($member)) {
            $membershipNumber = (string) ($member['member_number'] ?? '');
        }

        /**
         * The current vehicle-only block contract.
         */
        return [
            /**
             * Kept for compatibility with older template iterations.
             * The block is now vehicle-only.
             */
            'mode' => 'vehicle',

            /**
             * Layout / presentation controls
             */
            'mediaPos'              => $mediaPos,
            'headlineRaw'           => $headlineRaw,
            'headlineHtml'          => $headlineHtml,
            'headline_highlight'    => $headlineHighlight,
            'subhead'               => $subhead,
            'body'                  => $bodyFinal,
            'cta'                   => $cta,
            'sectionBackgroundImage'=> $sectionBackgroundImage,
            'enableParallax'        => $enableParallax,
            'sectionPaddingTop'     => $sectionPaddingTop,
            'sectionPaddingBottom'  => $sectionPaddingBottom,
            'parallaxSpeed'         => $parallaxSpeed,

            /**
             * Archive-driven display data
             */
            'archive'     => $archive,
            'monthYear'   => is_array($featureData) ? (string) ($featureData['monthYear'] ?? '') : '',
            'imageId'     => $finalImageId,
            'member'      => $member,
            'ownerModal'  => $ownerModal,
            'vehicleData' => $vehicleData,

            /**
             * Legacy compatibility values
             */
            'membershipNumber' => $membershipNumber,
            'membershipIssued' => '',
        ];
    }

    /**
     * Define the ACF field group for the block.
     *
     * WHY THIS IS NOW SIMPLER
     * -----------------------
     * The feature selection is automatic and archive-driven, so editors only
     * control presentation/content helpers now.
     */
    public function fields(): array
    {
        $fields = Builder::make('feature');

        $fields
            ->addRadio('media_position', [
                'label' => 'Media Position',
                'choices' => [
                    'left'  => 'Left',
                    'right' => 'Right',
                ],
                'default_value' => 'left',
                'layout' => 'horizontal',
            ])

            ->addText('headline', [
                'label' => 'Headline',
                'instructions' => 'Optional: wrap words in [[double brackets]] to highlight them.',
            ])

            ->addSelect('headline_highlight', [
                'label' => 'Headline Highlight Style',
                'choices' => [
                    'club-blue'         => 'Club Blue',
                    'signal-red'        => 'Signal Red',
                    'space-city'        => 'Space City',
                    'club-blue-solid'   => 'Club Blue Solid',
                    'signal-red-solid'  => 'Signal Red Solid',
                    'deep-purple-solid' => 'Deep Purple Solid',
                ],
                'default_value' => 'club-blue',
                'ui' => 1,
                'instructions' => 'Use [[double brackets]] in the headline to apply the selected highlight style. You may also use <strong>bold text</strong>.',
            ])

            ->addText('subhead', [
                'label' => 'Subhead',
            ])

            ->addWysiwyg('body', [
                'label' => 'Body',
                'media_upload' => 0,
                'tabs' => 'visual',
                'toolbar' => 'basic',
            ])

            ->addLink('cta', [
                'label' => 'CTA (optional)',
                'return_format' => 'array',
            ])

            ->addImage('image', [
                'label' => 'Fallback Image (optional)',
                'instructions' => 'Used only if the archived monthly feature image is unavailable.',
                'return_format' => 'id',
                'preview_size' => 'large',
                'library' => 'all',
            ])

            ->addImage('section_background_image', [
                'label' => 'Section Background Image (optional)',
                'instructions' => 'Optional decorative background image for this specific block instance.',
                'return_format' => 'id',
                'preview_size' => 'large',
                'library' => 'all',
            ])

            ->addRange('section_padding_top', [
                'label' => 'Section Top Padding',
                'instructions' => 'Adds vertical room above the centered content so the full-width background/parallax treatment is more visible.',
                'min' => 0,
                'max' => 240,
                'step' => 4,
                'default_value' => 96,
                'append' => 'px',
            ])

            ->addRange('section_padding_bottom', [
                'label' => 'Section Bottom Padding',
                'instructions' => 'Adds vertical room below the centered content so the full-width background/parallax treatment is more visible.',
                'min' => 0,
                'max' => 240,
                'step' => 4,
                'default_value' => 96,
                'append' => 'px',
            ])

            ->addTrueFalse('enable_parallax', [
                'label' => 'Enable Background Parallax',
                'instructions' => 'Applies a subtle scroll parallax effect to the optional section background image.',
                'ui' => 1,
                'default_value' => 1,
            ])

            ->addRange('parallax_speed', [
                'label' => 'Parallax Speed',
                'instructions' => 'Controls how far the background travels during scroll. Higher values feel faster/more dramatic.',
                'min' => 0,
                'max' => 80,
                'step' => 1,
                'default_value' => 24,
                'append' => '%',
            ])

            ->addTrueFalse('use_vehicle_notes', [
                'label' => 'Use Vehicle Notes if Body is empty',
                'ui' => 1,
                'default_value' => 1,
            ]);

        return $fields->build();
    }

    /**
     * No special asset registration is needed for this block class.
     *
     * NOTE
     * ----
     * The current modal behavior is handled in the Blade template itself.
     */
    public function assets(array $block): void
    {
        //
    }

    /**
     * Convert [[highlighted text]] into controlled highlight span markup.
     *
     * WHY THIS METHOD EXISTS
     * ----------------------
     * Editors can keep writing natural headlines while controlling only the
     * words that need visual emphasis. Only <strong> is allowed from editor
     * input so bold emphasis can be combined safely with the selected highlight
     * style.
     *
     * EXAMPLE
     * -------
     * "Meet our [[Monthly <strong>Feature</strong>]]"
     *
     * becomes:
     * "Meet our <span class=\"sccc-text-gradient sccc-text-gradient--club-blue\">Monthly <strong>Feature</strong></span>"
     */
    protected function headlineWithHighlights(string $headline, string $highlightStyle = 'club-blue'): string
    {
        $headline = trim((string) $headline);

        if ($headline === '') {
            return '';
        }

        $highlightStyle = $this->sanitizeHighlightStyle($highlightStyle);
        $class = 'sccc-text-gradient sccc-text-gradient--' . $highlightStyle;

        // Allow only the intentional inline formatting needed by this block.
        $safe = wp_kses($headline, [
            'strong' => [],
        ]);

        $safe = preg_replace_callback('/\\[\\[(.+?)\\]\\]/s', function ($matches) use ($class) {
            $inner = isset($matches[1]) ? $matches[1] : '';

            return '<span class="' . esc_attr($class) . '" data-sccc-highlight>' . $inner . '</span>';
        }, $safe);

        return $safe ?: '';
    }

    /**
     * Format archived vehicle notes for safe frontend display.
     *
     * WHY THIS METHOD EXISTS
     * ----------------------
     * Vehicle notes can arrive in either of these shapes depending on where the
     * value came from:
     *
     * 1. Raw textarea text with real line breaks.
     * 2. ACF-formatted text where line breaks have already become <br> tags.
     * 3. Older archive snapshots that may contain both <br> tags and real line breaks.
     *
     * Running nl2br() directly against option 2 or 3 causes duplicated visual
     * spacing. Stripping tags with the remove-breaks flag set to true removes
     * intentional line breaks completely. This method normalizes first, preserves
     * intentional single line breaks, then converts them exactly once.
     *
     * SAFETY
     * ------
     * Vehicle notes are treated as plain prose, not trusted HTML. We convert
     * allowed legacy <br> markup back to line breaks, strip any other markup,
    escape the plain text, then add <br> tags one time for display.
     */
    protected function formatVehicleNotesBody(string $notes): string
    {
        $notes = trim($notes);

        if ($notes === '') {
            return '';
        }

        /**
         * Convert existing ACF-generated or archived <br> tags back into plain
         * line breaks first so we can normalize all inputs the same way.
         */
        $notes = preg_replace('/<br\s*\/?>/i', "\n", $notes) ?: $notes;

        /**
         * Decode entities before stripping tags so escaped editor text displays
         * naturally after the final escaping pass.
         */
        $notes = html_entity_decode($notes, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');

        /**
         * Strip all remaining tags, but DO NOT remove breaks.
         *
         * IMPORTANT:
         * wp_strip_all_tags($text, true) removes leftover line breaks and white
         * space characters. That is exactly what caused intentional line breaks
         * to disappear. Leaving the second argument false preserves them.
         */
        $notes = wp_strip_all_tags($notes, false);

        /**
         * Normalize platform-specific line endings and collapse excessive blank
         * lines left behind by older double-formatting while preserving one
         * intentional blank line between prose chunks.
         */
        $notes = str_replace(["\r\n", "\r"], "\n", $notes);
        $notes = preg_replace("/\n{3,}/", "\n\n", $notes) ?: $notes;
        $notes = trim($notes);

        if ($notes === '') {
            return '';
        }

        return nl2br(esc_html($notes), false);
    }

    /**
     * Restrict headline highlight styles to the current editor choices.
     *
     * WHY THIS METHOD EXISTS
     * ----------------------
     * The selected value becomes part of a CSS class name in the Blade template.
     * Keeping a strict allowlist prevents invalid classes and keeps the admin
     * editor aligned with the frontend styling options.
     */
    protected function sanitizeHighlightStyle(string $highlightStyle): string
    {
        $highlightStyle = strtolower(trim($highlightStyle));

        $allowed = [
            'club-blue',
            'signal-red',
            'space-city',
            'club-blue-solid',
            'signal-red-solid',
            'deep-purple-solid',
        ];

        return in_array($highlightStyle, $allowed, true) ? $highlightStyle : 'club-blue';
    }
}