<?php

/**
 * File: app/Fields/ThankYou.php
 * Purpose: Thank You CPT — visual variation picker for the public hero template.
 */

namespace App\Fields;

use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class ThankYou extends Field
{
    public function fields(): array
    {
        $fields = new FieldsBuilder('thank_you_settings', [
            'title' => 'Thank You page',
            'position' => 'side',
            'style' => 'default',
        ]);

        $fields->setLocation('post_type', '==', 'thank_you');

        $fields
            ->addSelect('thank_you_visual_variation', [
                'label' => 'Visual style',
                'instructions' => 'Controls hero accents, background tint, and headline gradient. The post title is for the admin list only; visitors always see “Thank you”.',
                'choices' => [
                    'nebula' => 'Nebula — classic club blue glow',
                    'aurora' => 'Aurora — teal, violet, and mint',
                    'signal' => 'Signal — blue into racing red / amber',
                ],
                'default_value' => 'nebula',
                'return_format' => 'value',
                'ui' => 1,
                'required' => 0,
            ]);

        return $fields->build();
    }
}
