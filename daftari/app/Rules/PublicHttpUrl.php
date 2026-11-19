<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Guards against SSRF for user-supplied outbound URLs (webhook endpoints):
 * requires http/https, and rejects hosts that resolve to a loopback,
 * private, link-local, or otherwise reserved IP range — which would let a
 * webhook URL be used to probe the server's own network from the inside.
 */
class PublicHttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isSafe((string) $value)) {
            $fail(__('The :attribute must be a valid http:// or https:// URL that does not point to a private or internal address.'));
        }
    }

    /**
     * Audit finding SEC-05: this check previously only ran once, when the
     * webhook URL was first saved — a DNS record a host resolves to is
     * not fixed at save time. SendWebhookDelivery now calls this same
     * logic again immediately before every actual delivery attempt
     * (DNS-rebinding window shrunk from "the lifetime of the webhook" to
     * "between this check and the connection a few milliseconds later" —
     * full protection would need the resolved IP pinned for the
     * connection itself, e.g. via CURLOPT_RESOLVE, which this does not
     * attempt). Exposed as a static method, not just the validation-rule
     * entry point, specifically so both callers share one implementation.
     */
    public static function isSafe(string $url): bool
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! $parts || empty($parts['scheme']) || empty($parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
