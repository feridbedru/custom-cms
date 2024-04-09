<?php

$config = [
    'DB_NAME'           => "custom_cms",
    'DB_USER'           => "root",
    'DB_PASSWORD'       => "toor",
    'DB_HOST'           => "localhost",
    'MAX_RESOURCE'      => "10",
    'TITLE_LENGTH'      => "255",
    'CONTENT_LENGTH'    => "16777215",
    'FILE_SIZE'         => "123000",
    'FILE_TYPE'         => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/rtf',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/x-ms-wmv',
        'video/x-flv',
        'video/webm',
    ]
];

return $config;
