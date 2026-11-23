<?php

use App\Enums\SubscriptionType;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public function brandProfileComplete(): bool
    {
        $profile = auth()->user()->brandProfile;

        return $profile !== null
            && filled($profile->company_name)
            && filled($profile->description)
            && filled($profile->industry);
    }

    public function isFreePlan(): bool
    {
        return auth()->user()->subscription_type === SubscriptionType::FreePlan;
    }
};
?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl" level="1">Welcome, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">Your {{ $this->isFreePlan() ? 'Free' : 'Paid' }} client dashboard.</flux:text>
    </div>

    @if ($this->isFreePlan())
        <flux:callout variant="warning" icon="sparkles" heading="Upgrade to the Paid Plan">
            Unlock the full freelancer directory, complete profiles, new projects and consultation booking. Billing will be available soon.
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" disabled>Upgrade to Paid</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @unless ($this->brandProfileComplete())
        <flux:callout icon="building-office" heading="Complete your brand profile">
            Tell freelancers about your company and pick your interests so we can recommend the right people.
            <x-slot name="actions">
                <flux:button size="sm" href="{{ route('client.brand-profile') }}">Open brand profile</flux:button>
            </x-slot>
        </flux:callout>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card>
            <flux:heading>Recommended freelancers</flux:heading>
            <flux:text class="mt-1">Personalised recommendations will appear here.</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading>Projects and calls</flux:heading>
            <flux:text class="mt-1">You have no projects or calls yet.</flux:text>
        </flux:card>
    </div>
</div>
