<?php

namespace App\Fields;

use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class Minutes extends Field
{
    public function fields(): array
    {
        /**
         * Sidebar: Meeting Type radio (single select)
         */
        $sidebar = new FieldsBuilder('minutes_sidebar', [
            'title'    => 'Minutes (Sidebar)',
            'position' => 'side',
        ]);

        $sidebar->setLocation('post_type', '==', 'minutes');

        $sidebar
            ->addRadio('meeting_type_select', [
                'label'         => 'Meeting Type',
                'choices'       => [
                    'board'      => 'Board Meeting',
                    'committee'  => 'Committee Meeting',
                    'membership' => 'Membership Meeting',
                ],
                'layout'        => 'vertical',
                'return_format' => 'value',
                'required'      => 1,
                'instructions'  => 'This controls how minutes are grouped on the archive.',
            ]);

        /**
         * Main: Minutes content fields
         */
        $main = new FieldsBuilder('minutes_main', [
            'title' => 'Minutes (Details)',
        ]);

        $main->setLocation('post_type', '==', 'minutes');

        // OVERVIEW
        $main
            ->addTab('Overview')
            ->addGroup('meeting_details', [
                'label'  => 'Meeting Details',
                'layout' => 'block',
            ])
                ->addDatePicker('meeting_date', [
                    'label'          => 'Meeting Date',
                    'required'       => 1,
                    'display_format' => 'F j, Y',
                    'return_format'  => 'Y-m-d',
                ])
                ->addText('meeting_location', [
                    'label' => 'Location',
                ])
            ->endGroup()
            ->addTextarea('recap_summary', [
                'label'        => 'Recap Summary',
                'instructions' => 'Short, high-level recap (a paragraph or two).',
                'rows'         => 4,
                'new_lines'    => 'br',
            ]);

        // ATTENDANCE
        $main
            ->addTab('Attendance')
            
            ->addNumber('attendance_count', [
                'label'        => 'Membership Attendance',
                'min'          => 0,
                'append'       => 'attendees',
                'instructions' => 'For Membership meetings, enter a total count (shown as “xx attendees”).',
            ])
            ->addRepeater('attendees', [
                'label'        => 'Attendees (Board / Committee)',
                'button_label' => 'Add Attendee',
                'layout'       => 'row',
                'min'          => 0,
                'instructions' => 'For Board/Committee meetings, add attendee names and optional titles.',
            ])
                ->addText('name', [
                    'label'    => 'Name',
                    'required' => 1,
                ])
                ->addText('role', [
                    'label'        => 'Title (optional)',
                    'instructions' => 'Example: Treasurer, Events Lead, Safety Coordinator',
                ])
            ->endRepeater();

        // DECISIONS
        $main
            ->addTab('Decisions')
            ->addRepeater('motions', [
                'label'        => 'Motions / Decisions',
                'button_label' => 'Add Motion / Decision',
                'layout'       => 'block',
                'min'          => 0,
            ])
                ->addTextarea('motion_text', [
                    'label'    => 'Motion / Decision',
                    'rows'     => 3,
                    'required' => 1,
                ])
                ->addSelect('outcome', [
                    'label'         => 'Outcome',
                    'choices'       => [
                        'info'     => 'Info / Discussed',
                        'tabled'   => 'Tabled',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ],
                    'default_value' => 'info',
                    'ui'            => 1,
                ])
                ->addTextarea('notes', [
                    'label'     => 'Notes (optional)',
                    'rows'      => 3,
                    'new_lines' => 'br',
                ])
            ->endRepeater();

        // ACTION ITEMS
        $main
            ->addTab('Action Items')
            ->addRepeater('action_items', [
                'label'        => 'Action Items',
                'button_label' => 'Add Action Item',
                'layout'       => 'row',
                'min'          => 0,
            ])
                ->addText('task', [
                    'label'    => 'Task',
                    'required' => 1,
                ])
                ->addText('owner', [
                    'label' => 'Owner (text)',
                ])
                ->addDatePicker('due_date', [
                    'label'          => 'Due Date',
                    'display_format' => 'F j, Y',
                    'return_format'  => 'Y-m-d',
                ])
                ->addSelect('status', [
                    'label'         => 'Status',
                    'choices'       => [
                        'open'        => 'Open',
                        'in_progress' => 'In Progress',
                        'done'        => 'Done',
                    ],
                    'default_value' => 'open',
                    'ui'            => 1,
                ])
            ->endRepeater();

        // ATTACHMENTS
        $main
            ->addTab('Attachments')
            ->addFile('official_pdf', [
                'label'         => 'Official PDF (optional)',
                'return_format' => 'array',
                'mime_types'    => 'pdf',
            ])
            ->addRepeater('attachments', [
                'label'        => 'Additional Attachments',
                'button_label' => 'Add Attachment',
                'layout'       => 'row',
                'min'          => 0,
            ])
                ->addFile('file', [
                    'label'         => 'File',
                    'return_format' => 'array',
                ])
                ->addText('label', [
                    'label' => 'Label (optional)',
                ])
            ->endRepeater();

        // Return BOTH groups
        return [
            $sidebar->build(),
            $main->build(),
        ];
    }
}
