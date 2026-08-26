<?php

namespace App\Services\Raiffeisen\Data;

enum TransactionType: string
{
    case Pos = 'POS';
    case Other = 'Other';
    case ExchBuy = 'ExchBuy';
    case ExchSell = 'ExchSell';
    case Income = 'Income';
    case IncomeCash = 'IncomeCash';

    public static function fromRaw(string $raw): self
    {
        return self::tryFrom($raw) ?? self::Other;
    }
}
