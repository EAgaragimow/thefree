@props([
    'profile',
    'href' => null,
    'locked' => false,
    'favorited' => false,
    'canFavorite' => false,
])

{{--
    A freelancer in the directory. With an `href` the whole card opens the profile. A `locked` card
    (Free and payment-restricted clients) opens the upgrade modal instead and shows no rates.
    The heart saves the freelancer for Paid clients: the host page must define toggleFavorite($id).
--}}
@php
    use Illuminate\Support\Facades\Storage;

    $photo = $profile->photo_path ? Storage::disk('public')->url($profile->photo_path) : null;
    $name = $profile->user->name;
    $overlay = 'text-start after:absolute after:inset-0 after:rounded-lg focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-blue-500';
@endphp

<div {{ $attributes->class('relative flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500') }}>
    <div class="flex items-start gap-3">
        <flux:avatar size="lg" :src="$photo" :name="$name" />

        <div class="min-w-0 flex-1">
            @if ($href)
                <a href="{{ $href }}" class="block truncate font-medium {{ $overlay }}">{{ $name }}</a>
            @elseif ($locked)
                <flux:modal.trigger name="upgrade">
                    <button type="button" class="block w-full truncate font-medium {{ $overlay }}">{{ $name }}</button>
                </flux:modal.trigger>
            @else
                <span class="block truncate font-medium">{{ $name }}</span>
            @endif

            @if ($profile->professional_title)
                <flux:text class="truncate">{{ $profile->professional_title }}</flux:text>
            @endif
        </div>

        @if ($canFavorite)
            <button
                type="button"
                wire:click="toggleFavorite({{ $profile->id }})"
                class="relative z-10 -m-1 rounded p-1 text-zinc-400 hover:text-red-500 {{ $favorited ? 'text-red-500' : '' }}"
                aria-label="{{ $favorited ? 'Remove '.$name.' from favorites' : 'Save '.$name.' to favorites' }}"
                aria-pressed="{{ $favorited ? 'true' : 'false' }}"
            >
                <flux:icon.heart :variant="$favorited ? 'solid' : 'outline'" class="size-5" />
            </button>
        @endif
    </div>

    <div class="flex flex-wrap gap-1.5">
        @if ($profile->is_newbie)
            <flux:badge color="sky" size="sm">Newbie</flux:badge>
        @endif
        @if ($profile->professionalFunction)
            <flux:badge color="blue" size="sm">{{ $profile->professionalFunction->name }}</flux:badge>
        @endif
        @if ($profile->mainRole)
            <flux:badge color="indigo" size="sm">{{ $profile->mainRole->name }}</flux:badge>
        @endif
    </div>

    @if ($profile->skills->isNotEmpty())
        <flux:text size="sm" class="line-clamp-2">{{ $profile->skills->take(4)->pluck('name')->implode(' · ') }}</flux:text>
    @endif

    <div class="mt-auto flex items-center justify-between gap-2 text-sm text-zinc-500">
        <span class="truncate">{{ $profile->location }}</span>
        <span class="flex shrink-0 items-center gap-3">
            <span title="Freelancer score">{{ number_format($profile->score) }} pts</span>
            @unless ($locked)
                @if ($profile->hourly_rate_cents)
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">${{ number_format($profile->hourly_rate_cents / 100) }}/h</span>
                @endif
            @endunless
        </span>
    </div>
</div>
