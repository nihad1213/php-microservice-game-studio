<?php

$path = getenv('SQLITE_PATH') ?: __DIR__ . '/storage/sales.sqlite';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'app',
        'app' => [
            'adapter' => 'sqlite',
            'name'    => preg_replace('/\.sqlite$/', '', $path),
            'suffix'  => '.sqlite',
        ],
    ],
];