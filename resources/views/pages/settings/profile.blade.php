<?php

use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $timezone = 'UTC';

    public $avatar = null;

    public bool $saved = false;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->timezone = $user->timezone;
    }

    public function save(UpdateUserProfileInformation $updater): void
    {
        $this->saved = false;

        $this->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = auth()->user();

        $updater->update($user, [
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
        ]);

        if ($this->avatar) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->forceFill(['avatar_path' => $this->avatar->store('avatars', 'public')])->save();
            $this->avatar = null;
        }

        $this->saved = true;
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }

    /**
     * @return list<string>
     */
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }
};
?>

<x-settings-layout heading="Profile" subheading="Update your name, email address, photo and time zone.">
    <form wire:submit="save" class="space-y-6">
        @if ($saved)
            <flux:callout variant="success" icon="check-circle" heading="Your profile has been updated." />
        @endif

        <div class="flex items-center gap-4">
            <flux:avatar size="lg" :src="auth()->user()->avatarUrl()" :name="$name" />

            <div class="space-y-2">
                <input type="file" wire:model="avatar" accept="image/*" class="text-sm" />
                @error('avatar') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                @if (auth()->user()->avatar_path)
                    <flux:button size="sm" variant="ghost" wire:click="removeAvatar" type="button">Remove photo</flux:button>
                @endif
            </div>
        </div>

        <flux:field>
            <flux:label>Name</flux:label>
            <flux:input wire:model="name" required autocomplete="name" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" wire:model="email" required autocomplete="email" />
            <flux:error name="email" />
            <flux:description>Changing your email address requires you to verify it again.</flux:description>
        </flux:field>

        <flux:field>
            <flux:label>Time zone</flux:label>
            <flux:select wire:model="timezone">
                @foreach ($this->timezones() as $zone)
                    <flux:select.option value="{{ $zone }}">{{ $zone }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="timezone" />
        </flux:field>

        <flux:button type="submit" variant="primary">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </flux:button>
    </form>
</x-settings-layout>
