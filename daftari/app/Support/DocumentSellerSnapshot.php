<?php

namespace App\Support;

/**
 * A frozen stand-in for the issuing Company record on an already-issued
 * invoice — see Invoice::capturePartySnapshot()/sellerForDocument().
 * Deliberately mirrors the handful of Company properties that the print
 * templates (documents/print/*.blade.php) and ZatcaXmlGenerator actually
 * read off $invoice->company, so neither needs to know whether it's
 * looking at a live Company or a frozen snapshot.
 */
class DocumentSellerSnapshot
{
    public readonly ?string $name;

    public readonly ?string $name_ar;

    public readonly ?string $vat_number;

    public readonly ?string $cr_number;

    public readonly ?string $address;

    public readonly ?string $building_number;

    public readonly ?string $street_name;

    public readonly ?string $district;

    public readonly ?string $city;

    public readonly ?string $postal_code;

    public readonly ?string $additional_number;

    public readonly ?string $phone;

    public readonly ?string $email;

    public readonly ?string $logo_path;

    public readonly ?string $stamp_path;

    public readonly ?string $stamp_size;

    public function __construct(array $attributes)
    {
        $this->name = $attributes['name'] ?? null;
        $this->name_ar = $attributes['name_ar'] ?? null;
        $this->vat_number = $attributes['vat_number'] ?? null;
        $this->cr_number = $attributes['cr_number'] ?? null;
        $this->address = $attributes['address'] ?? null;
        $this->building_number = $attributes['building_number'] ?? null;
        $this->street_name = $attributes['street_name'] ?? null;
        $this->district = $attributes['district'] ?? null;
        $this->city = $attributes['city'] ?? null;
        $this->postal_code = $attributes['postal_code'] ?? null;
        $this->additional_number = $attributes['additional_number'] ?? null;
        $this->phone = $attributes['phone'] ?? null;
        $this->email = $attributes['email'] ?? null;
        $this->logo_path = $attributes['logo_path'] ?? null;
        $this->stamp_path = $attributes['stamp_path'] ?? null;
        $this->stamp_size = $attributes['stamp_size'] ?? null;
    }
}
