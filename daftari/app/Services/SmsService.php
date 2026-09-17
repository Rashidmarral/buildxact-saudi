<?php

namespace App\Services;

use App\Models\SmsConfig;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Unifonic's REST SMS API — a widely used SMS gateway
 * for Saudi/GCC businesses, and unlike WhatsApp's Cloud API it can send
 * plain free-form text with no pre-approved template required, so an
 * invoice/reminder message just needs to be composed as ordinary text.
 *
 * POST https://el.cloud.unifonic.com/rest/SMS/messages
 * form fields: AppSid, SenderID, Body, Recipient, responseType=JSON
 */
class SmsService
{
    private const API_URL = 'https://el.cloud.unifonic.com/rest/SMS/messages';

    public function send(SmsConfig $config, string $toPhone, string $message): array
    {
        // Same failure-mode discipline as every other outbound integration
        // in this app (WhatsApp, payment gateways, webhooks): a network-level
        // failure (DNS, timeout, TLS, egress block) must degrade to a clean
        // failure result, never an uncaught 500.
        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post(self::API_URL, [
                    'AppSid' => $config->app_sid,
                    'SenderID' => $config->sender_id,
                    'Body' => $message,
                    'Recipient' => $this->normalizePhone($toPhone),
                    'responseType' => 'JSON',
                ]);
        } catch (HttpClientException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => $response->json('message') ?? $response->body(),
            ];
        }

        $success = (bool) $response->json('success', false);

        if (! $success) {
            return [
                'success' => false,
                'error' => $response->json('message') ?? __('The SMS gateway rejected the message.'),
            ];
        }

        return [
            'success' => true,
            'message_id' => $response->json('data.MessageID'),
        ];
    }

    /**
     * Unifonic expects international format without a leading "+" — strip
     * everything but digits and, for a bare local Saudi number
     * (05XXXXXXXX), swap the leading 0 for the 966 country code so
     * companies can enter numbers the way they normally would.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '966'.substr($digits, 1);
        }

        return $digits;
    }
}
