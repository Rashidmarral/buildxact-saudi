<?php

namespace App\Services\Payments;

use App\Services\Payments\Drivers\HyperPayDriver;
use App\Services\Payments\Drivers\MoyasarDriver;
use App\Services\Payments\Drivers\PayTabsDriver;
use App\Services\Payments\Drivers\TapDriver;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public static function driver(string $provider): PaymentGatewayDriver
    {
        return match ($provider) {
            'moyasar' => app(MoyasarDriver::class),
            'hyperpay' => app(HyperPayDriver::class),
            'tap' => app(TapDriver::class),
            'paytabs' => app(PayTabsDriver::class),
            default => throw new InvalidArgumentException("Unknown payment provider [{$provider}]."),
        };
    }
}
