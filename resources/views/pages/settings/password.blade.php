<?php

use App\Actions\Fortify\UpdateUserPassword;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Password settings')] class extends Component {
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $saved = false;

    public function save(UpdateUserPassword $updater): void
    {
        $this->saved = false;

        $updater->update(auth()->user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->saved = true;
    }
};
?>

<x-settings-layout heading="Password" subheading="Use a long, random password to keep your account secure.">
    <form wire:submit="save" class="space-y-6">
        @if ($saved)
            <flux:callout variant="success" icon="check-circle" heading="Your password has been updated." />
        @endif

        <flux:field>
            <flux:label>Current password</flux:label>
            <flux:input type="password" wire:model="current_password" required autocomplete="current-password" viewable />
            <flux:error name="current_password" />
        </flux:field>

        <flux:field>
            <flux:label>New password</flux:label>
            <flux:input type="password" wire:model="password" required autocomplete="new-password" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>Confirm new password</flux:label>
            <flux:input type="password" wire:model="password_confirmation" required autocomplete="new-password" viewable />
        </flux:field>

        <flux:button type="submit" variant="primary">Update password</flux:button>
    </form>
</x-settings-layout>
