<x-auth-card title="Reset your password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="email" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>New password</flux:label>
            <flux:input type="password" name="password" required autocomplete="new-password" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>Confirm new password</flux:label>
            <flux:input type="password" name="password_confirmation" required autocomplete="new-password" viewable />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">Reset password</flux:button>
    </form>
</x-auth-card>
