<?php

use App\Actions\Profiles\DiscardProfileRevision;
use App\Actions\Profiles\SubmitProfileForReview;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Review state of the freelancer's pending profile changes, shared by every profile editing page.
 * Pages dispatch `profile-content-saved` after they save; this component dispatches
 * `profile-revision-changed` when the revision is submitted or discarded so pages can reload.
 */
new class extends Component {
    public ?string $notice = null;

    public function profile(): FreelancerProfile
    {
        return FreelancerProfile::query()
            ->with('openRevision')
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    #[On('profile-content-saved')]
    public function refreshStatus(): void
    {
        $this->notice = null;
        $this->resetErrorBag();
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    public function submit(SubmitProfileForReview $submit): void
    {
        $this->notice = null;
        $this->resetErrorBag();

        try {
            $submit($this->profile());
        } catch (\DomainException $exception) {
            $this->addError('submit', $exception->getMessage());

            return;
        }

        $this->notice = 'Your profile has been submitted for review.';
        $this->dispatch('profile-revision-changed');
    }

    public function discard(DiscardProfileRevision $discard): void
    {
        $this->notice = null;
        $this->resetErrorBag();

        try {
            $discard($this->profile());
        } catch (\DomainException $exception) {
            $this->addError('discard', $exception->getMessage());

            return;
        }

        $this->notice = 'Your changes have been discarded.';
        $this->dispatch('profile-revision-changed');
    }
};
?>

<div>
    @php($profile = $this->profile())
    @php($revision = $profile->openRevision)

    {{-- The panel appears at the bottom of the screen as soon as there is something to submit or discard, or a result to read. --}}
    @if ($notice !== null || $errors->any() || $revision?->status->isEditable())
        <div class="h-40" aria-hidden="true"></div>

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white shadow-[0_-4px_16px_rgba(0,0,0,0.08)]">
            <div class="mx-auto max-h-[60vh] w-full max-w-7xl space-y-3 overflow-y-auto px-4 py-4 sm:px-6">
    @if ($notice)
        <flux:callout variant="success" icon="check-circle" heading="{{ $notice }}">
            <x-slot name="controls">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="dismissNotice" aria-label="Close" />
            </x-slot>
        </flux:callout>
    @endif

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" heading="Your profile cannot be submitted yet">
            <ul class="list-disc ps-5">
                @foreach (collect($errors->all())->unique() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    @if ($revision?->status === ProfileStatus::ChangesRequested && $revision->last_change_request)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="An administrator requested changes">
            {{ $revision->last_change_request }}
        </flux:callout>
    @endif

    @if ($revision?->status === ProfileStatus::PendingReview)
        <flux:callout icon="clock" heading="Your profile is under review">
            You cannot edit it until the review is complete.
        </flux:callout>
    @elseif ($revision && $profile->isPublished())
        <flux:callout icon="information-circle" heading="Your last approved version is still live">
            Your changes become visible to clients after an administrator approves them.
        </flux:callout>
    @elseif ($revision)
        <flux:callout icon="information-circle" heading="Your profile is not visible to clients yet">
            Submit it for review when it is ready.
        </flux:callout>
    @endif

    @if ($revision?->status->isEditable())
        <div class="flex flex-wrap gap-3">
            <flux:button type="button" variant="primary" wire:click="submit" wire:confirm="Submit your profile for review? You will not be able to edit it until the review is complete.">
                Submit for review
            </flux:button>

            @if ($profile->isPublished())
                <flux:button type="button" variant="ghost" wire:click="discard" wire:confirm="Discard your changes and go back to the published version of your profile?">
                    Discard changes
                </flux:button>
            @endif
        </div>
    @endif
            </div>
        </div>
    @endif
</div>
