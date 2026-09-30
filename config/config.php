<?php

return [
    'app' => [
        'name'  => 'RetroApp',
        'debug' => false,
        'url'   => '',
    ],
    'paths' => [
        'base'     => dirname(__DIR__),
        'storage'  => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage',
        'uploads'  => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads',
        'exports'  => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports',
        'database' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'retro.sqlite',
        'schema'   => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
        'env'      => dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
    ],
    'upload' => [
        'max_bytes'  => 100 * 1024 * 1024,
        'extensions' => ['xlsx', 'csv', 'zip'],
        'archive_extensions' => ['zip'],
        'attachment_extensions' => [
            'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
        ],
    ],
];
