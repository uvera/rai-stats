<?php

namespace App\Services\Receipts;

/**
 * Thrown when the Moj Maxi backend rejects the bearer token (HTTP 401) - the
 * stored JWT is missing, expired, or otherwise invalid and the account needs
 * a fresh password to log in again.
 */
class ReceiptAuthException extends ReceiptException {}
