<?php

namespace App\Modules\Inventory\Console;

use App\Modules\Inventory\Services\StockLedger;
use Illuminate\Console\Command;

class RebuildBalances extends Command
{
    protected $signature = 'stock:rebuild-balances';

    protected $description = 'Recalculate the stock balance table from the stock movement ledger';

    public function handle(StockLedger $stock): int
    {
        $count = $stock->rebuildBalances();
        $this->info("{$count} stock buckets rebuilt from the movement ledger.");

        return self::SUCCESS;
    }
}
