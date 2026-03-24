<?php

return [
    'autoload' => false,
    'hooks' => [
        'response_send' => [
            'apilog',
        ],
        'module_init' => [
            'apilog',
        ],
        'sms_send' => [
            'smsbao',
        ],
        'sms_notice' => [
            'smsbao',
        ],
        'sms_check' => [
            'smsbao',
        ],
    ],
    'route' => [],
    'priority' => [],
    'domain' => '',
];
