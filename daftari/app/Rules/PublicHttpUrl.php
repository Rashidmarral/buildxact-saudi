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
        $url = trim((string) $value);
        $parts = parse_url($url);

        if (! $parts || empty($parts['scheme']) || empty($parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            $fail(__('The :attribute must be a valid http:// or https:// URL.'));

            return;
        }

        $host = $parts['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if (empty($ips)) {
            $fail(__('The :attribute host could not be resolved.'));

            return;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail(__('The :attribute must not point to a private or internal address.'));

                return;
            }
        }
    }
}
