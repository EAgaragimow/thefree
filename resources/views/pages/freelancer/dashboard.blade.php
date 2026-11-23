<?php

use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public function profile(): ?FreelancerProfile
    {
        return auth()->user()->freelancerProfile()->with('openRevision')->first();
    }

    public function statusLabel(ProfileStatus $status): string
    {
        return match ($status) {
            ProfileStatus::Draft => 'Draft',
            ProfileStatus::PendingReview => 'Pending review',
            ProfileStatus::ChangesRequested => 'Changes requested',
            ProfileStatus::Published => 'Published',
        };
    }

    public function statusColor(ProfileStatus $status): string
    {
        return match ($status) {
            ProfileStatus::Draft => 'zinc',
            ProfileStatus::PendingReview => 'amber',
            ProfileStatus::ChangesRequested => 'red',
            ProfileStatus::Published => 'green',
        };
    }
};
?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl" level="1">Welcome, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">Your freelancer dashboard.</flux:text>
    </div>

    @if ($profile = $this->profile())
        @php($status = $profile->displayStatus())
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <flux:heading>Profile status</flux:heading>
                <flux:badge color="{{ $this->statusColor($status) }}">{{ $this->statusLabel($status) }}</flux:badge>
            </div>

            @if ($status === ProfileStatus::ChangesRequested && $profile->openRevision?->last_change_request)
                <flux:callout variant="warning" icon="exclamation-triangle" heading="An administrator requested changes">
                    {{ $profile->openRevision->last_change_request }}
                </flux:callout>
            @endif

            @if ($status === ProfileStatus::PendingReview)
                <flux:text>Your profile is being reviewed. We will email you once there is a decision.</flux:text>
            @elseif ($status === ProfileStatus::Published)
                <flux:text>Your profile is live.</flux:text>
            @else
                <flux:text>Complete your profile and submit it for review to get published.</flux:text>
            @endif

            @if ($profile->isPublished() && $status !== ProfileStatus::Published)
                <flux:text>Your last approved version stays visible while your new changes are reviewed.</flux:text>
            @endif

            <flux:button href="{{ route('freelancer.profile') }}" variant="primary">Open my profile</flux:button>
        </flux:card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card>
            <flux:heading>Requests and projects</flux:heading>
            <flux:text class="mt-1">You have no requests or projects yet.</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading>Calls</flux:heading>
            <flux:text class="mt-1">You have no upcoming calls.</flux:text>
        </flux:card>
    </div>
</div>
