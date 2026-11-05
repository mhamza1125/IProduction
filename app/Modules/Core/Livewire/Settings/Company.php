<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Company settings')]
class Company extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $ntn = '';

    public string $strn = '';

    public int $fiscal_year_start_month = 7;

    public ?TemporaryUploadedFile $logo = null;

    #[Locked]
    public ?string $logoPath = null;

    public function mount(Settings $settings): void
    {
        $this->name = (string) $settings->get('company.name');
        $this->address = (string) $settings->get('company.address');
        $this->phone = (string) $settings->get('company.phone');
        $this->email = (string) $settings->get('company.email');
        $this->ntn = (string) $settings->get('company.ntn');
        $this->strn = (string) $settings->get('company.strn');
        $this->fiscal_year_start_month = (int) $settings->get('fiscal_year.start_month');
        $this->logoPath = $settings->get('company.logo_path');
    }

    public function save(Settings $settings): void
    {
        $this->authorize('core.settings.manage');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'strn' => ['nullable', 'string', 'max:30'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        if ($this->logo !== null) {
            $this->logoPath = $this->storeLogo($this->logo);
            $this->logo = null;
        }

        $settings->setMany([
            'company.name' => $data['name'],
            'company.address' => $data['address'] ?: null,
            'company.phone' => $data['phone'] ?: null,
            'company.email' => $data['email'] ?: null,
            'company.ntn' => $data['ntn'] ?: null,
            'company.strn' => $data['strn'] ?: null,
            'company.logo_path' => $this->logoPath,
            'fiscal_year.start_month' => $data['fiscal_year_start_month'],
        ]);

        $this->dispatch('toast', message: 'Settings saved.');
    }

    /**
     * Shrinks the logo to at most 600px wide and stores it as PNG on the public disk.
     */
    private function storeLogo(TemporaryUploadedFile $file): string
    {
        $png = (new ImageManager(new Driver))
            ->read($file->getRealPath())
            ->scaleDown(width: 600)
            ->toPng();

        $path = 'company/logo-'.Str::random(8).'.png';
        Storage::disk('public')->put($path, (string) $png);

        if ($this->logoPath !== null && $this->logoPath !== $path) {
            Storage::disk('public')->delete($this->logoPath);
        }

        return $path;
    }

    public function render(): View
    {
        return view('core::livewire.settings.company');
    }
}
