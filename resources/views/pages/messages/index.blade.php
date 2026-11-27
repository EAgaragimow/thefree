<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Messages')] class extends Component {
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">Messages</flux:heading>
        <flux:text class="mt-1">Your conversations with {{ auth()->user()->isFreelancer() ? 'brands' : 'freelancers' }} will appear here.</flux:text>
    </div>

    <flux:callout icon="chat-bubble-left-right" heading="No messages to show">
        Messaging is coming soon. Incoming messages and ongoing conversations will be listed on this page.
    </flux:callout>
</div>
