<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Wraps the Saudi Ministry of Commerce's Wathq API — one platform-level
 * subscription (see PlatformSettingsController::updateIntegrations()) used
 * on behalf of every company to auto-pull Commercial Registration details
 * instead of a user typing the CR number and trusting it's correct.
 *
 * Manual entry on the Client/Supplier forms always keeps working regardless
 * of whether this is configured — this only powers the optional "Verify via
 * Wathq" button next to it. Wathq's exact response field names aren't
 * publicly documented without a live developer account, so the mapping in
 * mapCommercialRegistration() is a best-effort guess kept in one place —
 * if real responses differ, only that method needs correcting.
 */
class WathqService
{
    private const CR_FULLINFO_URL = 'https://api.wathq.sa/commercial-registration/fullinfo/';

    private const NATIONAL_ADDRESS_URL = 'https://api.wathq.sa/spl/national/address/info/';

    public function isConfigured(): bool
    {
        return Setting::isConfigured('wathq_api_key');
    }

    public function lookupCommercialRegistration(string $crNumber): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => __('Wathq is not set up yet. Ask your Daftari administrator to add a Wathq API key in Platform Settings → Integrations, or enter these details manually.'),
            ];
        }

        $crNumber = trim($crNumber);

        if ($crNumber === '') {
            return ['success' => false, 'error' => __('Enter a CR number first.')];
        }

        try {
            $response = Http::withHeaders(['apiKey' => Setting::get('wathq_api_key')])
                ->acceptJson()
                ->timeout(15)
                ->get(self::CR_FULLINFO_URL.rawurlencode($crNumber));
        } catch (ConnectionException $e) {
            return ['success' => false, 'error' => __('Could not reach the Wathq verification service right now. Please try again or enter the details manually.')];
        }

        if ($response->status() === 404) {
            return ['success' => false, 'error' => __('No commercial registration was found for that number.')];
        }

        if ($response->failed()) {
            return ['success' => false, 'error' => __('Wathq could not verify this CR number right now. Please try again or enter the details manually.')];
        }

        return ['success' => true, 'data' => $this->mapCommercialRegistration($response->json() ?? [], $crNumber)];
    }

    /**
     * $shortAddress is the Saudi National Address short code (4 letters +
     * 4 digits, e.g. RRRD2929) printed on a company's national-address
     * certificate — looking it up returns the full structured address so
     * it doesn't have to be typed field by field.
     */
    public function lookupNationalAddress(string $shortAddress): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => __('Wathq is not set up yet. Ask your Daftari administrator to add a Wathq API key in Platform Settings → Integrations, or enter the address manually.'),
            ];
        }

        $shortAddress = trim($shortAddress);

        if ($shortAddress === '') {
            return ['success' => false, 'error' => __('Enter a short address code first.')];
        }

        try {
            $response = Http::withHeaders(['apiKey' => Setting::get('wathq_api_key')])
                ->acceptJson()
                ->timeout(15)
                ->get(self::NATIONAL_ADDRESS_URL.rawurlencode($shortAddress));
        } catch (ConnectionException $e) {
            return ['success' => false, 'error' => __('Could not reach the Wathq verification service right now. Please try again or enter the address manually.')];
        }

        if ($response->status() === 404) {
            return ['success' => false, 'error' => __('No address was found for that short address code.')];
        }

        if ($response->failed()) {
            return ['success' => false, 'error' => __('Wathq could not verify this address right now. Please try again or enter it manually.')];
        }

        return ['success' => true, 'data' => $this->mapNationalAddress($response->json() ?? [], $shortAddress)];
    }

    private function mapNationalAddress(array $body, string $shortAddress): array
    {
        return [
            'short_address' => (string) ($body['shortAddress'] ?? $shortAddress),
            'building_number' => $body['buildingNumber'] ?? null,
            'street_name' => $body['streetName'] ?? $body['street'] ?? null,
            'district' => $body['district'] ?? $body['districtName'] ?? null,
            'city' => $body['city'] ?? $body['cityName'] ?? null,
            'postal_code' => $body['postCode'] ?? $body['postalCode'] ?? null,
            'additional_number' => $body['additionalNumber'] ?? null,
        ];
    }

    private function mapCommercialRegistration(array $body, string $crNumber): array
    {
        return [
            'cr_number' => (string) ($body['crNumber'] ?? $body['crnumber'] ?? $crNumber),
            'name' => $body['businessName'] ?? $body['crName'] ?? $body['crNameEn'] ?? null,
            'name_ar' => $body['crNameAr'] ?? $body['businessNameAr'] ?? null,
            'status' => $body['status'] ?? $body['crStatus'] ?? null,
            'issue_date' => $body['issueDate'] ?? null,
            'expiry_date' => $body['expiryDate'] ?? $body['crExpiryDate'] ?? null,
        ];
    }
}
