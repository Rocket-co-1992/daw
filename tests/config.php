<?php

// Configurações de teste para PHPUnit
return [
    'database' => [
        'default' => 'sqlite',
        'connections' => [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'mysql_test' => [
                'driver' => 'mysql',
                'host' => env('DB_TEST_HOST', '127.0.0.1'),
                'port' => env('DB_TEST_PORT', '3306'),
                'database' => env('DB_TEST_DATABASE', 'daw_test'),
                'username' => env('DB_TEST_USERNAME', 'root'),
                'password' => env('DB_TEST_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
            ]
        ]
    ],
    
    'jwt' => [
        'secret' => 'test_secret_key_for_phpunit_tests_only',
        'algorithm' => 'HS256',
        'expiration' => 3600,
    ],
    
    'bcrypt' => [
        'cost' => 10,
    ],
    
    'websocket' => [
        'test_url' => 'ws://localhost:8080',
        'connection_timeout' => 5,
        'max_connections' => 5,
    ],
    
    'api' => [
        'base_url' => 'http://localhost:8000',
        'timeout' => 10,
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 100,
            'window' => 60, // segundos
        ]
    ],
    
    'files' => [
        'upload_path' => sys_get_temp_dir() . '/daw_test_uploads',
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'allowed_extensions' => ['wav', 'mp3', 'flac', 'aiff'],
    ],
    
    'logging' => [
        'level' => 'debug',
        'file' => sys_get_temp_dir() . '/daw_test.log',
    ],
    
    'cache' => [
        'driver' => 'array', // Para testes usar cache em memória
    ],
    
    'session' => [
        'driver' => 'array',
    ]
];
