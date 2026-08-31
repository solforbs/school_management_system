<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * @var array<string, mixed>
     */
    protected array $config;

    public function __construct()
    {
        $this->config = config('sms');
    }

    /**
     * @return array<string, mixed>
     */
    public function send(int $mobile, string $message, ?string $provider = null): array
    {
        if (! featureEnabled('sms')) {
            return [
                'success' => false,
                'error' => 'SMS is currently disabled in site settings',
            ];
        }

        // Use default provider if none specified
        $provider ??= $this->config['default'];

        // Get provider configuration
        $providerConfig = $this->config['providers'][$provider] ?? null;

        if (! $providerConfig) {
            return [
                'success' => false,
                'error' => "SMS provider '{$provider}' not found in configuration",
            ];
        }

        $mobile = $this->formatMobile($mobile);

        /*
     * Prevent the exact same SMS from being submitted twice at the
     * same time or repeatedly within the deduplication window.
     *
     * The key is based on:
     * - provider
     * - recipient
     * - message
     */
        $dedupeKey = 'sms:dedupe:'.hash(
            'sha256',
            $provider.'|'.$mobile.'|'.$message
        );

        $lock = Cache::lock($dedupeKey, 30);

        if (! $lock->get()) {
            Log::warning('Duplicate SMS prevented', [
                'provider' => $provider,
                'mobile' => $mobile,
            ]);

            return [
                'success' => false,
                'duplicate' => true,
                'error' => 'Duplicate SMS prevented',
            ];
        }

        try {
            // Build POST data from provider configuration
            $postData = http_build_query([
                'userid' => $providerConfig['userid'],
                'password' => $providerConfig['password'],
                'mobile' => $mobile,
                'msg' => $message,
                'senderid' => $providerConfig['senderid'],
                'msgType' => $providerConfig['default_msg_type'],
                'duplicatecheck' => $providerConfig['default_duplicate_check'],
                'output' => $providerConfig['default_output'],
                'sendMethod' => $providerConfig['default_send_method'],
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $providerConfig['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => $this->config['options']['timeout'] ?? 30,
                CURLOPT_MAXREDIRS => $this->config['options']['max_redirects'] ?? 10,
                CURLOPT_HTTPHEADER => [
                    'apikey: '.($providerConfig['apikey'] ?? ''),
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            // cURL/network error
            if ($error) {
                Log::error('HostPinnacle cURL error', [
                    'http_code' => $httpCode,
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => $error,
                    'http_code' => $httpCode,
                ];
            }

            // Decode gateway response
            $decodedResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decodedResponse)) {
                Log::error('HostPinnacle invalid response', [
                    'http_code' => $httpCode,
                    'raw_response' => $response,
                    'json_error' => json_last_error_msg(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Invalid JSON response',
                    'raw_response' => $response,
                    'http_code' => $httpCode,
                ];
            }

            // Log gateway response
            Log::debug('HostPinnacle response', [
                'http_code' => $httpCode,
                'response' => $decodedResponse,
            ]);

            return $this->normaliseResponse($decodedResponse);
        } finally {
            $lock->release();
        }
    }

    /**
     * The gateway reports its outcome in `status`, while the failure paths
     * above return a `success` flag. Callers shouldn't have to know the
     * provider's response shape, so guarantee `success` either way.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public function normaliseResponse(array $decoded): array
    {
        $decoded['success'] = ($decoded['status'] ?? null) === 'success';

        return $decoded;
    }

    private function formatMobile($mobile)
    {
        $mobile = preg_replace('/[^0-9]/', '', $mobile);

        if (strlen($mobile) == 10 && substr($mobile, 0, 1) == '0') {
            return '254'.substr($mobile, 1);
        }

        return $mobile;
    }
}
