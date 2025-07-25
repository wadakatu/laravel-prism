<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Documentation Title
    |--------------------------------------------------------------------------
    |
    | The title of your API documentation. This will be displayed at the top
    | of the generated documentation.
    |
    */
    'title' => env('APP_NAME', 'Laravel').' API',

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The version of your API. This will be included in the OpenAPI spec.
    |
    */
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | API Description
    |--------------------------------------------------------------------------
    |
    | A description of your API that will be included in the documentation.
    |
    */
    'description' => 'API documentation generated by Laravel Spectrum',

    /*
    |--------------------------------------------------------------------------
    | Route Patterns
    |--------------------------------------------------------------------------
    |
    | The route patterns that should be included in the documentation.
    | Use wildcards to match multiple routes.
    |
    */
    'route_patterns' => [
        'api/*',
        'api/v1/*',
        'api/v2/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Specific routes that should be excluded from the documentation.
    |
    */
    'excluded_routes' => [
        'api/health',
        'api/ping',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tag Mappings
    |--------------------------------------------------------------------------
    |
    | Custom tag mappings for organizing your API endpoints. You can use
    | exact matches or wildcards (*) to map routes to specific tags.
    |
    */
    'tags' => [
        // Exact match example:
        // 'api/v1/auth/login' => 'Authentication',

        // Wildcard example:
        // 'api/v1/auth/*' => 'Authentication',
        // 'api/v1/admin/*' => 'Administration',
        // 'api/v1/billing/*' => 'Billing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how authentication is detected and documented.
    |
    */
    'authentication' => [
        /*
        | Global authentication settings
        */
        'global' => [
            'enabled' => false,
            'scheme' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Global JWT authentication',
                'name' => 'globalAuth',
            ],
            'required' => false,
        ],

        /*
        | Custom authentication schemes
        | Map middleware names to OpenAPI security schemes
        */
        'custom_schemes' => [
            // 'custom-auth' => [
            //     'type' => 'apiKey',
            //     'in' => 'header',
            //     'name' => 'X-Custom-Token',
            //     'description' => 'Custom API token',
            //     'name' => 'customAuth',
            // ],
        ],

        /*
        | Pattern-based authentication
        | Apply authentication to routes matching patterns
        */
        'patterns' => [
            // 'api/admin/*' => [
            //     'scheme' => [...],
            //     'required' => true,
            // ],
        ],

        /*
        | OAuth2 configuration for Passport
        */
        'oauth2' => [
            'authorization_url' => env('APP_URL').'/oauth/authorize',
            'token_url' => env('APP_URL').'/oauth/token',
            'refresh_url' => env('APP_URL').'/oauth/token',
            'scopes' => [
                // 'read' => 'Read access',
                // 'write' => 'Write access',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the caching behavior for analysis results.
    |
    */
    'cache' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Cache
        |--------------------------------------------------------------------------
        |
        | When enabled, analysis results will be cached to speed up subsequent
        | documentation generation.
        |
        */
        'enabled' => env('SPECTRUM_CACHE_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Cache Directory
        |--------------------------------------------------------------------------
        |
        | The directory where cache files will be stored.
        |
        */
        'directory' => function_exists('storage_path')
            ? storage_path('app/spectrum/cache')
            : getcwd().'/storage/spectrum/cache',

        /*
        |--------------------------------------------------------------------------
        | Cache TTL
        |--------------------------------------------------------------------------
        |
        | Time to live for cache files in seconds. Set to null for no expiration.
        |
        */
        'ttl' => env('SPECTRUM_CACHE_TTL', null),

        /*
        |--------------------------------------------------------------------------
        | Watch Files
        |--------------------------------------------------------------------------
        |
        | Additional files to watch for changes that should invalidate the cache.
        |
        */
        'watch_files' => [
            base_path('composer.json'),
            base_path('composer.lock'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Watch Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the file watching behavior for real-time preview.
    |
    */
    'watch' => [
        /*
        |--------------------------------------------------------------------------
        | File Watching
        |--------------------------------------------------------------------------
        |
        | Configure which directories and files to watch for changes.
        |
        */
        'paths' => [
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            base_path('routes'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Watch Ignore Patterns
        |--------------------------------------------------------------------------
        |
        | Files matching these patterns will be ignored.
        |
        */
        'ignore' => [
            '*.log',
            '*.cache',
            '.git',
            'vendor',
            'node_modules',
        ],

        /*
        |--------------------------------------------------------------------------
        | Debounce Time
        |--------------------------------------------------------------------------
        |
        | Wait this many milliseconds after a file change before regenerating.
        | Useful to avoid multiple regenerations when saving multiple files.
        |
        */
        'debounce' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Analysis
    |--------------------------------------------------------------------------
    |
    | Configure how validation rules are detected and analyzed.
    |
    */
    'validation' => [
        /*
        |--------------------------------------------------------------------------
        | Validation Analysis
        |--------------------------------------------------------------------------
        */
        'analyze_inline' => true, // インラインバリデーションを解析
        'analyze_form_requests' => true, // FormRequestを解析

        /*
        |--------------------------------------------------------------------------
        | Lumen Compatibility
        |--------------------------------------------------------------------------
        */
        'lumen' => [
            'enabled' => env('SPECTRUM_LUMEN_MODE', false),
            'default_middleware' => ['api'], // Lumenのデフォルトミドルウェア
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Transformer Support
    |--------------------------------------------------------------------------
    |
    | Configure support for various response transformation libraries.
    |
    */
    'transformers' => [
        /*
        |--------------------------------------------------------------------------
        | Enabled Transformers
        |--------------------------------------------------------------------------
        |
        | Supported transformers for API response analysis
        |
        */
        'enabled' => [
            'laravel-resource' => true,
            'fractal' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Fractal Settings
        |--------------------------------------------------------------------------
        |
        | Configuration specific to Fractal transformer support
        |
        */
        'fractal' => [
            // Default serializer format
            'default_serializer' => 'array', // array, data_array, json_api

            // How to handle includes in OpenAPI
            'include_handling' => 'optional_properties', // optional_properties or separate_endpoints

            // Default pagination schema for collections
            'pagination_schema' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array'],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'pagination' => [
                                'type' => 'object',
                                'properties' => [
                                    'total' => ['type' => 'integer'],
                                    'count' => ['type' => 'integer'],
                                    'per_page' => ['type' => 'integer'],
                                    'current_page' => ['type' => 'integer'],
                                    'total_pages' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Example Generation
    |--------------------------------------------------------------------------
    |
    | Configure how example values are generated for your API documentation.
    |
    */
    'example_generation' => [
        /*
        |--------------------------------------------------------------------------
        | Use Faker
        |--------------------------------------------------------------------------
        |
        | When enabled, Faker will be used to generate realistic example data.
        | When disabled, static example values will be used.
        |
        */
        'use_faker' => env('SPECTRUM_USE_FAKER', true),

        /*
        |--------------------------------------------------------------------------
        | Faker Locale
        |--------------------------------------------------------------------------
        |
        | The locale to use for Faker data generation. This affects names,
        | addresses, phone numbers, and other locale-specific data.
        |
        */
        'faker_locale' => env('SPECTRUM_FAKER_LOCALE', config('app.faker_locale', 'en_US')),

        /*
        |--------------------------------------------------------------------------
        | Faker Seed
        |--------------------------------------------------------------------------
        |
        | Set a seed value to generate consistent example data across runs.
        | Leave null for random data each time.
        |
        */
        'faker_seed' => env('SPECTRUM_FAKER_SEED', null),

        /*
        |--------------------------------------------------------------------------
        | Custom Field Generators
        |--------------------------------------------------------------------------
        |
        | Define custom generators for specific field names across all resources.
        | The key is the field name, and the value is a callable that receives
        | a Faker instance and returns the example value.
        |
        */
        'custom_generators' => [
            // 'status' => fn($faker) => $faker->randomElement(['active', 'inactive', 'pending']),
            // 'role' => fn($faker) => $faker->randomElement(['admin', 'user', 'guest']),
        ],
    ],
];
