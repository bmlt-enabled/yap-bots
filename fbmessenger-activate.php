<?php

include 'config.php';
include 'functions.php';

$locale = 'default';
$days_submenu = array();
foreach ($GLOBALS['days_of_the_week'] as $day) {
    $days_submenu[] = [
        'title' => $day,
        'type' => 'postback',
        'payload' => [
            'set_day' => $day
        ]
    ];
}

$payload = [
    'get_started' => ['payload' => 'get_started'],
    'greeting' => [
        [
            'locale' => $locale,
            'text' => 'Hello {{user_first_name}}, ' . $GLOBALS['title']
        ]
    ],
    'persistent_menu' => [
        [
        'locale' => $locale,
        'composer_input_disabled' => false,
        'call_to_actions' => [
            [
                'title' => 'Today',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Today'
                ])
            ],
            [
                'title' => 'Tomorrow',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Tomorrow'
                ])
            ],
            [
                'title' => 'Monday',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Monday'
                ])
            ],
            [
                'title' => 'Tuesday',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Tuesday'
                ])
            ],
            [
                'title' => 'Wednesday',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Wednesday'
                ])
            ],
            [
                'title' => 'Thursday',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Thursday'
                ])
            ],
            [
                'title' => 'Friday',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Friday'
                ])
            ],
            [
                'title' => 'Saturday',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Saturday'
                ])
            ],
            [
                'title' => 'Sunday',
                'type' => 'postback',
                'payload' => json_encode([
                    'set_day' => 'Sunday'
                ])
            ],
            [
                'title' => 'Just For Today',
                'type' => 'postback',
                'payload' => 'JFT'
            ],
            [
                'title' => 'Spiritual Principal A Day',
                'type' => 'postback',
                'payload' => 'SPAD'
            ],
            [
                'title' => 'Feature Request/Report Bug',
                'type' => 'web_url',
                'url' => 'https://www.facebook.com/BMLT-656690394722060/',
                'webview_height_ratio' => 'full'
            ]
            ]
        ]
        ]
];

post("https://graph.facebook.com/v5.0/me/messenger_profile?access_token=" . $GLOBALS['fbmessenger_accesstoken'], $payload);
