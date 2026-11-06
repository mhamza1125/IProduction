<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Core\Models\Lookup;
use App\Modules\Finance\Database\Seeders\ChartOfAccountsSeeder;
use App\Modules\Finance\Database\Seeders\FinanceSeeder;
use App\Modules\Finance\Livewire\Accounts\Index as Accounts;
use App\Modules\Finance\Livewire\Ledgers\Index as LedgerPage;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\Journal;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Inventory\Actions\PostStockAdjustment;
use App\Modules\Inventory\Database\Seeders\InventorySeeder;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Vendor;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CoreSeeder::class, ChartOfAccountsSeeder::class, FinanceSeeder::class, InventorySeeder::class]);
    $this->actingAs(admin());
    $this->poster = app(JournalPoster::class);
    $this->ledgers = app(Ledgers::class);
});

function post(string $date, string $currency, array $lines, string $description = 'Test'): Journal
{
    return DB::transaction(function () use ($date, $currency, $lines, $description) {
        $entry = new JournalEntry(Carbon::parse($date), $currency, $description);
        foreach ($lines as [$side, $account, $amount, $ledgerable]) {
            $entry->{$side}($account, $amount, $ledgerable ?? null);
        }

        return app(JournalPoster::class)->post($entry);
    });
}

it('posts only balanced journals', function () {
    expect(fn () => post('2026-11-01', 'PKR', [['debit', 'inventory', '100', null], ['credit', 'accounts_payable', '90', null]]))
        ->toThrow(DocumentException::class, 'does not balance');

    $journal = post('2026-11-01', 'PKR', [['debit', 'inventory', '100', null], ['credit', 'accounts_payable', '100', null]]);

    expect($journal->number)->toBe('JNL-26-0001')->and($journal->lines()->count())->toBe(2)
        ->and(Journal::count())->toBe(1);
});

it('keeps journals immutable and corrects them with a reversal', function () {
    $vendor = Vendor::factory()->create();
    $journal = post('2026-11-01', 'PKR', [['debit', 'inventory', '1500', null], ['credit', 'accounts_payable', '1500', $vendor]]);

    expect(fn () => $journal->update(['description' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $journal->lines()->first()->delete())->toThrow(LogicException::class);

    expect($this->ledgers->partyBalance($vendor))->toBe('-1500.00');

    $reversal = DB::transaction(fn () => $this->poster->reverse($journal, Carbon::parse('2026-11-02'), 'Wrong vendor'));

    expect($reversal->reverses_journal_id)->toBe($journal->id)
        ->and($this->ledgers->partyBalance($vendor))->toBe('0.00')
        ->and(fn () => DB::transaction(fn () => $this->poster->reverse($journal, now(), 'again')))->toThrow(DocumentException::class, 'already reversed');
});

it('keeps foreign accounts in their own currency', function () {
    $usd = app(MoneyAccounts::class)->create(['name' => 'Meezan USD', 'type' => 'bank', 'currency_code' => 'USD']);
    $pkr = app(MoneyAccounts::class)->create(['name' => 'HBL Current', 'type' => 'bank', 'currency_code' => 'PKR']);

    expect($usd->glAccount->code)->toBe('1250-01')->and($pkr->glAccount->code)->toBe('1200-01')
        ->and(MoneyAccount::cashBook()->glAccount->code)->toBe('1100-01');

    expect(fn () => post('2026-11-01', 'PKR', [['debit', $usd, '800', null], ['credit', 'owner_capital', '800', null]]))
        ->toThrow(DocumentException::class, 'USD account');

    post('2026-11-01', 'USD', [['debit', $usd, '800', null], ['credit', 'owner_capital', '800', null]]);

    expect($this->ledgers->moneyBalance($usd))->toBe('800.00');
});

it('builds a ledger with opening, running and closing balances', function () {
    $customer = Customer::factory()->create(['currency_code' => 'USD']);
    post('2026-10-15', 'USD', [['debit', 'accounts_receivable', '1000', $customer], ['credit', 'sales', '1000', null]]);
    post('2026-11-03', 'USD', [['debit', 'accounts_receivable', '500', $customer], ['credit', 'sales', '500', null]]);
    post('2026-11-10', 'USD', [['debit', 'owner_capital', '200', null], ['credit', 'accounts_receivable', '200', $customer]]);

    $ledger = $this->ledgers->partyLedger($customer, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));

    expect($ledger['opening'])->toBe('1000.00')
        ->and($ledger['rows']->pluck('balance')->all())->toBe(['1500.00', '1300.00'])
        ->and($ledger['closing'])->toBe('1300.00')
        ->and($ledger['debits'])->toBe('500.00')->and($ledger['credits'])->toBe('200.00');

    Livewire::test(LedgerPage::class)->set('type', 'customer')->set('id', $customer->id)
        ->assertSee('1,300.00')->call('export', 'csv')->assertFileDownloaded();

    $this->get(route('finance.ledgers.print', ['type' => 'customer', 'id' => $customer->id]))->assertOk()->assertSee('Closing balance');
    $this->get(route('people.customers.show', $customer))->assertSee('1,300.00')->assertSee('They owe us');
});

it('posts the stock adjustment journal with the stock in one transaction', function () {
    $variant = Item::factory()->rawMaterial('kg')->create()->variants()->first();
    $adjustment = DB::transaction(function () use ($variant) {
        $adj = StockAdjustment::create(['number' => 'ADJ-26-0001', 'adjustment_date' => '2026-11-01', 'reason_id' => Lookup::where('type', 'adjustment_reason')->value('id')]);
        $adj->lines()->create(['item_variant_id' => $variant->id, 'status' => 'available', 'location_id' => Location::mainStore()->id, 'quantity' => '10', 'unit_cost' => '150']);

        return $adj;
    });
    app(DocumentWorkflow::class)->approve($adjustment);
    app(PostStockAdjustment::class)($adjustment);

    $inventory = GlAccount::system('inventory');
    $adjustmentAccount = GlAccount::system('stock_adjustment');

    expect($this->ledgers->accountBalance($inventory))->toBe('1500.00')
        ->and($this->ledgers->accountBalance($adjustmentAccount))->toBe('-1500.00')
        ->and(Journal::first()->source_id)->toBe($adjustment->id);
});

it('stops payments that would overdraw an account', function () {
    $cash = MoneyAccount::cashBook();
    post('2026-11-01', 'PKR', [['debit', $cash, '5000', null], ['credit', 'owner_capital', '5000', null]]);

    DB::transaction(fn () => app(MoneyAccounts::class)->assertCanPay($cash, '5000'));

    expect(fn () => DB::transaction(fn () => app(MoneyAccounts::class)->assertCanPay($cash, '5000.01')))
        ->toThrow(DocumentException::class, 'cannot pay');
});

it('protects the Cash Book and locks an account currency once used', function () {
    $cash = MoneyAccount::cashBook();

    Livewire::test(Accounts::class)->call('edit', $cash->id)->set('is_active', false)->set('type', 'bank')->call('save');
    expect($cash->fresh())->is_active->toBeTrue()->type->toBe('cash');

    post('2026-11-01', 'PKR', [['debit', $cash, '10', null], ['credit', 'owner_capital', '10', null]]);
    Livewire::test(Accounts::class)->call('edit', $cash->id)->set('currency_code', 'USD')->call('save')->assertHasErrors('currency_code');
});

it('hides bank balances and ledgers without permission', function () {
    $cash = MoneyAccount::cashBook();
    post('2026-11-01', 'PKR', [['debit', $cash, '987654', null], ['credit', 'owner_capital', '987654', null]]);

    $this->actingAs(userWith(['accounts.view']));
    $this->get(route('finance.accounts.index'))->assertOk()->assertDontSee('987,654');
    $this->get(route('finance.ledgers'))->assertForbidden();

    $this->actingAs(userWith(['accounts.view', 'bank_balance.view', 'ledgers.view']));
    $this->get(route('finance.accounts.index'))->assertSee('987,654.00');
    $this->get(route('finance.ledgers', ['type' => 'account', 'id' => $cash->id]))->assertOk()->assertSee('987,654.00');

    $this->actingAs(userWith(['ledgers.view']));
    $this->get(route('finance.ledgers', ['type' => 'account', 'id' => $cash->id]))->assertForbidden();
});

it('renders the finance pages', function () {
    $journal = post('2026-11-01', 'PKR', [['debit', 'inventory', '1', null], ['credit', 'accounts_payable', '1', null]]);

    foreach ([route('finance.accounts.index'), route('finance.ledgers'), route('finance.journals.index'), route('finance.journals.show', $journal), route('finance.chart-of-accounts')] as $url) {
        $this->get($url)->assertOk();
    }
});
