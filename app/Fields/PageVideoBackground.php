<?php

/**
 * File: app/Fields/PageVideoBackground.php
 * Purpose: Adds media-library controls for the Video Background Page template.
 */

namespace App\Fields;

use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class PageVideoBackground extends Field
{
    /**
     * The field group.
     */
    public function fields(): array
    {
        $fields = new FieldsBuilder('sccc_page_video_background', [
            'title' => 'Page Video Background',
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => [],
        ]);

        $fields
            ->setLocation('page_template', '==', 'template-video-background.blade.php');

        $fields
            ->addFile('sccc_page_background_video', [
                'label' => 'Background Video',
                'instructions' => 'Choose an MP4 or WebM video from the Media Library. For autoplay to work reliably, the video will be rendered muted.',
                'required' => 0,
                'return_format' => 'array',
                'library' => 'all',
                'mime_types' => 'mp4,webm',
            ])

            ->addImage('sccc_page_background_video_poster', [
                'label' => 'Fallback / Poster Image',
                'instructions' => 'Optional. Used before the video loads, and as a fallback for reduced-motion users.',
                'required' => 0,
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
            ])

            ->addRange('sccc_page_background_video_overlay_opacity', [
                'label' => 'Overlay Opacity',
                'instructions' => 'Controls the dark overlay above the video so page content stays readable.',
                'required' => 0,
                'default_value' => 45,
                'min' => 0,
                'max' => 90,
                'step' => 5,
                'prepend' => '',
                'append' => '%',
            ]);

        return $fields->build();
    }
}