<?php

namespace App\Enums;

use App\Services\Raiffeisen\Data\TransactionType as RaiffeisenTransactionType;

enum TransactionType: string
{
    case Pos = 'pos';
    case Other = 'other';
    case ExchBuy = 'exch_buy';
    case ExchSell = 'exch_sell';
    case Income = 'income';
    case IncomeCash = 'income_cash';

    /**
     * Not a real Raiffeisen transaction type - assigned to rows imported
     * from GetTransactionalAccountReservedFunds (pending holds, no stable
     * bank ID). Always excluded from stats: not a final amount.
     */
    case Reserved = 'reserved';

    public static function fromRaiffeisen(RaiffeisenTransactionType $type): self
    {
        return match ($type) {
            RaiffeisenTransactionType::Pos => self::Pos,
            RaiffeisenTransactionType::Other => self::Other,
            RaiffeisenTransactionType::ExchBuy => self::ExchBuy,
            RaiffeisenTransactionType::ExchSell => self::ExchSell,
            RaiffeisenTransactionType::Income => self::Income,
            RaiffeisenTransactionType::IncomeCash => self::IncomeCash,
        };
    }
}
