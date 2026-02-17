<?php

return [
    'fake' => [
        'endpoint' => 'https://www.yaseen.dev?param1={param1}&param2={param2}',
        'method' => 'POST',
        'body_format' => 'json',
        'auth' => [
            'type' => 'basic', // basic | digest | token
            // 'token' => env('TOKEN_KEY'),
            'username' => env('API_KEY'),
            'password' => env('API_SECRET')
        ],
        'data' => [
            'key' => 'value',
            'key1' => '{value1}',
            'secret' => env('SECRET_KEY')
        ],
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Custom_Header' => '{value}',
            'secret' => env('SECRET_KEY'),
        ],
    ],
];
