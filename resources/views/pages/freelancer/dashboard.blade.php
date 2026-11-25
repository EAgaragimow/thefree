<?php

use App\Actions\Profiles\ConsultationSchedule;
use App\Actions\Profiles\ProfileContent;
use App\Enums\ConsultationType;
use App\Enums\PricingMode;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public function profile(): ?FreelancerProfile
    {
        return auth()->user()->freelancerProfile()->with('openRevision')->first();
    }

    /**
     * What the freelancer is working on: pending changes if there are any, otherwise what is live.
     */
    public function content(FreelancerProfile $profile): ProfileContent
    {
        return $profile->workingContent();
    }

    public function money(?int $cents): string
    {
        return '$'.number_format(($cents ?? 0) / 100, 2);
    }

    /**
     * @param  array{pricing_mode: string, price_cents: int|null}  $service
     */
    public function priceLabel(array $service): string
    {
        return match (PricingMode::from($service['pricing_mode'])) {
            PricingMode::Fixed => $this->money($service['price_cents']),
            PricingMode::StartingFrom => 'From '.$this->money($service['price_cents']),
            PricingMode::CustomQuote => 'Custom quote',
        };
    }

    /**
     * @param  array{started_at: string, ended_at: string|null}  $experience
     */
    public function period(array $experience): string
    {
        $ended = $experience['ended_at'] ? Carbon::parse($experience['ended_at'])->format('M Y') : 'present';

        return Carbon::parse($experience['started_at'])->format('M Y').' – '.$ended;
    }

    /**
     * Only the consultations that are switched on; a switched-off one is not offered to clients.
     *
     * @return list<array<string, mixed>>
     */
    public function offeredConsultations(ProfileContent $content): array
    {
        return array_values(array_filter($content->consultationOffers(), fn (array $offer): bool => $offer['enabled']));
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
                <div class="flex items-center gap-2">
                    <flux:badge color="{{ $this->statusColor($status) }}">{{ $this->statusLabel($status) }}</flux:badge>
                    @if ($profile->is_newbie)
                        <flux:badge color="sky">Newbie</flux:badge>
                    @endif
                </div>
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
        </flux:card>
    @endif

    @if ($profile = $this->profile())
        @php($content = $this->content($profile))
        @php($consultations = $this->offeredConsultations($content))

        <div class="grid gap-4 lg:grid-cols-2">
            <x-settings-group heading="Highlights" :href="route('freelancer.highlights')" :empty="$content->highlights() === []">
                <ul class="space-y-2">
                    @foreach ($content->highlights() as $highlight)
                        <li wire:key="highlight-{{ $highlight['uid'] }}">
                            <flux:heading size="sm">{{ $highlight['title'] }}</flux:heading>
                            @if ($highlight['description'])
                                <flux:text size="sm">{{ Str::limit($highlight['description'], 90) }}</flux:text>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-settings-group>

            <x-settings-group heading="Services" :href="route('freelancer.services')" :empty="$content->services() === []">
                <ul class="space-y-2">
                    @foreach ($content->services() as $service)
                        <li wire:key="service-{{ $service['uid'] }}" class="flex items-center justify-between gap-3">
                            <flux:heading size="sm">{{ $service['title'] }}</flux:heading>
                            <flux:badge size="sm">{{ $this->priceLabel($service) }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </x-settings-group>

            <x-settings-group heading="Work history" :href="route('freelancer.profile')" :empty="$content->experiences() === []">
                <ul class="space-y-2">
                    @foreach ($content->experiences() as $experience)
                        <li wire:key="experience-{{ $loop->index }}">
                            <flux:heading size="sm">{{ $experience['role'] }} · {{ $experience['company'] }}</flux:heading>
                            <flux:text size="sm">{{ $this->period($experience) }}</flux:text>
                        </li>
                    @endforeach
                </ul>
            </x-settings-group>

            <x-settings-group heading="Consultations" :href="route('freelancer.consultations')" :empty="$consultations === []">
                <ul class="space-y-3">
                    @foreach ($consultations as $offer)
                        <li wire:key="consultation-{{ $offer['type'] }}">
                            <div class="flex items-center justify-between gap-3">
                                <flux:heading size="sm">{{ ConsultationType::from($offer['type'])->label() }}</flux:heading>
                                <flux:badge size="sm">{{ $offer['price_cents'] > 0 ? $this->money($offer['price_cents']) : 'Free' }}</flux:badge>
                            </div>
                            <flux:text size="sm">{{ $offer['duration_minutes'] }} minutes · {{ implode(', ', ConsultationSchedule::summary($offer['availability'])) }}</flux:text>
                        </li>
                    @endforeach
                </ul>
            </x-settings-group>
        </div>
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
