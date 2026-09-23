<?php

// Vercel: filesystem é read-only, só /tmp permite escrita.
// Garante os diretórios antes do boot (views compiladas, cache, sessões, logs).
if (getenv('VERCEL')) {
    foreach ([
        '/tmp/framework/sessions',
        '/tmp/framework/views',
        '/tmp/framework/cache/data',
        '/tmp/logs',
    ] as $dir) {
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    // Defaults serverless (só aplicam se a var ainda não estiver definida).
    $_SERVER['VIEW_COMPILED_PATH'] ??= '/tmp';
    $_ENV['VIEW_COMPILED_PATH'] ??= '/tmp';
    putenv('VIEW_COMPILED_PATH=/tmp');
}

// Forward Vercel requests to normal index.php
require __DIR__ . '/../public/index.php';