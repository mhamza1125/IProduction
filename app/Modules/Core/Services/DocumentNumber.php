<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\NumberSequence;
use App\Support\Modules\ModuleRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Unique document numbers such as "SO-26-0001", configurable per document
 * type in Settings > Numbering.
 *
 * Call it inside the DB transaction that saves the document:
 *
 *   DB::transaction(function () use ($order) {
 *       $order->number = app(DocumentNumber::class)->next('sales_order', $order->order_date);
 *       $order->save();
 *   });
 *
 * The counter row stays locked until the transaction commits, so two users
 * saving together never get the same number. The year part comes from the
 * document date (calendar year).
 */
class DocumentNumber
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    public function next(string $documentType, ?CarbonInterface $date = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('DocumentNumber::next() must be called inside a database transaction.');
        }

        $sequence = $this->sequence($documentType);
        $period = $this->period($sequence, $date ?? Date::now());

        // Make sure the counter exists, then lock it. insertOrIgnore avoids a
        // race when two transactions create the first number of a year together.
        DB::table('number_sequence_counters')->insertOrIgnore([
            'document_type' => $documentType,
            'period' => $period,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('number_sequence_counters')
            ->where('document_type', $documentType)
            ->where('period', $period)
            ->lockForUpdate()
            ->first(['id', 'next_number']);

        if ($counter === null) {
            throw new LogicException("Could not lock the number counter for [{$documentType}].");
        }

        $number = (int) $counter->next_number;

        DB::table('number_sequence_counters')
            ->where('id', $counter->id)
            ->update(['next_number' => $number + 1, 'updated_at' => now()]);

        return $this->format($sequence, $period, $number);
    }

    /**
     * What the next number will look like, without using it up.
     */
    public function preview(string $documentType, ?CarbonInterface $date = null): string
    {
        $sequence = $this->sequence($documentType);
        $period = $this->period($sequence, $date ?? Date::now());

        return $this->format($sequence, $period, $this->nextValue($documentType, $date));
    }

    /**
     * The counter value the next document of this type (and year) will get.
     */
    public function nextValue(string $documentType, ?CarbonInterface $date = null): int
    {
        $period = $this->period($this->sequence($documentType), $date ?? Date::now());

        return (int) (DB::table('number_sequence_counters')
            ->where('document_type', $documentType)
            ->where('period', $period)
            ->value('next_number') ?? 1);
    }

    /**
     * Set the next number for the current year (Settings > Numbering).
     */
    public function setNext(string $documentType, int $next, ?CarbonInterface $date = null): void
    {
        $period = $this->period($this->sequence($documentType), $date ?? Date::now());

        DB::table('number_sequence_counters')->updateOrInsert(
            ['document_type' => $documentType, 'period' => $period],
            ['next_number' => max(1, $next), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * The saved format, or a default built from the module's DocumentType.
     */
    public function sequence(string $documentType): NumberSequence
    {
        $sequence = NumberSequence::query()->where('document_type', $documentType)->first();

        if ($sequence !== null) {
            return $sequence;
        }

        $type = $this->modules->documentType($documentType)
            ?? throw new InvalidArgumentException("Unknown document type [{$documentType}].");

        return NumberSequence::query()->create([
            'document_type' => $documentType,
            'prefix' => $type->prefix,
            'separator' => '-',
            'year_format' => 'YY',
            'padding' => 4,
        ]);
    }

    private function period(NumberSequence $sequence, CarbonInterface $date): string
    {
        return match ($sequence->year_format) {
            'YYYY' => $date->format('Y'),
            'none' => '',
            default => $date->format('y'),
        };
    }

    private function format(NumberSequence $sequence, string $period, int $number): string
    {
        $parts = [$sequence->prefix, $period, str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT)];

        return implode($sequence->separator, array_filter($parts, fn (string $part) => $part !== ''));
    }
}
