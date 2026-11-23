<?php

namespace App\Enums;

enum BillingState: string
{
    case Ok = 'ok';
    case PaymentRestricted = 'payment-restricted';
}
