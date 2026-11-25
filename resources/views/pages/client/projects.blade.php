<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Projects')] class extends Component {
    //
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">Projects</flux:heading>
        <flux:text class="mt-1">Your projects with freelancers.</flux:text>
    </div>

    <flux:callout icon="briefcase" heading="You have no projects yet.">
        Projects and proposals will be listed here once they are available.
    </flux:callout>
</div>
