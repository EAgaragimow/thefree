@props([
    'content',
    'user',
    'professionalFunction' => null,
    'roles' => [],
    'skills' => [],
    'tools' => [],
    'industries' => [],
])

@php
    use App\Actions\Profiles\ConsultationSchedule;
    use App\Actions\Profiles\VideoEmbed;
    use App\Enums\ConsultationType;
    use App\Enums\PricingMode;
    use Illuminate\Support\Facades\Storage;

    $url = fn (?string $path): ?string => $path ? Storage::disk('public')->url($path) : null;
    $safeLink = fn (?string $link): ?string => filled($link) && preg_match('#^https?://#i', $link) === 1 ? $link : null;
    $money = fn (?int $cents): string => '$'.number_format(($cents ?? 0) / 100, 2);
    $priceLabel = fn (array $service): string => match (PricingMode::from($service['pricing_mode'])) {
        PricingMode::Fixed => $money($service['price_cents']),
        PricingMode::StartingFrom => 'From '.$money($service['price_cents']),
        PricingMode::CustomQuote => 'Custom quote',
    };
    $socialLabels = ['linkedin' => 'LinkedIn', 'twitter' => 'X / Twitter', 'instagram' => 'Instagram'];
@endphp

<article {{ $attributes->class('space-y-10') }}>
    <header class="flex flex-wrap items-center gap-5">
        <flux:avatar size="xl" :src="$url($content->get('photo_path'))" :name="$user->name" />

        <div class="min-w-0">
            <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
            @if ($content->get('professional_title'))
                <flux:text class="text-lg">{{ $content->get('professional_title') }}</flux:text>
            @endif
            <flux:text class="mt-1">
                {{ collect([$content->get('location'), $content->get('timezone')])->filter()->implode(' · ') }}
            </flux:text>

            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                @if ($safeLink($content->get('website')))
                    <a href="{{ $safeLink($content->get('website')) }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">Website</a>
                @endif
                @foreach ($content->get('social_links') as $network => $link)
                    @if ($safeLink($link))
                        <a href="{{ $safeLink($link) }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">{{ $socialLabels[$network] ?? ucfirst($network) }}</a>
                    @endif
                @endforeach
            </div>
        </div>
    </header>

    @if ($content->get('about'))
        <section class="space-y-2">
            <flux:heading size="lg">About</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $content->get('about') }}</flux:text>
        </section>
    @endif

    @if ($professionalFunction || count($roles))
        <section class="flex flex-wrap gap-2">
            @if ($professionalFunction)
                <flux:badge color="blue">{{ $professionalFunction }}</flux:badge>
            @endif
            @foreach ($roles as $role)
                <flux:badge color="indigo">{{ $role }}</flux:badge>
            @endforeach
        </section>
    @endif

    @php($hourlyRate = $content->get('hourly_rate_cents'))
    @php($weeklyHours = \App\Enums\WeeklyHours::tryFrom((string) $content->get('weekly_hours')))

    @if ($hourlyRate || $weeklyHours || $content->get('offers_monthly_retainers') !== null || $content->get('open_to_full_time') !== null)
        <section class="space-y-3">
            <flux:heading size="lg">Rates and availability</flux:heading>

            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                @if ($hourlyRate)
                    <div>
                        <dt class="text-zinc-500">Hourly rate</dt>
                        <dd class="font-medium">{{ $money($hourlyRate) }} / hour</dd>
                    </div>
                @endif
                @if ($weeklyHours)
                    <div>
                        <dt class="text-zinc-500">Availability</dt>
                        <dd class="font-medium">{{ $weeklyHours->label() }}</dd>
                    </div>
                @endif
                @if ($content->get('open_to_full_time') !== null)
                    <div>
                        <dt class="text-zinc-500">Open to full-time opportunities</dt>
                        <dd class="font-medium">{{ $content->get('open_to_full_time') ? 'Yes' : 'No' }}</dd>
                    </div>
                @endif
                @if ($content->get('offers_monthly_retainers') !== null)
                    <div>
                        <dt class="text-zinc-500">Monthly retainers</dt>
                        <dd class="font-medium">
                            {{ $content->get('offers_monthly_retainers')
                                ? collect(['Yes', $content->get('monthly_retainers_from_cents') ? 'from '.$money($content->get('monthly_retainers_from_cents')).' / month' : null])->filter()->implode(', ')
                                : 'No' }}
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($content->get('offers_monthly_retainers') && $content->get('monthly_retainers_description'))
                <flux:text class="whitespace-pre-line">{{ $content->get('monthly_retainers_description') }}</flux:text>
            @endif
        </section>
    @endif

    @if (count($content->get('best_fit')))
        <section class="space-y-2">
            <flux:heading size="lg">Best fit for</flux:heading>
            <ul class="list-disc space-y-1 ps-5 text-sm">
                @foreach ($content->get('best_fit') as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @foreach (['Skills' => $skills, 'Tools and platforms' => $tools, 'Industry experience' => $industries] as $heading => $names)
        @if (count($names))
            <section class="space-y-2">
                <flux:heading size="lg">{{ $heading }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($names as $name)
                        <flux:badge>{{ $name }}</flux:badge>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @php($offeredConsultations = array_values(array_filter($content->consultationOffers(), fn (array $offer): bool => $offer['enabled'])))

    @if ($offeredConsultations !== [])
        <section class="space-y-3">
            <div>
                <flux:heading size="lg">Consultations</flux:heading>
                <flux:text>Available times are shown in {{ $content->get('timezone') }}.</flux:text>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($offeredConsultations as $offer)
                    <flux:card wire:key="consultation-{{ $offer['type'] }}">
                        <div class="flex items-center justify-between gap-3">
                            <flux:heading>{{ ConsultationType::from($offer['type'])->label() }}</flux:heading>
                            <flux:badge>{{ $offer['price_cents'] > 0 ? $money($offer['price_cents']) : 'Free' }}</flux:badge>
                        </div>
                        <flux:text class="mt-1">{{ $offer['duration_minutes'] }} minutes</flux:text>
                        @if ($offer['description'])
                            <flux:text class="mt-1">{{ $offer['description'] }}</flux:text>
                        @endif
                        <ul class="mt-2 space-y-0.5 text-sm text-zinc-600 dark:text-zinc-400">
                            @foreach (ConsultationSchedule::summary($offer['availability']) as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </flux:card>
                @endforeach
            </div>
        </section>
    @endif

    @if ($content->services() !== [])
        <section class="space-y-3">
            <div>
                <flux:heading size="lg">Services</flux:heading>
                <flux:text>Services are by request: send a request to agree on scope, price and deadline.</flux:text>
            </div>

            <div class="space-y-3">
                @foreach ($content->services() as $service)
                    <flux:card wire:key="service-{{ $service['uid'] }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <flux:heading>{{ $service['title'] }}</flux:heading>
                            <flux:badge>{{ $priceLabel($service) }}</flux:badge>
                        </div>
                        @if ($service['description'])
                            <flux:text class="mt-1 whitespace-pre-line">{{ $service['description'] }}</flux:text>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        </section>
    @endif

    @if ($content->highlights() !== [])
        <section class="space-y-3">
            <flux:heading size="lg">Highlights</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($content->highlights() as $highlight)
                    <flux:modal.trigger name="highlight-{{ $highlight['uid'] }}" wire:key="highlight-card-{{ $highlight['uid'] }}">
                        <button type="button" class="group overflow-hidden rounded-lg border border-zinc-200 text-start transition hover:border-zinc-400 dark:border-zinc-700">
                            @if ($url($highlight['cover_path']))
                                <img src="{{ $url($highlight['cover_path']) }}" alt="" class="h-36 w-full object-cover">
                            @else
                                <div class="grid h-36 w-full place-items-center bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon.photo class="size-8 text-zinc-400" />
                                </div>
                            @endif
                            <div class="p-3">
                                <flux:heading>{{ $highlight['title'] }}</flux:heading>
                            </div>
                        </button>
                    </flux:modal.trigger>

                    <flux:modal name="highlight-{{ $highlight['uid'] }}" class="md:w-[48rem]">
                        <div class="space-y-5">
                            <flux:heading size="lg">{{ $highlight['title'] }}</flux:heading>

                            @if ($highlight['description'])
                                <flux:text class="whitespace-pre-line">{{ $highlight['description'] }}</flux:text>
                            @endif

                            @if ($highlight['gallery'] !== [])
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($highlight['gallery'] as $image)
                                        <img src="{{ $url($image) }}" alt="" loading="lazy" class="w-full rounded object-cover">
                                    @endforeach
                                </div>
                            @endif

                            @foreach ($highlight['video_urls'] as $video)
                                @if ($embed = VideoEmbed::embedUrl($video))
                                    <div class="aspect-video overflow-hidden rounded">
                                        <iframe src="{{ $embed }}" title="{{ $highlight['title'] }}" loading="lazy" class="size-full" allow="fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"></iframe>
                                    </div>
                                @endif
                            @endforeach

                            @if ($highlight['links'] !== [])
                                <ul class="list-disc space-y-1 ps-5">
                                    @foreach ($highlight['links'] as $link)
                                        @if ($safeLink($link['url']))
                                            <li><a href="{{ $safeLink($link['url']) }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">{{ $link['label'] ?: $link['url'] }}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </flux:modal>
                @endforeach
            </div>
        </section>
    @endif

    @if ($content->experiences() !== [])
        <section class="space-y-3">
            <flux:heading size="lg">Experience</flux:heading>

            <ul class="space-y-3">
                @foreach ($content->experiences() as $experience)
                    <li>
                        <flux:heading>{{ $experience['role'] }} · {{ $experience['company'] }}</flux:heading>
                        <flux:text>
                            {{ \Illuminate\Support\Carbon::parse($experience['started_at'])->format('M Y') }}
                            – {{ $experience['ended_at'] ? \Illuminate\Support\Carbon::parse($experience['ended_at'])->format('M Y') : 'present' }}
                        </flux:text>
                        @if ($experience['description'])
                            <flux:text class="mt-1 whitespace-pre-line">{{ $experience['description'] }}</flux:text>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</article>
