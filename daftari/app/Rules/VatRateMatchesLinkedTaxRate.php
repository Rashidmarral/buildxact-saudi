<?php

namespace App\Rules;

use App\Models\TaxRate;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Security audit finding M-02: a line's tax_rate_id (which ZatcaXmlGenerator
 * uses to pick the e-invoice tax category — standard/zero-rated/exempt) and
 * its numeric vat_rate (what the VAT amount is actually calculated from)
 * were accepted completely independently — a client could tag a line as
 * zero-rated while still charging 15% VAT on it, or the reverse, producing
 * an e-invoice whose reported tax category doesn't match the money it
 * actually moved. No-ops when the line has no tax_rate_id at all — that's
 * the normal, unlinked case every existing invoice/credit note already
 * uses, still validated on vat_rate alone.
 */
class VatRateMatchesLinkedTaxRate implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taxRateId = Arr::get($this->data, Str::replaceLast('vat_rate', 'tax_rate_id', $attribute));

        if (empty($taxRateId)) {
            return;
        }

        $taxRate = TaxRate::find($taxRateId);

        if ($taxRate && abs((float) $taxRate->rate - (float) $value) > 0.01) {
            $fail(__('The VAT rate must match the selected tax rate (:name — :rate%).', [
                'name' => $taxRate->name,
                'rate' => rtrim(rtrim(number_format((float) $taxRate->rate, 2), '0'), '.'),
            ]));
        }
    }
}
