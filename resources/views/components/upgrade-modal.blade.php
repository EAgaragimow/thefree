{{--
    Shown when a Free or payment-restricted client clicks a freelancer card (SPEC §2). Open it with
    <flux:modal.trigger name="upgrade">. The button stays disabled until Stripe billing exists.
--}}
<flux:modal name="upgrade" class="md:w-96">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">Upgrade to the Paid Plan</flux:heading>
            <flux:text class="mt-2">
                Full freelancer profiles, the complete directory, new projects and consultation booking are part of the Paid Plan.
                Billing will be available soon.
            </flux:text>
        </div>

        <div class="flex">
            <flux:spacer />
            <flux:button variant="primary" disabled>Upgrade to Paid</flux:button>
        </div>
    </div>
</flux:modal>
