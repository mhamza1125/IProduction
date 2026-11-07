<?php

namespace App\Modules\Payroll\Livewire\Runs;

use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Services\Payrolls;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
use App\Support\Livewire\HandlesDocumentActions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use HandlesDocumentActions;

    #[Locked]
    public PayrollRun $run;

    #[Locked]
    public ?int $editing = null;

    public string $allowances = '';

    public string $attendance = '';

    public string $reason = '';

    public function mount(PayrollRun $run): void
    {
        $this->run = $run;
    }

    protected function document(): Model&Document
    {
        return $this->run;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(Payrolls::class)->post($this->run, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshRun(): void
    {
        $this->run->refresh();
    }

    public function recalculate(Payrolls $payrolls): void
    {
        $this->authorize('payroll.edit');
        try {
            $payrolls->generate($this->run);
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->run->refresh();
        $this->dispatch('toast', message: 'Recalculated from the latest attendance, wage work, loans, advances and fines.');
    }

    public function edit(int $lineId): void
    {
        $this->authorize('payroll.edit');
        $line = $this->run->lines()->findOrFail($lineId);
        $this->editing = $line->id;
        $this->allowances = (string) $line->allowances;
        $this->attendance = (string) $line->attendance_deduction;
        $this->reason = (string) $line->deduction_override_reason;
    }

    public function saveLine(Payrolls $payrolls): void
    {
        $this->authorize('payroll.edit');
        $this->validate(['allowances' => ['required', 'numeric', 'min:0'], 'attendance' => ['required', 'numeric', 'min:0']]);
        $line = PayrollLine::query()->where('payroll_run_id', $this->run->id)->findOrFail($this->editing);
        try {
            $payrolls->editLine($line, (string) $this->allowances, (string) $this->attendance, $this->reason);
        } catch (DocumentException $e) {
            $this->addError('attendance', $e->getMessage());

            return;
        }
        $this->reset('editing', 'allowances', 'attendance', 'reason');
        $this->run->refresh();
        $this->dispatch('toast', message: 'Line updated.');
    }

    public function delete(): void
    {
        $this->authorize('payroll.delete');
        abort_unless($this->run->isDraft(), 403);
        $this->run->lines()->each(fn ($l) => $l->delete());
        $this->run->delete();
        session()->flash('toast', 'Draft payroll deleted.');
        $this->redirectRoute('payroll.runs.index', navigate: true);
    }

    public function render(): View
    {
        return view('payroll::livewire.runs.show', [
            'lines' => $this->run->lines()->with(['employee', 'items'])->get()->sortBy('employee.name')->values(),
            'canSee' => auth()->user()->can('salary.view'),
        ])->title($this->run->number.' · '.$this->run->monthLabel());
    }
}
