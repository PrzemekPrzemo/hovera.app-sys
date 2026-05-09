<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'time_type' => 'Time and type',
            'resources' => 'Resources',
            'details' => 'Details',
            'participants' => 'Group lesson participants',
            'participants_description' => 'Each participant = client + optional horse. After the lesson, mark attendance per participant.',
        ],
        'label' => [
            'type' => 'Type',
            'starts_at' => 'Starts',
            'ends_at' => 'Ends',
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'arena' => 'Arena',
            'client' => 'Client',
            'title' => 'Title (for events / blocks)',
            'status' => 'Status',
            'price' => 'Price',
            'notes' => 'Notes',
            'participants' => 'Participants',
            'participant_client' => 'Client',
            'participant_horse' => 'Horse (optional)',
            'participant_horse_placeholder' => '— riding own horse / assign later —',
            'participant_attendance' => 'Attendance',
            'participant_notes' => 'Notes (e.g. "first lesson")',
        ],
    ],

    'attendance' => [
        'expected' => 'Expected',
        'present' => 'Present',
        'absent' => 'Absent',
        'late' => 'Late',
    ],

    'actions' => [
        'add_participant' => '+ Add participant',
    ],

    'table' => [
        'column' => [
            'starts_at' => 'Starts',
            'ends_at' => 'Ends',
            'type' => 'Type',
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'arena' => 'Arena',
            'client' => 'Client',
            'status' => 'Status',
        ],
        'participant_count' => '{0} no participants|{1} 👥 :count participant|[2,*] 👥 :count participants',
        'filter' => [
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'upcoming' => 'Only upcoming',
        ],
    ],
];
