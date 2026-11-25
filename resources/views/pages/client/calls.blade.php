<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Calls')] class extends Component {
    //
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">Calls</flux:heading>
        <flux:text class="mt-1">Your consultations with freelancers.</flux:text>
    </div>

    <flux:callout icon="video-camera" heading="You have no upcoming calls.">
        Booked consultations will be listed here once consultation booking is available.
    </flux:callout>
</div>
