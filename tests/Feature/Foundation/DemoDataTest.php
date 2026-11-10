<?php

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Reports\Services\Reports;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as Router;
use Livewire\Component;

/**
 * Loads the demo company and opens every screen and printout for every record.
 * Lists with several rows are where a missing eager load shows up (strict mode
 * is on outside production), and single-record tests rarely have them.
 */
it('loads the demo company with balanced books', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoDataSeeder::class);

    $unbalanced = DB::table('journal_lines')->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
        ->select('journals.currency_code')->groupBy('journals.currency_code')->havingRaw('SUM(debit) <> SUM(credit)')->count();

    expect(User::query()->where('email', 'manager@demo.test')->exists())->toBeTrue()
        ->and($unbalanced)->toBe(0)
        ->and(DB::table('stock_balances')->where('quantity', '<', 0)->count())->toBe(0);

    // Running it again changes nothing.
    $this->seed(DemoDataSeeder::class);
    expect(User::query()->where('email', 'like', '%@demo.test')->count())->toBe(7);
});

it('opens every screen and printout of the demo company without a server error', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoDataSeeder::class);
    $admin = User::role(SyncPermissions::ADMIN)->firstOrFail();
    $this->actingAs($admin);

    $reportKeys = app(Reports::class)->forUser($admin)->flatten()->map(fn ($r) => $r->key())->all();
    $urls = [];

    foreach (Router::getRoutes()->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->getActionName(), 'App\\Modules\\')) {
            continue;
        }
        $params = $route->parameterNames();
        if ($params === []) {
            $urls[] = $route->uri();

            continue;
        }
        if ($params === ['key'] && str_starts_with($route->uri(), 'reports/')) {
            foreach ($reportKeys as $key) {
                $urls[] = str_replace('{key}', $key, $route->uri());
            }

            continue;
        }
        if ($params === ['run', 'line']) {
            foreach (PayrollLine::query()->get(['id', 'payroll_run_id']) as $line) {
                $urls[] = str_replace(['{run}', '{line}'], [$line->payroll_run_id, $line->id], $route->uri());
            }

            continue;
        }
        if (count($params) === 1 && ($model = boundModel($route, $params[0]))) {
            foreach ($model::query()->pluck((new $model)->getKeyName()) as $id) {
                $urls[] = str_replace('{'.$params[0].'}', (string) $id, $route->uri());
            }
        }
    }

    $failures = [];
    // Printouts are also produced as PDFs (a different renderer, with its own failures).
    $pdfs = array_map(fn ($u) => $u.'?pdf=1', array_values(array_filter($urls, fn ($u) => preg_match('#/(print|packing-list)$|/slip/#', $u))));

    foreach ([...$urls, ...$pdfs] as $url) {
        $response = $this->get('/'.$url);
        $status = $response->status();
        if ($status >= 500) {
            $failures[] = "{$status} /{$url}";
        } elseif (str_ends_with($url, '?pdf=1') && $status === 200 && ! str_contains((string) $response->headers->get('content-type'), 'pdf')) {
            $failures[] = "not a PDF: /{$url}";
        }
    }

    expect(count($urls))->toBeGreaterThan(300)
        ->and($failures)->toBe([]);
});

/** @return class-string<Model>|null */
function boundModel(Route $route, string $name): ?string
{
    $class = $route->getControllerClass();
    $parameters = $class && is_subclass_of($class, Component::class) && method_exists($class, 'mount')
        ? (new ReflectionMethod($class, 'mount'))->getParameters()
        : $route->signatureParameters(['subClass' => Model::class]);

    foreach ($parameters as $parameter) {
        $type = $parameter->getType();
        if ($parameter->getName() === $name && $type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class)) {
            return $type->getName();
        }
    }

    return null;
}
