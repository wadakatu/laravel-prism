---
id: config-reference
title: 設定リファレンス
sidebar_label: 設定リファレンス
---

# 設定リファレンス

`config/spectrum.php`ファイルの全設定項目の詳細なリファレンスです。

## 📋 設定ファイルの公開

```bash
php artisan vendor:publish --provider="LaravelSpectrum\SpectrumServiceProvider" --tag="config"
```

## 🔧 基本設定

### API情報

```php
/*
|--------------------------------------------------------------------------
| API Documentation Title
|--------------------------------------------------------------------------
|
| APIドキュメントのタイトル。ドキュメントの上部に表示されます。
|
*/
'title' => env('APP_NAME', 'Laravel') . ' API',

/*
|--------------------------------------------------------------------------
| API Version
|--------------------------------------------------------------------------
|
| APIのバージョン。OpenAPI仕様に含まれます。
|
*/
'version' => '1.0.0',

/*
|--------------------------------------------------------------------------
| API Description
|--------------------------------------------------------------------------
|
| APIの説明文。Markdown形式で記述可能です。
|
*/
'description' => 'API documentation generated by Laravel Spectrum',

/*
|--------------------------------------------------------------------------
| Terms of Service URL
|--------------------------------------------------------------------------
|
| 利用規約のURL（オプション）。
|
*/
'terms_of_service' => 'https://example.com/terms',

/*
|--------------------------------------------------------------------------
| Contact Information
|--------------------------------------------------------------------------
|
| API提供者の連絡先情報。
|
*/
'contact' => [
    'name' => 'API Support',
    'email' => 'api@example.com',
    'url' => 'https://example.com/support',
],

/*
|--------------------------------------------------------------------------
| License Information
|--------------------------------------------------------------------------
|
| APIのライセンス情報。
|
*/
'license' => [
    'name' => 'MIT',
    'url' => 'https://opensource.org/licenses/MIT',
],
```

### サーバー設定

```php
/*
|--------------------------------------------------------------------------
| Servers
|--------------------------------------------------------------------------
|
| APIサーバーのリスト。複数の環境を定義できます。
|
*/
'servers' => [
    [
        'url' => env('APP_URL', 'http://localhost'),
        'description' => 'Development server',
    ],
    [
        'url' => 'https://staging.example.com',
        'description' => 'Staging server',
    ],
    [
        'url' => 'https://api.example.com',
        'description' => 'Production server',
    ],
],
```

## 📂 ルート設定

```php
/*
|--------------------------------------------------------------------------
| Route Patterns
|--------------------------------------------------------------------------
|
| ドキュメントに含めるルートのパターン。ワイルドカード（*）を使用できます。
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
| ドキュメントから除外する特定のルート。
|
*/
'excluded_routes' => [
    'api/health',
    'api/ping',
    'api/debug/*',
    '_debugbar/*',
    'telescope/*',
    'horizon/*',
],

/*
|--------------------------------------------------------------------------
| Route Metadata
|--------------------------------------------------------------------------
|
| ルートに追加のメタデータを設定。
|
*/
'route_metadata' => [
    'api/admin/*' => [
        'deprecated' => true,
        'x-internal' => true,
    ],
],
```

## 🏷️ タグ設定

```php
/*
|--------------------------------------------------------------------------
| Tag Mappings
|--------------------------------------------------------------------------
|
| ルートをグループ化するためのタグマッピング。
| 完全一致またはワイルドカード（*）を使用できます。
|
*/
'tags' => [
    // 完全一致
    'api/v1/auth/login' => 'Authentication',
    'api/v1/auth/logout' => 'Authentication',
    'api/v1/auth/refresh' => 'Authentication',
    
    // ワイルドカード
    'api/v1/users/*' => 'User Management',
    'api/v1/posts/*' => 'Blog Posts',
    'api/v1/admin/*' => 'Administration',
    'api/v1/reports/*' => 'Reports',
],

/*
|--------------------------------------------------------------------------
| Tag Descriptions
|--------------------------------------------------------------------------
|
| 各タグの説明。
|
*/
'tag_descriptions' => [
    'Authentication' => 'エンドポイントの認証と認可',
    'User Management' => 'ユーザーの作成、読み取り、更新、削除',
    'Blog Posts' => 'ブログ投稿の管理',
    'Administration' => '管理者専用エンドポイント',
],

/*
|--------------------------------------------------------------------------
| Tag Order
|--------------------------------------------------------------------------
|
| タグの表示順序。リストに含まれないタグはアルファベット順に表示。
|
*/
'tag_order' => [
    'Authentication',
    'User Management',
    'Blog Posts',
    'Administration',
],
```

## 🔐 認証設定

```php
/*
|--------------------------------------------------------------------------
| Authentication Configuration
|--------------------------------------------------------------------------
|
| APIの認証方式の設定。
|
*/
'authentication' => [
    /*
    |--------------------------------------------------------------------------
    | Default Authentication
    |--------------------------------------------------------------------------
    |
    | デフォルトの認証方式。
    |
    */
    'default' => 'bearer',
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Flows
    |--------------------------------------------------------------------------
    |
    | 利用可能な認証フローの定義。
    |
    */
    'flows' => [
        'bearer' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'JWT Bearer Token認証',
        ],
        
        'apiKey' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key',
            'description' => 'APIキー認証',
        ],
        
        'basic' => [
            'type' => 'http',
            'scheme' => 'basic',
            'description' => 'Basic認証',
        ],
        
        'oauth2' => [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => [
                    'authorizationUrl' => '/oauth/authorize',
                    'tokenUrl' => '/oauth/token',
                    'refreshUrl' => '/oauth/token/refresh',
                    'scopes' => [
                        'read' => '読み取り権限',
                        'write' => '書き込み権限',
                        'delete' => '削除権限',
                    ],
                ],
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Middleware Mapping
    |--------------------------------------------------------------------------
    |
    | Laravelミドルウェアと認証フローのマッピング。
    |
    */
    'middleware_map' => [
        'auth' => 'bearer',
        'auth:api' => 'bearer',
        'auth:sanctum' => 'bearer',
        'auth:passport' => 'oauth2',
        'auth.basic' => 'basic',
        'api-key' => 'apiKey',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Exclude Patterns
    |--------------------------------------------------------------------------
    |
    | 認証が不要なルートパターン。
    |
    */
    'exclude_patterns' => [
        'api/health',
        'api/status',
        'api/auth/login',
        'api/auth/register',
        'api/password/forgot',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Global Security
    |--------------------------------------------------------------------------
    |
    | すべてのエンドポイントにグローバルセキュリティを適用するか。
    |
    */
    'global_security' => false,
],
```

## 🎨 例データ生成

```php
/*
|--------------------------------------------------------------------------
| Example Generation
|--------------------------------------------------------------------------
|
| リクエスト/レスポンス例の生成設定。
|
*/
'example_generation' => [
    /*
    |--------------------------------------------------------------------------
    | Use Faker
    |--------------------------------------------------------------------------
    |
    | Fakerを使用してリアルな例データを生成するか。
    |
    */
    'use_faker' => env('SPECTRUM_USE_FAKER', true),
    
    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | Fakerのロケール設定。
    |
    */
    'faker_locale' => env('SPECTRUM_FAKER_LOCALE', 'ja_JP'),
    
    /*
    |--------------------------------------------------------------------------
    | Faker Seed
    |--------------------------------------------------------------------------
    |
    | 一貫した例データを生成するためのシード値。
    |
    */
    'faker_seed' => env('SPECTRUM_FAKER_SEED', null),
    
    /*
    |--------------------------------------------------------------------------
    | Custom Field Generators
    |--------------------------------------------------------------------------
    |
    | 特定のフィールド名に対するカスタムジェネレーター。
    |
    */
    'custom_generators' => [
        'email' => fn($faker) => $faker->safeEmail(),
        'phone' => fn($faker) => $faker->phoneNumber(),
        'avatar_url' => fn($faker) => $faker->imageUrl(200, 200, 'people'),
        'price' => fn($faker) => $faker->randomFloat(2, 100, 10000),
        'status' => fn($faker) => $faker->randomElement(['active', 'inactive', 'pending']),
        'role' => fn($faker) => $faker->randomElement(['admin', 'user', 'guest']),
        'country_code' => fn($faker) => $faker->countryCode(),
        'currency' => fn($faker) => $faker->currencyCode(),
        'latitude' => fn($faker) => $faker->latitude(),
        'longitude' => fn($faker) => $faker->longitude(),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Array Limits
    |--------------------------------------------------------------------------
    |
    | 配列の例で生成する要素数。
    |
    */
    'array_limits' => [
        'min' => 1,
        'max' => 3,
    ],
],
```

## ⚡ パフォーマンス設定

```php
/*
|--------------------------------------------------------------------------
| Performance Configuration
|--------------------------------------------------------------------------
|
| パフォーマンス最適化の設定。
|
*/
'performance' => [
    /*
    |--------------------------------------------------------------------------
    | Enable Optimization
    |--------------------------------------------------------------------------
    |
    | パフォーマンス最適化を有効にするか。
    |
    */
    'enabled' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Parallel Processing
    |--------------------------------------------------------------------------
    |
    | 並列処理の設定。
    |
    */
    'parallel_processing' => true,
    'workers' => env('SPECTRUM_WORKERS', 'auto'), // 'auto'でCPUコア数
    'chunk_size' => env('SPECTRUM_CHUNK_SIZE', 100),
    'memory_limit' => env('SPECTRUM_MEMORY_LIMIT', '512M'),
    
    /*
    |--------------------------------------------------------------------------
    | Analysis Optimization
    |--------------------------------------------------------------------------
    |
    | 解析の最適化設定。
    |
    */
    'analysis' => [
        'max_depth' => 3,
        'skip_vendor' => true,
        'lazy_loading' => true,
        'use_cache' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Resource Limits
    |--------------------------------------------------------------------------
    |
    | リソース制限。
    |
    */
    'limits' => [
        'max_routes' => 10000,
        'max_file_size' => '50M',
        'timeout' => 300,
        'max_schema_depth' => 10,
    ],
],
```

## 💾 キャッシュ設定

```php
/*
|--------------------------------------------------------------------------
| Cache Configuration
|--------------------------------------------------------------------------
|
| キャッシュの設定。
|
*/
'cache' => [
    /*
    |--------------------------------------------------------------------------
    | Enable Cache
    |--------------------------------------------------------------------------
    |
    | キャッシュを有効にするか。
    |
    */
    'enabled' => env('SPECTRUM_CACHE_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | 使用するキャッシュストア。
    |
    */
    'store' => env('SPECTRUM_CACHE_STORE', 'file'),
    
    /*
    |--------------------------------------------------------------------------
    | Cache Directory
    |--------------------------------------------------------------------------
    |
    | ファイルキャッシュのディレクトリ。
    |
    */
    'directory' => storage_path('app/spectrum/cache'),
    
    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | キャッシュの有効期限（秒）。nullで無期限。
    |
    */
    'ttl' => env('SPECTRUM_CACHE_TTL', null),
    
    /*
    |--------------------------------------------------------------------------
    | Cache Segments
    |--------------------------------------------------------------------------
    |
    | セグメント別のキャッシュ設定。
    |
    */
    'segments' => [
        'routes' => ['ttl' => 86400], // 24時間
        'schemas' => ['ttl' => 3600], // 1時間
        'examples' => ['ttl' => 7200], // 2時間
        'analysis' => ['ttl' => 3600], // 1時間
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Watch Files
    |--------------------------------------------------------------------------
    |
    | 変更を監視してキャッシュを無効化するファイル。
    |
    */
    'watch_files' => [
        base_path('composer.json'),
        base_path('composer.lock'),
        config_path('spectrum.php'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Smart Invalidation
    |--------------------------------------------------------------------------
    |
    | スマートキャッシュ無効化を有効にするか。
    |
    */
    'smart_invalidation' => true,
],
```

## 👁️ Watch設定

```php
/*
|--------------------------------------------------------------------------
| Watch Configuration
|--------------------------------------------------------------------------
|
| ファイル監視の設定。
|
*/
'watch' => [
    /*
    |--------------------------------------------------------------------------
    | Watch Paths
    |--------------------------------------------------------------------------
    |
    | 監視するディレクトリとファイル。
    |
    */
    'paths' => [
        app_path('Http/Controllers'),
        app_path('Http/Requests'),
        app_path('Http/Resources'),
        base_path('routes'),
        config_path(),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Ignore Patterns
    |--------------------------------------------------------------------------
    |
    | 無視するファイルパターン。
    |
    */
    'ignore_patterns' => [
        '*.log',
        '*.cache',
        '.DS_Store',
        'Thumbs.db',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    |
    | ポーリングの設定（Docker環境など）。
    |
    */
    'polling' => [
        'enabled' => env('SPECTRUM_WATCH_POLL', false),
        'interval' => 1000, // ミリ秒
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | プレビューサーバーの設定。
    |
    */
    'server' => [
        'host' => 'localhost',
        'port' => 8080,
        'auto_open' => true,
    ],
],
```

## 📤 出力設定

```php
/*
|--------------------------------------------------------------------------
| Output Configuration
|--------------------------------------------------------------------------
|
| 出力ファイルの設定。
|
*/
'output' => [
    /*
    |--------------------------------------------------------------------------
    | OpenAPI Output
    |--------------------------------------------------------------------------
    |
    | OpenAPIドキュメントの出力先。
    |
    */
    'openapi' => [
        'path' => storage_path('app/spectrum/openapi.json'),
        'format' => 'json', // 'json' または 'yaml'
        'pretty_print' => true,
        'version' => '3.0.0',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Export Paths
    |--------------------------------------------------------------------------
    |
    | エクスポートファイルの出力先。
    |
    */
    'exports' => [
        'postman' => storage_path('app/spectrum/postman/'),
        'insomnia' => storage_path('app/spectrum/insomnia/'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Include Options
    |--------------------------------------------------------------------------
    |
    | 出力に含める要素。
    |
    */
    'include' => [
        'examples' => true,
        'descriptions' => true,
        'deprecated' => true,
        'x-properties' => true,
    ],
],
```

## 🛡️ エラーハンドリング

```php
/*
|--------------------------------------------------------------------------
| Error Handling
|--------------------------------------------------------------------------
|
| エラーハンドリングの設定。
|
*/
'error_handling' => [
    /*
    |--------------------------------------------------------------------------
    | Fail on Error
    |--------------------------------------------------------------------------
    |
    | エラー時に処理を中断するか。
    |
    */
    'fail_on_error' => false,
    
    /*
    |--------------------------------------------------------------------------
    | Error Reporting
    |--------------------------------------------------------------------------
    |
    | エラーレポートのレベル。
    |
    */
    'reporting' => [
        'level' => 'warning', // 'error', 'warning', 'notice'
        'log_channel' => 'spectrum',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Error Recovery
    |--------------------------------------------------------------------------
    |
    | エラーからの回復設定。
    |
    */
    'recovery' => [
        'continue_on_parse_error' => true,
        'use_fallback_types' => true,
        'skip_invalid_routes' => true,
    ],
],
```

## 🎯 高度な設定

```php
/*
|--------------------------------------------------------------------------
| Advanced Configuration
|--------------------------------------------------------------------------
|
| 高度な設定オプション。
|
*/
'advanced' => [
    /*
    |--------------------------------------------------------------------------
    | Custom Analyzers
    |--------------------------------------------------------------------------
    |
    | カスタムアナライザーの登録。
    |
    */
    'analyzers' => [
        // 'custom' => \App\Spectrum\Analyzers\CustomAnalyzer::class,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Custom Generators
    |--------------------------------------------------------------------------
    |
    | カスタムジェネレーターの登録。
    |
    */
    'generators' => [
        // 'custom' => \App\Spectrum\Generators\CustomGenerator::class,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Hooks
    |--------------------------------------------------------------------------
    |
    | フックポイントの設定。
    |
    */
    'hooks' => [
        'before_analysis' => [],
        'after_analysis' => [],
        'before_generation' => [],
        'after_generation' => [],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    | OpenAPI拡張の設定。
    |
    */
    'extensions' => [
        'x-logo' => [
            'url' => '/logo.png',
            'altText' => 'API Logo',
        ],
        'x-documentation' => 'https://docs.example.com',
    ],
],
```

## 💡 環境変数

主要な設定は環境変数でオーバーライドできます：

```env
# 基本設定
SPECTRUM_USE_FAKER=true
SPECTRUM_FAKER_LOCALE=ja_JP
SPECTRUM_FAKER_SEED=12345

# パフォーマンス
SPECTRUM_WORKERS=8
SPECTRUM_CHUNK_SIZE=100
SPECTRUM_MEMORY_LIMIT=1G

# キャッシュ
SPECTRUM_CACHE_ENABLED=true
SPECTRUM_CACHE_STORE=redis
SPECTRUM_CACHE_TTL=3600

# Watch
SPECTRUM_WATCH_POLL=true
```

## 📚 関連ドキュメント

- [インストールと設定](./installation.md) - 初期設定ガイド
- [パフォーマンス最適化](./performance.md) - パフォーマンス設定の詳細
- [カスタマイズ](./customization.md) - 高度なカスタマイズ