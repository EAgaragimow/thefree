<?php

namespace App\Console\Commands;

use App\Models\FreelancerProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-newbie-profiles')]
#[Description('Clear the newbie flag of profiles that are older than a month.')]
class ExpireNewbieProfiles extends Command
{
    public function handle(): int
    {
        $expired = FreelancerProfile::query()
            ->where('is_newbie', true)
            ->where('created_at', '<=', now()->subMonths(FreelancerProfile::NEWBIE_MONTHS))
            ->update(['is_newbie' => false]);

        $this->info("Cleared the newbie flag of {$expired} profile(s).");

        return self::SUCCESS;
    }
}
