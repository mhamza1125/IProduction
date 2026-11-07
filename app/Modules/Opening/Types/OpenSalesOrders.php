<?php

namespace App\Modules\Opening\Types;

use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Modules\People\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Decimal;
use App\Support\Documents\DocumentWorkflow;
use Illuminate\Support\Collection;

/** Open sales orders with their remaining quantities and prices; rows with the same order_ref form one order. */
class OpenSalesOrders extends BaseType
{
    /** @var array<string, int> */
    private array $refCustomer = [];

    public function key(): string
    {
        return 'open_sales_orders';
    }

    public function label(): string
    {
        return 'Open sales orders';
    }

    public function columns(): array
    {
        return ['order_ref' => 'Old system order number (rows with the same ref form one order)', 'customer_code' => 'Customer code', 'sku' => 'Variant SKU',
            'remaining_qty' => 'Quantity still to deliver', 'price' => 'Unit price in the customer currency', 'due_date' => 'Due date (yyyy-mm-dd, optional)', 'customer_po' => 'Customer PO (optional)'];
    }

    public function example(): array
    {
        return ['order_ref' => 'SO-OLD-881', 'customer_code' => 'CUS-0001', 'sku' => 'GLV-RED-M', 'remaining_qty' => '400', 'price' => '6.25', 'due_date' => '2026-12-15', 'customer_po' => 'HS-2291'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        $customer = $this->party(Customer::class, $row['customer_code']);
        if (! $customer) {
            $e[] = "Unknown customer \"{$row['customer_code']}\".";
        }
        $variant = $this->variant($row['sku']);
        if (! $variant) {
            $e[] = "Unknown SKU \"{$row['sku']}\".";
        } elseif (! $variant->item->can_sell) {
            $e[] = "{$row['sku']} is not sold.";
        }
        if (! $this->positive($row['remaining_qty']) || ! $this->nonNegative($row['price'])) {
            $e[] = 'Remaining quantity must be above zero and price zero or more.';
        }
        if ($row['due_date'] && ! strtotime((string) $row['due_date'])) {
            $e[] = 'Due date is not a date.';
        }
        $ref = (string) $row['order_ref'];
        if ($ref === '') {
            $e[] = 'order_ref is required.';
        } elseif ($customer) {
            if (isset($this->refCustomer[$ref]) && $this->refCustomer[$ref] !== $customer->id) {
                $e[] = "Order {$ref} has rows for two customers.";
            }
            $this->refCustomer[$ref] = $customer->id;
            if (SalesOrder::query()->where('notes', 'like', "Opening order {$ref}%")->exists()) {
                $e[] = "Order {$ref} was already imported.";
            }
        }

        return $e;
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        $orders = app(SalesOrders::class);
        foreach ($rows->groupBy(fn ($r) => $r->data['order_ref']) as $ref => $group) {
            $first = $group->first()->data;
            $customer = $this->party(Customer::class, $first['customer_code']);
            $order = $orders->save(null, ['customer_id' => $customer->getKey(), 'order_date' => $batch->batch_date->toDateString(), 'customer_po_number' => $first['customer_po'] ?: null,
                'due_date' => collect($group)->pluck('data.due_date')->filter()->min() ?: null, 'notes' => "Opening order {$ref} ({$batch->number})"],
                $group->map(fn ($r) => ['item_variant_id' => $this->variant($r->data['sku'])->id, 'description' => '', 'quantity' => (string) $r->data['remaining_qty'],
                    'unit_price' => (string) $r->data['price'], 'tax_rate' => '', 'due_date' => $r->data['due_date'] ?: null])->values()->all());
            app(DocumentWorkflow::class)->approve($order);
            foreach ($group as $r) {
                $r->update(['result_type' => $order->getMorphClass(), 'result_id' => $order->id]);
                $this->add((string) $customer->getAttribute('currency_code'), Decimal::mul((string) $r->data['remaining_qty'], (string) $r->data['price']));
            }
        }

        return $this->takeTotals();
    }
}
