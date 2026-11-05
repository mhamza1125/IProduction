<x-layouts::app title="Dashboard">
    <x-page-header title="Dashboard" description="Welcome back, {{ auth()->user()->name }}." />

    <x-card>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            The base system is ready. Business modules (Inventory, Sales, Purchase, Production, Accounts…) will add their own widgets here.
        </p>
    </x-card>
</x-layouts::app>
