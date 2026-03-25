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
        'view_filter' => [
            'betterform',
        ],
        'config_init' => [
            'betterform',
            'summernote',
        ],
        'admin_login_init' => [
            'loginbg',
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
        'app_init' => [
            'socket',
        ],
    ],
    'route' => [],
    'priority' => [],
    'domain' => '',
];
