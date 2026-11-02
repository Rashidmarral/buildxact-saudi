<?php

namespace App\Support;

/**
 * A frozen stand-in for a Client record on an already-issued invoice —
 * see Invoice::capturePartySnapshot()/partyForDocument(). Deliberately
 * mirrors the handful of Client properties and the fullAddress() method
 * that the print templates (documents/print/*.blade.php) actually read
 * off $doc['party'], so those templates don't need to know whether
 * they're looking at a live Client or a frozen snapshot.
 */
class DocumentPartySnapshot
{
    public readonly ?string $name;

    public readonly ?string $name_ar;

    public readonly ?string $vat_number;

    public readonly ?string $cr_number;

    public readonly ?string $building_number;

    public readonly ?string $street_name;

    public readonly ?string $district;

    public readonly ?string $city;

    public readonly ?string $state;

    public readonly ?string $country;

    public readonly ?string $postal_code;

    public readonly ?string $email;

    public function __construct(array $attributes)
    {
        $this->name = $attributes['name'] ?? null;
        $this->name_ar = $attributes['name_ar'] ?? null;
        $this->vat_number = $attributes['vat_number'] ?? null;
        $this->cr_number = $attributes['cr_number'] ?? null;
        $this->building_number = $attributes['building_number'] ?? null;
        $this->street_name = $attributes['street_name'] ?? null;
        $this->district = $attributes['district'] ?? null;
        $this->city = $attributes['city'] ?? null;
        $this->state = $attributes['state'] ?? null;
        $this->country = $attributes['country'] ?? null;
        $this->postal_code = $attributes['postal_code'] ?? null;
        $this->email = $attributes['email'] ?? null;
    }

    /**
     * Same field order/format as Client::fullAddress() — the print
     * templates check `method_exists($doc['party'], 'fullAddress')`
     * specifically so this and the live Client model stay interchangeable.
     */
    public function fullAddress(): string
    {
        return collect([
            $this->building_number,
            $this->street_name,
            $this->district,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }
}
