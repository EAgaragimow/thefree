<?php

use App\Actions\Applications\ActivateFreelancerAccount;
use App\Models\FreelancerApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.public'), Title('Activate your account')] class extends Component {
    #[Locked]
    public string $token = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function application(): ?FreelancerApplication
    {
        $application = FreelancerApplication::query()
            ->where('invitation_token_hash', hash('sha256', $this->token))
            ->first();

        return $application?->invitationIsUsable() ? $application : null;
    }

    public function activate(ActivateFreelancerAccount $activate): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        $user = $activate($this->token, $this->password);

        Auth::guard('web')->login($user);
        session()->regenerate();

        $this->redirectRoute('freelancer.profile', navigate: false);
    }
};
?>

<div class="mx-auto w-full max-w-md py-12">
    @if ($application = $this->application())
        <flux:heading size="xl" level="1">Activate your account</flux:heading>
        <flux:text class="mt-2">Welcome, {{ $application->full_name }}. Choose a password to finish setting up your freelancer account.</flux:text>

        <form wire:submit="activate" class="mt-6 space-y-6">
            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input value="{{ $application->email }}" readonly disabled />
            </flux:field>

            <flux:field>
                <flux:label>Password</flux:label>
                <flux:input type="password" wire:model="password" required autocomplete="new-password" viewable />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>Confirm password</flux:label>
                <flux:input type="password" wire:model="password_confirmation" required autocomplete="new-password" viewable />
            </flux:field>

            <flux:error name="token" />

            <flux:button type="submit" variant="primary" class="w-full">Activate account</flux:button>
        </form>
    @else
        <flux:callout variant="danger" icon="exclamation-triangle" heading="This invitation link is invalid or has expired.">
            If you were approved, please contact support to request a new invitation.
        </flux:callout>
    @endif
</div>
