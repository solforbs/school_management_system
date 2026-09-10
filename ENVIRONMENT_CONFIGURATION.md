# Environment Configuration Management

The environment configuration feature allows administrators to manage application settings and API credentials through the web interface without needing direct access to the `.env` file.

## Overview

### Features

- **Web-based editing**: Manage environment variables through the admin settings page
- **Organized by category**: Variables are grouped by function (General, Mail, Storage, Payments, SMS, AI, Device Sync)
- **Encryption at rest**: Sensitive values (API keys, passwords, secrets) are encrypted before being stored
- **Validation**: Each field is validated according to predefined rules
- **Atomic updates**: Changes are only persisted if validation passes
- **Backup preservation**: Empty password fields preserve existing encrypted values

### Security

Secret fields marked in the configuration are:
- Encrypted using Laravel's encryption (`Illuminate\Support\Facades\Crypt`)
- Never sent back to the browser (blank placeholders shown instead)
- Can only be updated by admins (role-based access control)

## Architecture

### Components

#### `EnvManager` (`app/Services/EnvManager.php`)
Handles reading and writing the `.env` file.

**Public Methods:**
- `get(string $key, ?string $default = null): ?string` - Read a single value
- `put(string $key, ?string $value): void` - Write a single value
- `getMany(array $keys): array` - Read multiple values
- `putMany(array $values): void` - Write multiple values

**Example:**
```php
$envManager = app(EnvManager::class);
$appName = $envManager->get('APP_NAME');
$envManager->put('APP_NAME', 'My School');
```

#### `ConfigurationManager` (`app/Services/ConfigurationManager.php`)
Defines which environment variables can be edited and their metadata.

**Structure:**
```php
const EDITABLE_CONFIG = [
    'category_name' => [
        'VARIABLE_NAME' => [
            'label' => 'Human-readable label',
            'type' => 'text|password|email|number|select|url',
            'description' => 'Field description',
            'rules' => 'Laravel validation rules',
            'secret' => false, // Whether to encrypt
            'options' => [], // For select type
        ],
    ],
];
```

**Public Methods:**
- `getCategories(): array` - Get all editable configuration by category
- `getCategory(string $category): array` - Get configuration for specific category
- `getEditableKeys(): array` - Get all editable variable names
- `getConfig(string $key): ?array` - Get metadata for specific variable
- `isSecret(string $key): bool` - Check if variable should be encrypted
- `getRules(string $key): ?string` - Get validation rules for variable
- `getAllRules(): array` - Get all validation rules

**Example:**
```php
$categories = ConfigurationManager::getCategories();
$rules = ConfigurationManager::getAllRules();
$isSecret = ConfigurationManager::isSecret('MAIL_PASSWORD');
```

#### `EnvironmentConfiguration` Livewire Component (`app/Livewire/EnvironmentConfiguration.php`)
Manages the UI and state for the configuration editor.

**Public Properties:**
- `configuration: array` - Current configuration values grouped by category
- `originalValues: array` - Values loaded from disk (for reset functionality)
- `secrets: array` - Tracks which fields are secret
- `activeCategory: string` - Current tab in UI
- `showSensitiveValues: bool` - Whether to show password fields in plaintext

**Public Methods:**
- `save(): void` - Validate and persist changes to .env
- `resetConfiguration(): void` - Restore values from disk

## Editable Configuration Categories

### General
- `APP_NAME` - Application name
- `SUPPORT_PHONE` - Support contact phone
- `CONTACT_MAIL_TO` - Contact form email destination

### Mail
- `MAIL_MAILER` - Mail driver (log, smtp, sendmail, etc.)
- `MAIL_HOST` - SMTP server hostname
- `MAIL_PORT` - SMTP port
- `MAIL_USERNAME` - SMTP authentication username
- `MAIL_PASSWORD` - SMTP password (encrypted)
- `MAIL_ENCRYPTION` - Encryption protocol (null, tls, ssl)
- `MAIL_FROM_ADDRESS` - Sender email address
- `MAIL_FROM_NAME` - Sender name

### Storage
- `AWS_ACCESS_KEY_ID` - AWS access key
- `AWS_SECRET_ACCESS_KEY` - AWS secret key (encrypted)
- `AWS_DEFAULT_REGION` - AWS region
- `AWS_BUCKET` - S3 bucket name
- `CLOUDINARY_URL` - Cloudinary connection string
- `CLOUDINARY_CLOUD_NAME` - Cloudinary cloud name
- `CLOUDINARY_KEY` - Cloudinary API key
- `CLOUDINARY_SECRET` - Cloudinary API secret (encrypted)

### Payments
- `PAYSTACK_PUBLIC_KEY` - Paystack public key
- `PAYSTACK_SECRET_KEY` - Paystack secret key (encrypted)

### SMS
- `PINNACLE_API_KEY` - Host Pinnacle API key (encrypted)
- `PINNACLE_API_URL` - Host Pinnacle API endpoint
- `PINNACLE_USER_ID` - Host Pinnacle user ID
- `PINNACLE_SENDER_ID` - SMS sender ID
- `PINNACLE_API_PASSWORD` - Host Pinnacle password (encrypted)

### AI
- `GEMINI_API_KEY` - Google Gemini API key (encrypted)
- `GEMINI_MODEL` - Gemini model name

### Device Sync
- `ZKTECO_DEFAULT_TIMEZONE` - Biometric device timezone
- `ZKTECO_ADMS_STORAGE_TIMEZONE` - Device data storage timezone

## Adding New Configuration

To add a new editable configuration variable:

1. **Update `ConfigurationManager::EDITABLE_CONFIG`** with the variable definition:

```php
const EDITABLE_CONFIG = [
    'my_category' => [
        'MY_NEW_VAR' => [
            'label' => 'My Variable',
            'type' => 'text',
            'description' => 'What this does',
            'rules' => 'required|string|max:255',
            'secret' => false,
        ],
    ],
];
```

2. **The UI will automatically include it** in the Configuration tab

3. **No database migrations needed** - everything is stored in `.env`

### Field Types

- `text` - Plain text input
- `email` - Email input with validation
- `number` - Numeric input
- `url` - URL input with validation
- `password` - Password input (hidden, can toggle visibility)
- `select` - Dropdown (requires `options` array)

### Validation Rules

Use standard Laravel validation rules. Examples:
- `required|string|max:255` - Required string, max 255 chars
- `nullable|email|max:255` - Optional email address
- `nullable|numeric|between:1,65535` - Optional number between 1-65535
- `required|string|in:log,smtp,sendmail` - Required, must be one of these values

## Usage in Code

### Reading Configuration

```php
use App\Services\EnvManager;

$envManager = app(EnvManager::class);
$appName = $envManager->get('APP_NAME', 'Default App');
```

Or use Laravel's built-in `env()` helper:
```php
$appName = env('APP_NAME', 'Default App');
```

### Updating Configuration

```php
use App\Services\EnvManager;

$envManager = app(EnvManager::class);
$envManager->put('APP_NAME', 'New School Name');

// Batch update
$envManager->putMany([
    'APP_NAME' => 'New Name',
    'MAIL_HOST' => 'smtp.example.com',
    'MAIL_PORT' => '587',
]);
```

## Access Control

The configuration page is restricted to users with the `Admin` role.

```php
abort_unless(isAdmin(), 403);
```

## Caching

The `EnvManager` uses Laravel's cache to optimize performance:
- Cache keys: `env.{KEY_NAME}`
- Cache is busted automatically when values are updated
- Cache is forever (no TTL) for performance

## File Structure

When saving, the `.env` file is rewritten to preserve:
- Comments and blank lines (structure)
- Original line order (for modified values)
- New values are appended if not in the original file

## Testing

Run the test suite:

```bash
php artisan test tests/Feature/EnvironmentConfigurationTest.php
```

Test coverage includes:
- Access control (guests, non-admins, admins)
- Loading initial values
- Saving configuration
- Secret value encryption
- Validation
- Preserving existing secrets with empty fields
- Configuration reset
- Category organization

## Troubleshooting

### Changes not appearing in application
- Restart the application server (PHP-FPM, artisan serve, etc.)
- Clear Laravel cache: `php artisan cache:clear`
- Clear config cache: `php artisan config:cache`

### .env file not updating
- Ensure the `.env` file is writable: `chmod 644 .env`
- Check logs for permission errors
- Verify the application has write permissions to the project directory

### Encrypted values not decrypting
- Verify `APP_KEY` is set correctly
- Check that the encryption key hasn't changed
- Ensure `Illuminate\Support\Facades\Crypt` is being used

## Future Enhancements

Potential improvements:
- Audit logging for configuration changes
- Configuration versioning/rollback
- Environment-specific presets (development, staging, production)
- Validation testing (ping SMTP server, test AWS credentials, etc.)
- Configuration import/export
- Per-field access control (restrict who can edit specific settings)
