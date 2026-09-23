<?php
/**
 * File path + filename: app/Blocks/MembershipCta.php
 *
 * Purpose:
 * - Register a Gutenberg block called "Membership CTA" for use in the blog
 *   sidebar (and anywhere else an editor places it).
 * - Provide ACF fields for all editable copy so the client never needs to
 *   touch code to update the heading, body, button label, or button URL.
 *
 * Why a Block (not a sidebar widget):
 * - The project uses Gutenberg. A Block fits naturally into the widget block
 *   editor used by the sidebar widget area in WordPress 5.8+.
 * - Blocks are portable — this can be placed in any widget area or post
 *   content area without additional registration.
 *
 * Logged-in visibility:
 * - The `with()` method passes `is_logged_in` to the Blade view.
 * - The view returns early (renders nothing) when a user is logged in.
 * - In the editor, $is_preview is true so a placeholder is shown instead,
 *   keeping the block accessible for editing regardless of login state.
 *
 * CSS:
 * - All styles are self-contained in the Blade view via a <style> tag.
 * - No external stylesheet is enqueued.
 *
 * Naming note — why $name = 'Membership Cta' (not 'Membership CTA'):
 * - ACF Composer derives the Blade view path from $name using Str::kebab().
 * - 'Membership CTA' → 'membership-c-t-a' (each uppercase letter = word boundary).
 * - 'Membership Cta' → 'membership-cta' which matches the view file correctly.
 * - The block inserter label is set separately via $label so editors still
 *   see the properly capitalised "Membership CTA" in the UI.
 *
 * View file (auto-resolved from slugified $name):
 *   resources/views/blocks/membership-cta.blade.php
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class MembershipCta extends Block
{
    /**
     * The block name used to derive the view path.
     *
     * MUST be 'Membership Cta' (not 'Membership CTA') so that Str::kebab()
     * produces 'membership-cta', matching the Blade view filename.
     * All-caps acronyms cause Str::kebab() to split each letter individually:
     * 'CTA' → 'c-t-a', producing an unresolvable 'blocks.membership-c-t-a' path.
     *
     * @var string
     */
    public $name = 'Membership Cta';

    /**
     * Human-readable label shown in the Gutenberg block inserter.
     * This is what editors see — correctly capitalised.
     *
     * @var string
     */
    public $label = 'Membership CTA';

    /**
     * Block description shown in the block inserter tooltip.
     *
     * @var string
     */
    public $description = 'A sign-up prompt shown to logged-out visitors. Hidden automatically when the visitor is already a member.';

    /**
     * Block category in the inserter.
     * 'widgets' places it alongside other sidebar-friendly blocks.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Dashicon shown in the block inserter.
     *
     * @var string
     */
    public $icon = 'groups';

    /**
     * Keywords editors can type to find this block in the inserter.
     *
     * @var array
     */
    public $keywords = ['membership', 'join', 'cta', 'sidebar'];

    /**
     * Allow the block to be inserted multiple times per page.
     *
     * @var bool
     */
    public $multiple = true;

    /**
     * Data passed to the Blade view.
     *
     * @return array
     */
    public function with(): array
    {
        return [
            'heading'      => $this->heading(),
            'body'         => $this->body(),
            'button_label' => $this->buttonLabel(),
            'button_url'   => $this->buttonUrl(),
            'is_logged_in' => is_user_logged_in(),
            'is_preview'   => $this->preview,
        ];
    }

    /**
     * The block field group.
     */
    public function fields(): array
    {
        $fields = Builder::make('membership_cta');

        $fields
            ->addText('membership_cta_heading', [
                'label'         => 'Heading',
                'default_value' => 'Not a member yet?',
            ])
            ->addTextarea('membership_cta_body', [
                'label'         => 'Body Copy',
                'rows'          => 3,
                'default_value' => 'Join the Space City Car Club today for exclusive access to events, merch discounts, and our community forum.',
            ])
            ->addText('membership_cta_button_label', [
                'label'         => 'Button Label',
                'default_value' => 'Become a Member',
            ])
            ->addText('membership_cta_button_url', [
                'label'        => 'Button URL',
                'instructions' => 'Link to your membership sign-up or checkout page.',
                'placeholder'  => 'https://example.com/membership-checkout',
            ]);

        return $fields->build();
    }

    /**
     * Return the heading field value with fallback.
     */
    protected function heading(): string
    {
        return get_field('membership_cta_heading') ?: 'Not a member yet?';
    }

    /**
     * Return the body copy field value with fallback.
     */
    protected function body(): string
    {
        return get_field('membership_cta_body') ?: '';
    }

    /**
     * Return the button label field value with fallback.
     */
    protected function buttonLabel(): string
    {
        return get_field('membership_cta_button_label') ?: 'Become a Member';
    }

    /**
     * Return the button URL field value with fallback.
     */
    protected function buttonUrl(): string
    {
        return get_field('membership_cta_button_url') ?: '#';
    }
}