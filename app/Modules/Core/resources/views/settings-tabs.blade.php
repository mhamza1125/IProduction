{{-- Tabs shared by the company-wide settings pages. --}}
@php
    $tabs = [
        'core.settings.company' => 'Company',
        'core.settings.approvals' => 'Approvals',
        'core.settings.period-lock' => 'Period lock',
    ];
    foreach (app(\App\Support\Modules\ModuleRegistry::class)->all() as $module) {
        if (method_exists($module, 'settingsTabs')) {
            $tabs += $module->settingsTabs();
        }
    }
@endphp
<nav class="-mt-2 mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800" aria-label="Settings sections">
    @foreach ($tabs as $route => $label)
        <a href="{{ route($route) }}" wire:navigate
           @class([
               'whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium',
               'border-brand-600 text-brand-700 dark:text-brand-500' => request()->routeIs($route),
               'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => ! request()->routeIs($route),
           ])>{{ $label }}</a>
    @endforeach
</nav>
