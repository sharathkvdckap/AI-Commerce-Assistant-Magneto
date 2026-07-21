<?php
return [
    'remote_storage' => [
        'driver' => 'file'
    ],
    'backend' => [
        'frontName' => 'admin'
    ],
    'cache' => [
        'graphql' => [
            'id_salt' => 'wVwaoxpTpsAXM6D8kVD28JLYP7N50h99'
        ],
        'frontend' => [
            'default' => [
                'id_prefix' => 'f41_'
            ],
            'page_cache' => [
                'id_prefix' => 'f41_'
            ]
        ],
        'allow_parallel_generation' => false
    ],
    'config' => [
        'async' => 0
    ],
    'queue' => [
        'consumers_wait_for_messages' => 1
    ],
    'crypt' => [
        'key' => 'base64ZsJ0TCkf3HMM7CZBtdS3JnXuFJIS7OCmCLlgIPjnNWw='
    ],
    'db' => [
        'table_prefix' => '',
        'connection' => [
            'default' => [
                'host' => 'localhost',
                'dbname' => 'magento248p4',
                'username' => 'admin',
                'password' => 'admin',
                'model' => 'mysql4',
                'engine' => 'innodb',
                'initStatements' => 'SET NAMES utf8;',
                'active' => '1',
                'driver_options' => [
                    1014 => false
                ]
            ]
        ]
    ],
    'resource' => [
        'default_setup' => [
            'connection' => 'default'
        ]
    ],
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'default',
    'session' => [
        'save' => 'files'
    ],
    'lock' => [
        'provider' => 'db'
    ],
    'directories' => [
        'document_root_is_pub' => true
    ],
    'cache_types' => [
        'config' => 1,
        'layout' => 1,
        'block_html' => 1,
        'collections' => 1,
        'reflection' => 1,
        'db_ddl' => 1,
        'compiled_config' => 1,
        'eav' => 1,
        'customer_notification' => 1,
        'config_integration' => 1,
        'config_integration_api' => 1,
        'graphql_query_resolver_result' => 1,
        'full_page' => 1,
        'config_webservice' => 1,
        'translate' => 1
    ],
    'downloadable_domains' => [
        'm248p4.local'
    ],
    'install' => [
        'date' => 'Thu, 16 Jul 2026 13:15:43 +0000'
    ],
    'system' => [
        'default' => [
            'imagerecognition' => [
                'general' => [
                    'api_key' => '0:3:P1hj3OZk0p/REYgbT9U5HAZNM598payMgKLkFw4HDw6N1BDLUVfd4QhOk7gGlCMRjfBLbeRKnyVoK5HWYy0bF3ijv9OGlN2MVVmpUA=='
                ]
            ]
        ]
    ]
];
