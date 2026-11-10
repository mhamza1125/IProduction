<?php

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Finance\Livewire\Ledgers\Index as Ledgers;
use App\Modules\Finance\Services\LedgerSubjects;
use App\Modules\Reports\Livewire\Show as ReportScreen;
use App\Modules\Reports\Services\Reports;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Clicks Export (Excel and CSV) on every list, report and ledger of the demo
 * company. Exports format every row through column callbacks, which read
 * relations: with several rows a missing eager load fails here (strict mode).
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoDataSeeder::class);
    $this->admin = User::role(SyncPermissions::ADMIN)->firstOrFail();
    $this->actingAs($this->admin);
});

/** @return list<class-string<Component>> list screens with an export action and no required mount arguments */
function exportableScreens(): array
{
    $classes = [];
    foreach (glob(base_path('app/Modules/*/Livewire/{,*/,*/*/}*.php'), GLOB_BRACE) ?: [] as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(base_path('app/'))));
        if (! class_exists($class) || ! is_subclass_of($class, Component::class) || (new ReflectionClass($class))->isAbstract() || ! method_exists($class, 'export')) {
            continue;
        }
        $mount = method_exists($class, 'mount') ? new ReflectionMethod($class, 'mount') : null;
        if ($mount && $mount->getNumberOfRequiredParameters() > 0) {
            continue;
        }
        $classes[] = $class;
    }

    return $classes;
}

it('exports every list to Excel and CSV', function () {
    $screens = array_values(array_filter(exportableScreens(), fn ($c) => $c !== Ledgers::class));
    expect(count($screens))->toBeGreaterThan(20);

    $failures = [];
    foreach ($screens as $class) {
        foreach (['xlsx', 'csv'] as $format) {
            try {
                Livewire::test($class)->call('export', $format)->assertFileDownloaded();
            } catch (Throwable $e) {
                $failures[] = class_basename(dirname(str_replace('\\', '/', $class))).'/'.class_basename($class)." {$format}: ".$e->getMessage();
            }
        }
    }

    expect($failures)->toBe([]);
});

it('exports every report to Excel and CSV', function () {
    $failures = [];
    foreach (app(Reports::class)->forUser($this->admin)->flatten() as $report) {
        foreach (['xlsx', 'csv'] as $format) {
            try {
                Livewire::test(ReportScreen::class, ['key' => $report->key()])->call('export', $format)->assertFileDownloaded();
            } catch (Throwable $e) {
                $failures[] = $report->key()." {$format}: ".$e->getMessage();
            }
        }
    }

    expect($failures)->toBe([]);
});

it('opens, prints and exports the ledger of every customer, vendor, employee, contractor and account', function () {
    $failures = [];
    foreach (LedgerSubjects::TYPES as $type => [, $class]) {
        foreach ($class::query()->pluck('id') as $id) {
            $currencies = $type === 'gl' ? ['PKR', 'USD', 'EUR'] : ['PKR'];
            foreach ($currencies as $currency) {
                $where = "{$type} #{$id} {$currency}";
                $status = $this->get(route('finance.ledgers.print', ['type' => $type, 'id' => $id, 'currency' => $currency, 'from' => '', 'to' => '']))->status();
                if ($status !== 200) {
                    $failures[] = "{$where} print: HTTP {$status}";
                }
                try {
                    Livewire::withQueryParams(['type' => $type, 'id' => $id, 'currency' => $currency])
                        ->test(Ledgers::class)->assertOk()->call('export', 'csv')->assertFileDownloaded();
                } catch (Throwable $e) {
                    $failures[] = "{$where} screen/export: ".$e->getMessage();
                }
            }
        }
    }

    expect($failures)->toBe([]);
});

it('sorts every list by every sortable column, and searches and filters by date', function () {
    $failures = [];
    foreach (exportableScreens() as $class) {
        if ($class === Ledgers::class) {
            continue;
        }
        $name = class_basename(dirname(str_replace('\\', '/', $class))).'/'.class_basename($class);
        try {
            $screen = Livewire::test($class);
            preg_match_all("/sortBy\\('([a-z_.]+)'\\)/", $screen->html(), $m);
            foreach (array_unique($m[1]) as $field) {
                try {
                    $screen->call('sortBy', $field)->assertOk()->call('sortBy', $field)->assertOk();
                } catch (Throwable $e) {
                    $failures[] = "{$name} sort {$field}: ".$e->getMessage();
                }
            }
            $screen->set('search', 'a')->assertOk()->set('search', 'SO-26')->assertOk()->set('search', '');
            if (property_exists($class, 'dateFrom')) {
                $screen->set('dateFrom', '2026-10-01')->set('dateTo', '2026-10-31')->assertOk();
            }
        } catch (Throwable $e) {
            $failures[] = "{$name}: ".$e->getMessage();
        }
    }

    expect($failures)->toBe([]);
});
