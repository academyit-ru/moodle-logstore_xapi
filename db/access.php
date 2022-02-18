<?php

$capabilities = [
    'logstore/xapi:view_queue_monitor' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'student' => CAP_PROHIBIT,
        ],
    ],
];
