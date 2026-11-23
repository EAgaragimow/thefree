<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Approved = 'approved';
    case ChangesRequested = 'changes-requested';
}
