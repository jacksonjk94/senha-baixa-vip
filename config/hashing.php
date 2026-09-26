<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | ATENÇÃO (Laravel 12): o HashManager lê `hashing.driver` — e o bootstrap
    | faz merge do config base do framework (vendor) com este arquivo. A chave
    | `driver` PRECISA existir aqui: sem ela vale a do framework, que é
    | `env('HASH_DRIVER')` puro — e var vazia ("") no Dashboard da Vercel
    | quebrava todo login com ArgumentCountError no Manager::createDriver.
    |
    */

    'driver' => env('HASH_DRIVER', 'bcrypt') ?: 'bcrypt',

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12) ?: 12,
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null) ?: null,
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,

];
