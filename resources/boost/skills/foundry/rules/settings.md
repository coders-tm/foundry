# Settings Domain

## Overview

The Settings domain manages application-wide configuration via a flat JSON-based store (`resources/settings.json`). This system replaces legacy database-backed settings with a high-performance, file-driven approach that integrates directly with Laravel's native configuration system.

## Core Components

- **`Settings` Facade** (`Foundry\Facades\Settings`) — The primary interface for interacting with settings.
- **`SettingsService`** (`Foundry\Services\SettingsService`) — Handles the JSON I/O, recursive flattening, and config merging logic.
- **`settings()` Helper** — A global helper that proxies to the `Settings` facade for both reading and writing.
- **`SettingChanged` Event** — Fired whenever a setting is updated, allowing for cache clearing or process restarts (e.g., queue workers).

## Key Workflows

### Reading Settings

Settings are accessed via dot notation. The system automatically pulls from the `settings` config namespace which is populated from the JSON file.

1. **Via helper** (Recommended):
   ```php
   $value = settings('config.name');
   $value = settings('mail.default', 'smtp'); // With default
   ```

2. **Via Facade**:
   ```php
   use Foundry\Facades\Settings;
   
   $name = Settings::get('config.name');
   ```

### Writing Settings

Updates are persisted immediately to the JSON storage and reflected in the runtime config.

1. **Single update**:
   ```php
   settings(['config.name' => 'My New App']);
   // or
   Settings::set('config.name', 'My New App');
   ```

2. **Nested array update** (Non-destructive):
   ```php
   // This will only update the 'host' and 'port', preserving other 'mailers' config
   Settings::set('mail.mailers.smtp', [
       'host' => '127.0.0.1',
       'port' => '1025',
   ]);
   ```

## Configuration Merging

The system automatically synchronizes JSON settings with Laravel's internal configuration based on the `settings_override` map defined in `config/foundry.json`.

### Non-Destructive Merging
When settings are loaded, they are recursively flattened. This means setting `mail.mailers.smtp` will update only those specific keys in `config('mail.mailers')`, leaving other keys (like `ses` or `postmark`) untouched.

### Mapping Rules
Mappings can be:
- **Direct Alias**: `'currency' => 'stripe.currency'`
- **Multiple Keys**: `'email' => ['foundry.admin_email', 'mail.from.address']`
- **Callable**: Custom logic executed when the setting is changed.

## Storage & Configuration

- **Storage Location**: By default, settings are stored in `resources/settings.json`.
- **Configurable Path**: The path can be customized via `FOUNDRY_SETTINGS_PATH` in the `.env` file or the `settings_path` key in `config/foundry.php`.
- **Cache**: Updates fire the `SettingChanged` event, which can be used to clear application caches.

## Best Practices

- **Avoid Secrets**: Do not store passwords or sensitive API keys in the JSON file; use `.env` for secrets.
- **Dot Notation**: Always use dot notation for clarity and consistency.
- **Recursive Updates**: Prefer passing arrays for grouped updates to minimize disk I/O.
- **Testing**: In testing environments, the system defaults to a temporary `tests/settings.json` to ensure test isolation.

## Common Tasks

### Updating Mail Configuration
```php
Settings::set('mail', [
    'default' => 'smtp',
    'mailers' => [
        'smtp' => [
            'host' => 'mailhog',
            'port' => 1025
        ]
    ]
]);
```

### Accessing App Settings in Views
```blade
<h1>{{ settings('config.name') }}</h1>
```

### Reacting to Setting Changes
Listen for `Foundry\Events\SettingChanged`:
```php
public function handle(SettingChanged $event)
{
    if ($event->key === 'config.timezone') {
        // Handle timezone change logic
    }
}
```
