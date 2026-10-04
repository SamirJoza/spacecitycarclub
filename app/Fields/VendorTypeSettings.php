<?php

/**
 * File path + filename: app/Fields/VendorTypeSettings.php
 *
 * Purpose:
 * - Adds a color picker to Vendor Types (Vendors → Vendor Types).
 *
 * Why this file exists:
 * - Vendor types are color coded in the Vendors admin list (pill + row
 *   accent) and in the Featured Vendors block. Defaults are seeded in
 *   App\Support\Vendors\Vendors::defaultTypes(); change them here per type.
 */

namespace App\Fields;

use App\Support\Vendors\Vendors;
use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class VendorTypeSettings extends Field
{
    public function fields(): array
    {
        $fields = new FieldsBuilder('vendor_type_settings', [
            'title' => 'Vendor type',
        ]);

        $fields->setLocation('taxonomy', '==', Vendors::TAXONOMY_TYPE);

        $fields->addColorPicker('vendor_type_color', [
            'key' => 'field_vendor_type_color',
            'label' => 'Color',
            'instructions' => 'Used for this type in the Vendors list and the Featured Vendors block.',
        ]);

        return $fields->build();
    }
}
