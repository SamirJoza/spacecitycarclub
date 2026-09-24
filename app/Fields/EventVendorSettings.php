<?php

/**
 * File path + filename: app/Fields/EventVendorSettings.php
 *
 * Purpose:
 * - Adds a "Vendors" box to the sidebar of MagePeople events (`mep_events`).
 *
 * Why this file exists:
 * - Only shows that are open to vendors appear in the Vendor Application form.
 * - Each show carries its own default vendor fee (staff can override the fee
 *   per application when approving).
 * - Some venues do not allow outside food vendors. Food vendors who apply to
 *   such a show are flagged in the Vendors list so the reviewer notices.
 */

namespace App\Fields;

use App\Support\Vendors\Vendors;
use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class EventVendorSettings extends Field
{
    public function fields(): array
    {
        $fields = new FieldsBuilder('event_vendor_settings', [
            'title' => 'Vendors',
            'position' => 'side',
            'style' => 'default',
        ]);

        $fields->setLocation('post_type', '==', Vendors::EVENT_POST_TYPE);

        $fields
            ->addTrueFalse(Vendors::EVENT_FIELD_ACCEPTING, [
                'key' => 'field_event_vendors_accepting',
                'label' => 'Accepting vendor applications',
                'instructions' => 'When on, this show is listed in the Vendor Application form (upcoming shows only).',
                'default_value' => 0,
                'ui' => 1,
            ])
            ->addNumber(Vendors::EVENT_FIELD_FEE, [
                'key' => 'field_event_vendor_fee_default',
                'label' => 'Vendor fee',
                'instructions' => 'Default fee charged after approval. Can be overridden per vendor.',
                'prepend' => '$',
                'min' => 0,
                'step' => 0.01,
            ])
                ->conditional(Vendors::EVENT_FIELD_ACCEPTING, '==', '1')
            ->addTrueFalse(Vendors::EVENT_FIELD_FOOD_ALLOWED, [
                'key' => 'field_event_vendors_food_allowed',
                'label' => 'Food vendors allowed',
                'instructions' => 'Turn off if the venue does not allow outside food. Food vendor applications will be flagged.',
                'default_value' => 1,
                'ui' => 1,
            ])
                ->conditional(Vendors::EVENT_FIELD_ACCEPTING, '==', '1');

        return $fields->build();
    }
}
