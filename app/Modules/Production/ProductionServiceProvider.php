<?php

namespace App\Modules\Production;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Actions\PostMaterialProcess;
use App\Modules\Production\Models\ContractorJob;
use App\Modules\Production\Models\ContractorJobLine;
use App\Modules\Production\Models\MaterialProcess;
use App\Modules\Production\Models\MaterialProcessLine;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcTransaction;
use App\Modules\Production\Models\WageWork;
use App\Modules\Production\Services\ContractorJobs;
use App\Modules\Production\Services\ProductionAccounting;
use App\Modules\Production\Services\PtcCosting;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Production\Services\Rejections;
use App\Modules\Production\Services\WageWorks;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Production (specification, Modules 7 and 8): PTCs, child and merged PTCs,
 * wage work, contractor jobs, material processing and rejections.
 */
class ProductionServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Production';
    }

    public function register(): void
    {
        parent::register();

        foreach ([PtcCosting::class, ProductionAccounting::class, WageWorks::class, Rejections::class, PtcTransactions::class, Ptcs::class, ContractorJobs::class, PostMaterialProcess::class] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        parent::boot();

        ItemVariant::whenUsed('production.variant', fn (ItemVariant $v) => Ptc::query()->where('item_variant_id', $v->id)->exists()
            || PtcTransaction::query()->where('item_variant_id', $v->id)->exists()
            || ContractorJobLine::query()->where('item_variant_id', $v->id)->exists()
            || MaterialProcessLine::query()->where('item_variant_id', $v->id)->exists());
        Item::whenUsed('production.item', fn (Item $i) => Ptc::query()->whereIn('item_variant_id', $i->variants()->withTrashed()->select('id'))->exists());
        ProductionRoute::whenUsed('production.route', fn (ProductionRoute $r) => Ptc::query()->where('route_id', $r->id)->exists());
        Employee::whenUsed('production.employee', fn (Employee $e) => PtcTransaction::query()->where('employee_id', $e->id)->exists() || WageWork::query()->where('employee_id', $e->id)->exists());
        Contractor::whenUsed('production.contractor', fn (Contractor $c) => ContractorJob::query()->where('contractor_id', $c->id)->exists()
            || PtcTransaction::query()->where('contractor_id', $c->id)->exists() || MaterialProcess::query()->where('contractor_id', $c->id)->exists());
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('ptcs', 'PTCs', [...PermissionSet::DOCUMENT, 'override'], 'Production'),
            new PermissionSet('production_transactions', 'Stage transactions', ['view', 'create', 'export'], 'Production'),
            new PermissionSet('wage_work', 'Wage work', ['view', 'create', 'approve', 'override', 'export'], 'Production'),
            new PermissionSet('contractor_jobs', 'Contractor jobs', PermissionSet::DOCUMENT, 'Production'),
            new PermissionSet('material_processes', 'Material processing', PermissionSet::DOCUMENT, 'Production'),
            new PermissionSet('rejections', 'Rejections', [...PermissionSet::DOCUMENT], 'Production'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('ptc', 'PTC', 'PTC'),
            new DocumentType('contractor_job', 'Contractor Job', 'CJ'),
            new DocumentType('material_process', 'Material Processing', 'MP'),
            new DocumentType('rejection', 'Rejection', 'REJ', approvable: false),
            new DocumentType('rejection_decision', 'Rejection Decision', 'RD'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('PTCs', 'production.ptcs.index', 'cog', 'ptcs.view', 'Production', 200, 'production.ptcs.*'),
            new NavItem('Contractor Jobs', 'production.jobs.index', 'users', 'contractor_jobs.view', 'Production', 210, 'production.jobs.*'),
            new NavItem('Material Processing', 'production.processes.index', 'arrows', 'material_processes.view', 'Production', 220, 'production.processes.*'),
            new NavItem('Rejections', 'production.rejections.index', 'shield', 'rejections.view', 'Production', 230, 'production.rejections.*'),
            new NavItem('Wage Work', 'production.wage-work', 'clock', 'wage_work.view', 'Production', 240),
        ];
    }
}
