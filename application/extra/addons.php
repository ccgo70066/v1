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
        'app_init' => [
            'reset',
            'socket',
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
