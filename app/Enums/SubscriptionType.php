<?php

namespace App\Enums;

enum SubscriptionType: string
{
    case FreePlan = 'free-plan';
    case PaidPlan = 'paid-plan';
}
