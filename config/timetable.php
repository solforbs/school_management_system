<?php

/*
|--------------------------------------------------------------------------
| Timetable generator settings
|--------------------------------------------------------------------------
|
| Everything the generator and its pre-flight checks need to know that is
| not stored per school. Keyword lists are matched case-insensitively on
| WHOLE WORDS, so "pe" does not match "Special Needs". A trailing "*" means
| "any word starting with", e.g. "math*" matches Mathematics.
|
| A school's own SubjectAllocation rows always beat these defaults.
*/

return [

    // A teacher may not be given more than this many lessons back-to-back.
    // A break always resets the run. Set to null to disable the rule.
    'max_consecutive_lessons' => (int) env('TIMETABLE_MAX_CONSECUTIVE', 3),

    // Maximum lessons per teacher per day. null means no daily cap.
    'max_daily_lessons' => env('TIMETABLE_MAX_DAILY') !== null ? (int) env('TIMETABLE_MAX_DAILY') : null,

    // Warn when a teacher's required lessons reach this share of the week's slots.
    'teacher_high_load_ratio' => (float) env('TIMETABLE_HIGH_LOAD_RATIO', 0.85),

    // Used only when a subject has no allocation and matches no keyword tier.
    'fallback_periods_per_week' => 3,
    'fallback_cognitive_weight' => 'medium',

    // A subject is only offered double lessons from this many periods a week.
    'double_min_periods' => 4,

    // How many double-lesson blocks a double-eligible subject gets per week.
    'double_blocks_per_subject' => 1,

    'weights' => [
        'high' => [
            'math*', 'english', 'kiswahili', 'science*', 'physics', 'chemistry',
            'biology', 'algebra', 'geometry',
        ],
        'low' => [
            'physical education', 'physical and health education', 'phe', 'pe', 'p.e',
            'games', 'creative art*', 'art and craft', 'art', 'music', 'drama',
            'clubs', 'pastoral', 'life skills',
        ],
    ],

    // First matching tier wins, checked from the top.
    'default_periods' => [
        5 => ['math*', 'english'],
        4 => ['kiswahili', 'science*', 'integrated science', 'biology', 'chemistry', 'physics'],
        3 => ['social studies', 'cre', 'ire', 'agriculture', 'pre-technical', 'business*'],
        2 => [
            'physical education', 'physical and health education', 'phe', 'pe',
            'creative art*', 'music', 'art',
        ],
    ],

    // Subjects that suit a double lesson (labs, practicals) when allowed.
    'double_lesson_subjects' => [
        'math*', 'physics', 'chemistry', 'biology', 'science*', 'integrated science',
        'agriculture', 'computer*', 'creative art*',
    ],
];
