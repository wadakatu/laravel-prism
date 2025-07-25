# Export Features Guide

Laravel Spectrum can export API documentation in various formats. It outputs in formats that can be directly imported into popular API testing tools like Postman and Insomnia.

## 🔗 Postman Export

### Basic Export

```bash
php artisan spectrum:export:postman
```

By default, outputs to `storage/app/spectrum/postman/collection.json`.

### Custom Output Location

```bash
php artisan spectrum:export:postman --output=/path/to/postman_collection.json
```

### Export Options

```bash
php artisan spectrum:export:postman \
    --include-examples \
    --include-tests \
    --environment
```

Option descriptions:
- `--include-examples`: Include request/response examples
- `--include-tests`: Generate automatic test scripts
- `--environment`: Also generate environment variables file

### Generated Content

#### Collection Structure

```json
{
  "info": {
    "name": "Laravel API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Authentication",
      "item": [
        {
          "name": "Login",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"user@example.com\",\n  \"password\": \"password123\"\n}"
            },
            "url": {
              "raw": "{{base_url}}/api/auth/login",
              "host": ["{{base_url}}"],
              "path": ["api", "auth", "login"]
            }
          }
        }
      ]
    }
  ]
}
```

#### Environment Variables

```json
{
  "name": "Laravel API Environment",
  "values": [
    {
      "key": "base_url",
      "value": "http://localhost:8000",
      "type": "default"
    },
    {
      "key": "bearer_token",
      "value": "",
      "type": "secret"
    }
  ]
}
```

#### Test Scripts

Example of automatically generated tests:

```javascript
// Response status check
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

// Response structure validation
pm.test("Response has required fields", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('data');
    pm.expect(jsonData.data).to.have.property('id');
    pm.expect(jsonData.data).to.have.property('email');
});

// Save authentication token
if (pm.response.json().token) {
    pm.environment.set("bearer_token", pm.response.json().token);
}
```

## 🦊 Insomnia Export

### Basic Export

```bash
php artisan spectrum:export:insomnia
```

By default, outputs to `storage/app/spectrum/insomnia/workspace.json`.

### Export Options

```bash
php artisan spectrum:export:insomnia \
    --workspace-name="My API" \
    --include-environments \
    --folder-structure
```

Option descriptions:
- `--workspace-name`: Workspace name
- `--include-environments`: Include environment settings
- `--folder-structure`: Organize with folder structure

### Generated Content

#### Workspace Structure

```json
{
  "_type": "export",
  "__export_format": 4,
  "__export_date": "2024-01-01T00:00:00.000Z",
  "__export_source": "laravel-spectrum",
  "resources": [
    {
      "_id": "wrk_1",
      "_type": "workspace",
      "name": "Laravel API",
      "description": "Generated by Laravel Spectrum"
    },
    {
      "_id": "env_1",
      "_type": "environment",
      "parentId": "wrk_1",
      "name": "Base Environment",
      "data": {
        "base_url": "http://localhost:8000",
        "bearer_token": ""
      }
    }
  ]
}
```

#### Request Structure

```json
{
  "_id": "req_1",
  "_type": "request",
  "parentId": "fld_auth",
  "name": "Create User",
  "method": "POST",
  "url": "{{ _.base_url }}/api/users",
  "headers": [
    {
      "name": "Content-Type",
      "value": "application/json"
    },
    {
      "name": "Authorization",
      "value": "Bearer {{ _.bearer_token }}"
    }
  ],
  "body": {
    "mimeType": "application/json",
    "text": "{\n  \"name\": \"John Doe\",\n  \"email\": \"john@example.com\",\n  \"password\": \"secret123\"\n}"
  }
}
```

## 🛠️ Customization

### Export Configuration

```php
// config/spectrum.php
'export' => [
    'postman' => [
        // Postman collection version
        'version' => '2.1.0',
        
        // Default variables
        'variables' => [
            'base_url' => env('APP_URL', 'http://localhost:8000'),
            'api_version' => 'v1',
        ],
        
        // Authentication presets
        'auth_presets' => [
            'bearer' => [
                'type' => 'bearer',
                'bearer' => [
                    'token' => '{{bearer_token}}'
                ]
            ],
        ],
        
        // Test generation settings
        'generate_tests' => true,
        'test_templates' => [
            'status_check' => true,
            'response_time' => true,
            'schema_validation' => true,
        ],
    ],
    
    'insomnia' => [
        // Environment settings
        'environments' => [
            'development' => [
                'base_url' => 'http://localhost:8000',
                'bearer_token' => '',
            ],
            'staging' => [
                'base_url' => 'https://staging.example.com',
                'bearer_token' => '',
            ],
            'production' => [
                'base_url' => 'https://api.example.com',
                'bearer_token' => '',
            ],
        ],
        
        // Folder structure
        'folder_by_tags' => true,
        'folder_icons' => [
            'Authentication' => '🔐',
            'Users' => '👥',
            'Admin' => '👨‍💼',
        ],
    ],
],
```

### Custom Formatters

Add your own export format:

```php
namespace App\Spectrum\Formatters;

use LaravelSpectrum\Contracts\ExportFormatter;

class CustomFormatter implements ExportFormatter
{
    public function format(array $openapi): array
    {
        // Convert OpenAPI document to custom format
        return [
            'version' => '1.0',
            'endpoints' => $this->transformEndpoints($openapi['paths']),
            // ...
        ];
    }
}
```

Registration:

```php
// AppServiceProvider.php
use LaravelSpectrum\Facades\Spectrum;

public function boot()
{
    Spectrum::addFormatter('custom', CustomFormatter::class);
}
```

Usage:

```bash
php artisan spectrum:export --format=custom --output=api.custom.json
```

## 🔄 Import Procedures

### Importing to Postman

1. Open Postman
2. Click "Import" in the left sidebar
3. Select "Upload Files"
4. Select the generated `collection.json`
5. Similarly import the environment variables file
6. Select the environment and execute requests

### Importing to Insomnia

1. Open Insomnia
2. Application → Preferences → Data → Import Data
3. Select "From File"
4. Select the generated file
5. Confirm import settings
6. Set environment variables and start testing

## 🔧 Advanced Usage

### Automatic Export in CI/CD

```yaml
# .github/workflows/export-api.yml
name: Export API Documentation

on:
  push:
    branches: [main]
    paths:
      - 'app/Http/**'
      - 'routes/**'

jobs:
  export:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install Dependencies
        run: composer install
        
      - name: Generate OpenAPI
        run: php artisan spectrum:generate
        
      - name: Export to Postman
        run: |
          php artisan spectrum:export:postman \
            --output=postman/collection.json \
            --environment
            
      - name: Export to Insomnia  
        run: |
          php artisan spectrum:export:insomnia \
            --output=insomnia/workspace.json
            
      - name: Commit changes
        uses: EndBug/add-and-commit@v9
        with:
          add: 'postman/* insomnia/*'
          message: 'Update API exports'
```

### Team Sharing

#### Postman Team Workspace

```bash
# Export with team settings
php artisan spectrum:export:postman \
    --team-id=your-team-id \
    --include-monitors \
    --include-mocks
```

#### Insomnia Git Sync

```bash
# Export with Git sync settings
php artisan spectrum:export:insomnia \
    --git-sync \
    --no-ids  # Exclude IDs to prevent conflicts
```

## 💡 Best Practices

### 1. Utilize Environment Variables

Make all environment-dependent values variable:

```json
{
  "base_url": "{{base_url}}",
  "api_key": "{{api_key}}",
  "timeout": "{{request_timeout}}"
}
```

### 2. Organize Folder Structure

Create folders organized by tags:

```
├── Authentication
│   ├── Login
│   ├── Logout
│   └── Refresh Token
├── Users
│   ├── List Users
│   ├── Create User
│   └── Update User
└── Admin
    ├── Dashboard
    └── Reports
```

### 3. Test Automation

Include basic tests when exporting:

```javascript
// Status code
pm.test("Success response", () => {
    pm.response.to.have.status(200);
});

// Response time
pm.test("Response time < 500ms", () => {
    pm.expect(pm.response.responseTime).to.be.below(500);
});
```

## 📚 Related Documentation

- [Basic Usage](./basic-usage.md) - Basics of documentation generation
- [CI/CD Integration](./ci-cd-integration.md) - Automation setup
- [Customization](./customization.md) - Advanced customization