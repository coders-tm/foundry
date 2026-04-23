# Settings Domain

## Overview

The Settings domain manages application-wide and per-tenant configuration through a dynamic settings store, eliminating the need for hardcoded config values in files.

## Core Models & Services

- **`Setting`** (`src/Models/Setting.php`) — Key-value store for application settings with optional per-user or per-tenant scoping.
- **`ConfigLoader`** (`src/Services/ConfigLoader.php`) — Service for reading, writing, and merging settings with Laravel's native config system.

## Key Workflows

### Reading Settings

1. **Direct model query** (low-level):
   ```php
   $value = Setting::value('site_name'); // Returns value or null
   $value = Setting::value('site_name', 'Default Site'); // With fallback
   ```

2. **Via ConfigLoader** (recommended):
   ```php
   $value = ConfigLoader::get('site.name');
   ```

3. **Via helper** (if defined):
   ```php
   $value = setting('site_name');
   ```

### Writing Settings

1. **Create or update**:
   ```php
   Setting::setValue('site_name', 'My Platform');
   // Or
   ConfigLoader::set('site.name', 'My Platform');
   ```

2. **Batch update**:
   ```php
   Setting::setMany([
       'site_name' => 'My Platform',
       'site_logo_url' => 'https://...',
       'timezone' => 'UTC',
   ]);
   ```

### Dynamic Config Merging

`ConfigLoader` merges settings with Laravel config (stored in `config/*.php` files):
- Settings **override** config file values (dynamic > static).
- Fallback to config defaults if setting not found.
- Useful for admin panel to modify app behavior without redeployment.

## Database Schema

- `Setting` table with columns:
  - `key` (unique, e.g., `site_name`)
  - `value` (stored as JSON or text)
  - `group` (optional, e.g., `site`, `payment`, `email` for organization)
  - `scoped_type` (optional, for per-user/per-tenant, e.g., `User::class`)
  - `scoped_id` (optional, e.g., user ID or tenant ID)

## Typical Settings Categories

- **Site**: `site_name`, `site_logo_url`, `site_description`, `timezone`
- **Email**: `mail_from_address`, `mail_from_name`, `mail_driver`
- **Payment**: `stripe_public_key`, `stripe_secret_key`, `paypal_client_id` (though sensitive keys should use `.env`)
- **Features**: `enable_blog`, `enable_support_tickets`, `enable_wallet`
- **Branding**: `primary_color`, `secondary_color`, `font_family`

## Best Practices

- **Sensitive data**: Never store passwords, API secrets in `Setting`; use `.env` and `config/*.php` for secrets.
- **Caching**: Cache settings in Redis or file to avoid DB queries on every request (use `ConfigLoader::cache()`).
- **Validation**: Validate setting values before writing (e.g., email format, valid color hex).
- **Scoping**: Use `scoped_type` and `scoped_id` for per-user or per-tenant settings (multi-tenancy).
- **Groups**: Organize related settings under a `group` (e.g., all payment settings under `payment` group).
- **Audit logging**: Log who changed which setting and when for compliance/audit trails.

## Common Tasks

### Read Site Name Setting

```php
$siteName = Setting::value('site_name', 'Default App');
// or
$siteName = ConfigLoader::get('site.name', 'Default App');
```

### Update Site Settings via Admin Panel

```php
Setting::setMany([
    'site_name' => request('site_name'),
    'site_logo_url' => $logoUrl,
    'timezone' => request('timezone'),
    'primary_color' => request('primary_color'),
]);

// Clear cache to pick up new values
ConfigLoader::clearCache();
```

### Per-Tenant Settings (Multi-Tenancy)

```php
// Set tenant-specific setting
Setting::create([
    'key' => 'invoice_prefix',
    'value' => 'INV-2024-',
    'scoped_type' => Tenant::class,
    'scoped_id' => $tenant->id,
]);

// Retrieve
$value = Setting::whereKey('invoice_prefix')
    ->whereScopedType(Tenant::class)
    ->whereScopedId($tenant->id)
    ->value('value');
```

### Check Feature Flags

```php
if (ConfigLoader::get('features.enable_support_tickets', false)) {
    // Show support ticket link
}
```
