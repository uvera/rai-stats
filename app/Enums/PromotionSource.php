<?php

namespace App\Enums;

/**
 * Which feed a cached promotion row came from.
 */
enum PromotionSource: string
{
    case Ranked = 'ranked';
    case Directed = 'directed';
    case Triweekly = 'triweekly';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case PlusGratis = 'plus_gratis';
    case MaxiBees = 'maxi_bees';
}
