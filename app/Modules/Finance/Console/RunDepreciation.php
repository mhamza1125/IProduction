<?php

namespace App\Modules\Finance\Console;

use App\Modules\Core\Services\Settings;
use App\Modules\Finance\Services\Depreciation;
use App\Support\Documents\DocumentException;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Posts a month's depreciation (default: last month). Scheduled on the 1st of
 * each month only when "finance.auto_depreciation" is switched on in settings;
 * --scheduled makes the command respect that switch.
 */
class RunDepreciation extends Command
{
    protected $signature = 'assets:depreciate {month? : yyyy-mm, default last month} {--scheduled : skip unless automatic depreciation is on}';

    protected $description = 'Post straight-line depreciation for a month';

    public function handle(Depreciation $depreciation, Settings $settings): int
    {
        if ($this->option('scheduled') && ! $settings->get('finance.auto_depreciation')) {
            $this->line('Automatic depreciation is off (Settings › Company).');

            return self::SUCCESS;
        }

        $month = $this->argument('month')
            ? Carbon::createFromFormat('Y-m', (string) $this->argument('month'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        try {
            $journal = $depreciation->run($month);
        } catch (DocumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($journal ? "Depreciation for {$month->format('F Y')} posted as {$journal->number}." : "Nothing to depreciate for {$month->format('F Y')}.");

        return self::SUCCESS;
    }
}
