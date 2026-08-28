<?php

namespace App\Enums;

use App\Services\Receipts\Contracts\ProviderClient;
use App\Services\Receipts\Maxi\MaxiProvider;
use App\Services\Receipts\Metro\MetroProvider;

/**
 * Which grocery receipt backend a GroceryAccount belongs to.
 */
enum ReceiptProvider: string
{
    case Maxi = 'maxi';
    case Metro = 'metro';

    public function label(): string
    {
        return match ($this) {
            self::Maxi => 'Moj Maxi',
            self::Metro => 'Metro',
        };
    }

    /**
     * @return class-string<ProviderClient>
     */
    public function clientClass(): string
    {
        return match ($this) {
            self::Maxi => MaxiProvider::class,
            self::Metro => MetroProvider::class,
        };
    }

    public function client(): ProviderClient
    {
        return app($this->clientClass());
    }
}
