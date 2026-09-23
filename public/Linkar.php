<?php

// forçar a exibir erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$basePath = realpath(__DIR__ . '/..'); // sobe um nível (para sair da public/)
$target = $basePath . '/storage/app/public';
$link = __DIR__ . '/storage';

if (!is_dir($target)) {
    echo "O diretório de origem não existe: $target\n";
    exit;
}

if (is_link($link) || file_exists($link)) {
    echo "O link simbólico já existe: $link\n";
} else {
    if (symlink($target, $link)) {
        echo "Link simbólico criado com sucesso: $link → $target\n";
    } else {
        echo "Erro ao criar o link simbólico.\n";
    }
}
