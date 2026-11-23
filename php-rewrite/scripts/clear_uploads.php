<?php

/**
 * Удаляет все файлы под public/uploads (вложения, согласия, временные выгрузки и т.д.).
 * Сохраняет структуру каталогов и файлы с именем .gitkeep при наличии.
 *
 * Запуск из корня проекта:
 *   php scripts/clear_uploads.php
 */

$root = dirname(__DIR__);
$base = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';

if (!is_dir($base)) {
    echo "Каталог uploads отсутствует, создавать не требуется.\n";
    exit(0);
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

$n = 0;
foreach ($it as $item) {
    $name = $item->getFilename();
    if ($name === '.gitkeep') {
        continue;
    }
    if ($item->isDir()) {
        @rmdir($item->getPathname());
    } else {
        if (@unlink($item->getPathname())) {
            $n++;
        }
    }
}

echo "Удалено файлов: {$n}\n";
