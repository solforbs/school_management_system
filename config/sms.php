<?php

return [
    'default' => 'hostpinnacle',

    'providers' => [
        'hostpinnacle' => [
            'url' => env('PINNACLE_API_URL', 'https://smsportal.hostpinnacle.co.ke/SMSApi/send'),
            'userid' => env('PINNACLE_USER_ID'),
            'password' => env('PINNACLE_API_PASSWORD'),
            'senderid' => env('PINNACLE_SENDER_ID'),
            'apikey' => env('PINNACLE_API_KEY'),
            'default_msg_type' => env('PINNACLE_MESSAGE_TYPE'),
            'default_duplicate_check' => true,
            'default_output' => env('PINNACLE_RESPONSE'),
            'default_send_method' => env('PINNACLE_SEND_METHOD'),
        ],
    ],

    'options' => [
        'timeout' => 30,
        'max_redirects' => 10,
    ],
];
