<?php

return [

    'default' => env('HASH_DRIVER', 'bcrypt') ?: 'bcrypt',

    'drivers' => [
        'bcrypt' => [
            'rounds' => (int) env('BCRYPT_ROUNDS', 10) ?: 10,
        ],

        'argon' => [
            'memory' => 65536,
            'threads' => 4,
            'time' => 4,
        ],

        'argon2id' => [
            'memory' => 65536,
            'threads' => 4,
            'time' => 4,
        ],
    ],
];
