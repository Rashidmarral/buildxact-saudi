<?php

namespace App\Services;

use App\Models\WhatsappConfig;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Meta's WhatsApp Cloud API (developers.facebook.com/
 * docs/whatsapp/cloud-api) — confirmed against Meta's own documentation:
 * POST https://graph.facebook.com/{version}/{phone_number_id}/messages,
 * Authorization: Bearer {access_token}, body shape below.
 *
 * Business-initiated messages (an invoice notification is exactly that —
 * the customer didn't message first) can only be sent as a template
 * message, and templates must be created and approved inside the
 * company's own Meta Business account — there is no API for a third
 * party like Daftari to create or approve one on a company's behalf.
 * That's why $config->template_name is something the company types in
 * themselves rather than something this service generates.
 */
class WhatsAppService
{
    private const API_VERSION = 'v20.0';

    /**
     * @param  array<int, string>  $bodyParams  Positional {{1}}, {{2}}, ...
     *                                          values for the template's body.
     */
    public function sendTemplateMessage(WhatsappConfig $config, string $toPhone, array $bodyParams): array
    {
        // Meta's API (and any network hop in between) can fail outright —
        // DNS, timeout, TLS — not just respond with an error status. Those
        // must degrade to a clean failure result, never an uncaught 500,
        // same as every other outbound integration in this app.
        try {
            $response = Http::withToken($config->access_token)
                ->timeout(20)
                ->post(sprintf('https://graph.facebook.com/%s/%s/messages', self::API_VERSION, $config->phone_number_id), [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normalizePhone($toPhone),
                    'type' => 'template',
                    'template' => [
                        'name' => $config->template_name,
                        'language' => ['code' => $config->template_language],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], $bodyParams),
                            ],
                        ],
                    ],
                ]);
        } catch (HttpClientException $e) {
            // Covers both a connection-level failure (DNS, timeout, TLS)
            // and a transport-layer rejection before any response body is
            // available to inspect (e.g. an egress proxy blocking the
            // destination host) — either way there's nothing to parse as
            // a normal API response, so fail cleanly instead of a 500.
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => $response->json('error.message') ?? $response->body(),
            ];
        }

        return [
            'success' => true,
            'message_id' => $response->json('messages.0.id'),
        ];
    }

    /**
     * The Cloud API expects E.164 without a leading "+" — strip everything
     * but digits and, for a bare local Saudi number (05XXXXXXXX), swap the
     * leading 0 for the 966 country code so companies can enter numbers
     * the way they normally would.
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
