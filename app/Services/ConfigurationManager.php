<?php

namespace App\Services;

/**
 * Defines editable environment variables organized by category.
 * This is the single source of truth for which env vars the admin can edit.
 */
class ConfigurationManager
{
    /**
     * All editable configuration organized by category.
     * Format: [category] => [variable_name => [label, type, description, validation_rules]]
     */
    public const EDITABLE_CONFIG = [
        'general' => [
            'APP_NAME' => [
                'label' => 'Application Name',
                'type' => 'text',
                'description' => 'The name of your school/application',
                'rules' => 'required|string|max:255',
            ],
            'SUPPORT_PHONE' => [
                'label' => 'Support Phone',
                'type' => 'text',
                'description' => 'Contact phone number for support',
                'rules' => 'nullable|string|max:50',
            ],
            'CONTACT_MAIL_TO' => [
                'label' => 'Contact Email',
                'type' => 'email',
                'description' => 'Email address for contact form submissions',
                'rules' => 'nullable|email|max:255',
            ],
        ],
        'mail' => [
            'MAIL_MAILER' => [
                'label' => 'Mail Driver',
                'type' => 'select',
                'options' => ['log', 'smtp', 'sendmail', 'mailgun', 'ses', 'postmark', 'resend'],
                'description' => 'Mail service driver to use',
                'rules' => 'required|string|in:log,smtp,sendmail,mailgun,ses,postmark,resend',
            ],
            'MAIL_HOST' => [
                'label' => 'SMTP Host',
                'type' => 'text',
                'description' => 'SMTP server hostname',
                'rules' => 'nullable|string|max:255',
            ],
            'MAIL_PORT' => [
                'label' => 'SMTP Port',
                'type' => 'number',
                'description' => 'SMTP server port',
                'rules' => 'nullable|numeric|between:1,65535',
            ],
            'MAIL_USERNAME' => [
                'label' => 'SMTP Username',
                'type' => 'text',
                'description' => 'SMTP authentication username',
                'rules' => 'nullable|string|max:255',
            ],
            'MAIL_PASSWORD' => [
                'label' => 'SMTP Password',
                'type' => 'password',
                'description' => 'SMTP authentication password (write-only: leave blank to keep)',
                'rules' => 'nullable|string',
            ],
            'MAIL_ENCRYPTION' => [
                'label' => 'SMTP Encryption',
                'type' => 'select',
                'options' => ['null', 'tls', 'ssl'],
                'description' => 'SMTP encryption protocol',
                'rules' => 'nullable|string|in:null,tls,ssl',
            ],
            'MAIL_FROM_ADDRESS' => [
                'label' => 'From Email Address',
                'type' => 'email',
                'description' => 'Email address used in "From" header',
                'rules' => 'nullable|email|max:255',
            ],
            'MAIL_FROM_NAME' => [
                'label' => 'From Name',
                'type' => 'text',
                'description' => 'Name used in "From" header',
                'rules' => 'nullable|string|max:255',
            ],
        ],
        'storage' => [
            'AWS_ACCESS_KEY_ID' => [
                'label' => 'AWS Access Key',
                'type' => 'text',
                'description' => 'AWS S3 access key ID',
                'rules' => 'nullable|string|max:255',
            ],
            'AWS_SECRET_ACCESS_KEY' => [
                'label' => 'AWS Secret Key',
                'type' => 'password',
                'description' => 'AWS S3 secret access key (write-only: leave blank to keep)',
                'rules' => 'nullable|string',
            ],
            'AWS_DEFAULT_REGION' => [
                'label' => 'AWS Region',
                'type' => 'text',
                'description' => 'AWS S3 region',
                'rules' => 'nullable|string|max:50',
            ],
            'AWS_BUCKET' => [
                'label' => 'S3 Bucket',
                'type' => 'text',
                'description' => 'S3 bucket name for file storage',
                'rules' => 'nullable|string|max:255',
            ],
            'CLOUDINARY_URL' => [
                'label' => 'Cloudinary URL',
                'type' => 'text',
                'description' => 'Cloudinary connection string',
                'rules' => 'nullable|string|max:500',
            ],
            'CLOUDINARY_CLOUD_NAME' => [
                'label' => 'Cloudinary Cloud Name',
                'type' => 'text',
                'description' => 'Cloudinary cloud name',
                'rules' => 'nullable|string|max:255',
            ],
            'CLOUDINARY_KEY' => [
                'label' => 'Cloudinary API Key',
                'type' => 'text',
                'description' => 'Cloudinary API key',
                'rules' => 'nullable|string|max:255',
            ],
            'CLOUDINARY_SECRET' => [
                'label' => 'Cloudinary Secret',
                'type' => 'password',
                'description' => 'Cloudinary API secret (write-only: leave blank to keep)',
                'rules' => 'nullable|string',
            ],
        ],
        'payments' => [
            'PAYSTACK_PUBLIC_KEY' => [
                'label' => 'Paystack Public Key',
                'type' => 'text',
                'description' => 'Paystack public key for frontend',
                'rules' => 'nullable|string|max:255',
            ],
            'PAYSTACK_SECRET_KEY' => [
                'label' => 'Paystack Secret Key',
                'type' => 'password',
                'description' => 'Paystack secret key (used directly by payment API)',
                'rules' => 'nullable|string|max:255',
            ],
        ],
        'sms' => [
            'PINNACLE_API_KEY' => [
                'label' => 'Pinnacle API Key',
                'type' => 'password',
                'description' => 'Host Pinnacle SMS API key (write-only: leave blank to keep)',
                'rules' => 'nullable|string',
            ],
            'PINNACLE_API_URL' => [
                'label' => 'Pinnacle API URL',
                'type' => 'url',
                'description' => 'Host Pinnacle SMS API endpoint',
                'rules' => 'nullable|url|max:500',
            ],
            'PINNACLE_USER_ID' => [
                'label' => 'Pinnacle User ID',
                'type' => 'text',
                'description' => 'Host Pinnacle user ID',
                'rules' => 'nullable|string|max:255',
            ],
            'PINNACLE_SENDER_ID' => [
                'label' => 'Pinnacle Sender ID',
                'type' => 'text',
                'description' => 'SMS sender ID',
                'rules' => 'nullable|string|max:50',
            ],
            'PINNACLE_API_PASSWORD' => [
                'label' => 'Pinnacle Password',
                'type' => 'password',
                'description' => 'Host Pinnacle password (write-only: leave blank to keep)',
                'rules' => 'nullable|string',
            ],
        ],
        'ai' => [
            'GEMINI_API_KEY' => [
                'label' => 'Gemini API Key',
                'type' => 'password',
                'description' => 'Google Gemini API key (write-only: leave blank to keep)',
                'rules' => 'nullable|string',
            ],
            'GEMINI_MODEL' => [
                'label' => 'Gemini Model',
                'type' => 'text',
                'description' => 'Gemini model to use (e.g. gemini-2.0-flash)',
                'rules' => 'nullable|string|max:100',
            ],
        ],
        'device_sync' => [
            'ZKTECO_DEFAULT_TIMEZONE' => [
                'label' => 'ZKTeco Timezone',
                'type' => 'text',
                'description' => 'Timezone for biometric devices',
                'rules' => 'nullable|string|max:50',
            ],
            'ZKTECO_ADMS_STORAGE_TIMEZONE' => [
                'label' => 'ZKTeco Storage Timezone',
                'type' => 'text',
                'description' => 'Storage timezone for device data',
                'rules' => 'nullable|string|max:50',
            ],
        ],
    ];

    /**
     * Get all editable configuration organized by category
     */
    /** @return array<string, array<string, array<string, mixed>>> */
    public static function getCategories(): array
    {
        return self::EDITABLE_CONFIG;
    }

    /**
     * Get configuration for a specific category
     */
    /** @return array<string, array<string, mixed>> */
    public static function getCategory(string $category): array
    {
        return self::EDITABLE_CONFIG[$category] ?? [];
    }

    /**
     * Get all editable variable names
     */
    /** @return array<string> */
    public static function getEditableKeys(): array
    {
        $keys = [];

        foreach (self::EDITABLE_CONFIG as $category => $vars) {
            $keys = array_merge($keys, array_keys($vars));
        }

        return $keys;
    }

    /**
     * Get configuration definition for a specific variable
     */
    /** @return array<string, mixed>|null */
    public static function getConfig(string $key): ?array
    {
        foreach (self::EDITABLE_CONFIG as $vars) {
            if (isset($vars[$key])) {
                return $vars[$key];
            }
        }

        return null;
    }

    /**
     * Whether a value is write-only in the UI: masked on entry, never sent
     * back to the browser, and left as-is when the field is submitted blank.
     *
     * These are deliberately stored in plain text. Every consumer reads them
     * straight through env() - config/mail.php, config/services.php,
     * config/sms.php, config/filesystems.php - so an encrypted value would
     * be handed to the provider verbatim and rejected, which is exactly how
     * the Paystack key broke. Encrypting would buy nothing anyway: APP_KEY
     * lives in the same file, so whoever can read .env can already decrypt
     * it. File permissions are the control that matters for .env.
     */
    public static function isWriteOnly(string $key): bool
    {
        return (self::getConfig($key)['type'] ?? null) === 'password';
    }

    /**
     * Get validation rules for a specific variable
     */
    public static function getRules(string $key): ?string
    {
        $config = self::getConfig($key);

        return $config['rules'] ?? null;
    }

    /**
     * Get all validation rules organized by variable name
     */
    /** @return array<string, string> */
    public static function getAllRules(): array
    {
        $rules = [];

        foreach (self::getEditableKeys() as $key) {
            if ($rule = self::getRules($key)) {
                $rules[$key] = $rule;
            }
        }

        return $rules;
    }
}
