<x-layouts::guest>
    <x-slot:heading>Two-factor authentication</x-slot:heading>

    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf
            <div x-show="! recovery">
                <p class="mb-3 text-sm text-muted">Enter the 6-digit code from your authenticator app.</p>
                <x-input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <p class="mb-3 text-sm text-muted">Enter one of your emergency recovery codes.</p>
                <x-input label="Recovery code" name="recovery_code" autocomplete="one-time-code" x-bind:disabled="! recovery" />
            </div>
            <x-button type="submit" class="w-full">Verify</x-button>
        </form>

        <button type="button" x-on:click="recovery = ! recovery" class="mt-4 w-full text-center text-sm text-link hover:underline">
            <span x-show="! recovery">Use a recovery code</span>
            <span x-show="recovery" x-cloak>Use an authenticator code</span>
        </button>
    </div>
</x-layouts::guest>
