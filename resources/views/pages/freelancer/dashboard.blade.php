<?php

use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public function profile(): ?FreelancerProfile
    {
        return auth()->user()->freelancerProfile()->with('openRevision')->first();
    }

    /**
     * The sections below save their own changes; the status above follows them.
     */
    #[On('profile-content-saved')]
    #[On('profile-revision-changed')]
    public function refreshStatus(): void
    {
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

<div class="space-y-4">
    <x-site.section id="overview">
        <flux:heading size="xl" level="1">Welcome, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">Your freelancer dashboard.</flux:text>

        @if ($profile = $this->profile())
            @php($status = $profile->displayStatus())
            <div class="mt-8 space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <flux:heading>Profile status</flux:heading>
                    <flux:badge color="{{ $this->statusColor($status) }}">{{ $this->statusLabel($status) }}</flux:badge>
                    @if ($profile->is_newbie)
                        <flux:badge color="amber">Newbie</flux:badge>
                    @endif
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

                <div class="flex flex-wrap gap-2">
                    <flux:button href="{{ route('freelancer.preview') }}" variant="primary">Preview Profile</flux:button>
                    <flux:button href="{{ route('freelancer.profile') }}">Profile Settings</flux:button>
                </div>
            </div>
        @endif
    </x-site.section>

    <x-site.section id="messages" heading="Messages" :count="0" :href="route('messages.index')">
        <x-site.empty title="No messages to show." text="Your latest conversations will appear here." />
    </x-site.section>

    <x-site.section id="requests" heading="Requests" :count="0">
        <x-site.empty title="No call requests to show." text="New requests will appear here as they come in." />
    </x-site.section>

    <x-site.section id="calls" heading="Calls" :count="0">
        <x-site.empty title="No calls to show." text="Scheduled consultations and past calls will appear here." />
    </x-site.section>

    <x-site.section id="projects" heading="Projects" :count="0">
        <x-site.empty title="No projects yet." text="Your projects will appear here as they progress." />
    </x-site.section>

    @if ($this->profile())
        <livewire:freelancer.highlights-section />
        <livewire:freelancer.services-section />
        <livewire:freelancer.work-history-section />
        <livewire:freelancer.consultations-section />
        <livewire:profile-revision-bar />
    @endif
</div>
