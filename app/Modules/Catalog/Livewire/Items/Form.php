<?php

namespace App\Modules\Catalog\Livewire\Items;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Services\DocumentNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?Item $item = null;

    public string $code = '';

    public string $name = '';

    public ?int $category_id = null;

    public string $type = Item::RAW_MATERIAL;

    public string $description = '';

    public ?int $stock_unit_id = null;

    public ?int $purchase_unit_id = null;

    public ?int $sales_unit_id = null;

    public string $default_selling_price = '';

    public string $default_purchase_cost = '';

    public string $min_stock = '';

    public string $tax_rate = '';

    public string $tax_notes = '';

    public string $over_receipt_percent = '';

    public bool $can_buy = true;

    public bool $can_make = false;

    public bool $can_sell = false;

    public bool $track_by_lot = true;

    public bool $is_active = true;

    public ?TemporaryUploadedFile $image = null;

    public function mount(?Item $item = null): void
    {
        if ($item?->exists) {
            $this->item = $item;
            $this->fill(array_map(fn ($v) => $v ?? '', $item->only([
                'code', 'name', 'type', 'description', 'default_selling_price', 'min_stock', 'tax_rate', 'tax_notes',
                'over_receipt_percent', 'can_buy', 'can_make', 'can_sell', 'track_by_lot', 'is_active',
            ])));
            $this->category_id = $item->category_id;
            $this->stock_unit_id = $item->stock_unit_id;
            $this->purchase_unit_id = $item->purchase_unit_id;
            $this->sales_unit_id = $item->sales_unit_id;
            $this->default_purchase_cost = auth()->user()?->can('cost.view') ? (string) $item->default_purchase_cost : '';
        } else {
            $this->stock_unit_id = Unit::query()->where('symbol', 'pcs')->value('id');
        }
    }

    public function updatedType(string $type): void
    {
        // Sensible defaults when the type changes; users can still adjust them.
        if (! $this->item) {
            $this->can_buy = $type !== Item::FINISHED_GOOD;
            $this->can_make = $type !== Item::RAW_MATERIAL;
            $this->can_sell = $type === Item::FINISHED_GOOD;
        }
    }

    public function save(): void
    {
        $this->authorize($this->item ? 'items.edit' : 'items.create');

        $canSeeCost = auth()->user()?->can('cost.view') ?? false;
        $activeUnit = Rule::exists('units', 'id')->where('is_active', true)->whereNull('deleted_at');

        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('items', 'code')->ignore($this->item?->id)],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'item_category')],
            'type' => ['required', Rule::in(array_keys(Item::TYPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'stock_unit_id' => ['required', $activeUnit],
            'purchase_unit_id' => ['nullable', $activeUnit],
            'sales_unit_id' => ['nullable', $activeUnit],
            'default_selling_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'default_purchase_cost' => $canSeeCost ? ['nullable', 'numeric', 'min:0', 'decimal:0,4'] : ['nullable'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_notes' => ['nullable', 'string', 'max:255'],
            'over_receipt_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'can_buy' => ['boolean'],
            'can_make' => ['boolean'],
            'can_sell' => ['boolean'],
            'track_by_lot' => ['boolean'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ], ['code.regex' => 'Use letters, numbers, dots, dashes or underscores.']);

        if (! $data['can_buy'] && ! $data['can_make']) {
            $this->addError('can_buy', 'An item must be bought, made, or both.');

            return;
        }

        unset($data['image']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if (! $canSeeCost) {
            unset($data['default_purchase_cost']);
        }

        // The stock unit is fixed once the item exists: stock quantities are kept in it.
        if ($this->item) {
            unset($data['stock_unit_id']);
        }

        $item = DB::transaction(function () use ($data) {
            $item = $this->item ?? new Item;

            if (! $item->exists && blank($data['code'])) {
                $data['code'] = app(DocumentNumber::class)->next('item');
            }

            if ($this->image) {
                $data['image_path'] = $this->storeImage($this->image, $item->image_path);
            }

            $item->fill($data)->save();

            return $item;
        });

        session()->flash('toast', $this->item ? 'Item updated.' : "Item {$item->code} created.");
        $this->redirectRoute('catalog.items.show', $item, navigate: true);
    }

    private function storeImage(TemporaryUploadedFile $file, ?string $old): string
    {
        $jpg = (new ImageManager(new Driver))->read($file->getRealPath())->scaleDown(width: 800)->toJpeg(80);
        $path = 'items/'.Str::random(16).'.jpg';
        Storage::disk('public')->put($path, (string) $jpg);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return $path;
    }

    public function render(): View
    {
        $units = Unit::query()->active()->orderBy('name')->get()->mapWithKeys(fn (Unit $u) => [$u->id => "{$u->name} ({$u->symbol})"]);

        return view('catalog::livewire.items.form', [
            'types' => Item::TYPES,
            'categories' => Lookup::options('item_category', $this->category_id),
            'units' => $units,
            'canSeeCost' => auth()->user()?->can('cost.view') ?? false,
        ])->title($this->item ? 'Edit item' : 'New item');
    }
}
