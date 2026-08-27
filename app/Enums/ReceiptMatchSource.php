<?php

namespace App\Enums;

/**
 * How a Maxi receipt got linked to a bank Transaction: automatically during
 * sync (exact amount + date + place), or by an admin picking it by hand.
 */
enum ReceiptMatchSource: string
{
    case Auto = 'auto';
    case Manual = 'manual';
}
