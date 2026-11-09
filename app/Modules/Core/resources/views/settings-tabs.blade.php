{{-- Tabs shared by the company-wide settings pages. --}}
@php
    $tabs = [
        'core.settings.company' => 'Company',
        'core.settings.approvals' => 'Approvals',
        'core.settings.period-lock' => 'Period lock',
        'core.settings.payroll' => 'Payroll',
    ];
    foreach (app(\App\Support\Modules\ModuleRegistry::class)->all() as $module) {
        if (method_exists($module, 'settingsTabs')) {
            $tabs += $module->settingsTabs();
        }
    }
@endphp
<nav class="-mt-2 mb-6 flex gap-1 overflow-x-auto border-b border-line" aria-label="Settings sections">
    @foreach ($tabs as $route => $label)
        <a href="{{ route($route) }}" wire:navigate
           @class([
               'whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium',
               'border-brand-600 text-link' => request()->routeIs($route),
               'border-transparent text-subtle hover:text-fg' => ! request()->routeIs($route),
           ])>{{ $label }}</a>
    @endforeach
</nav>
