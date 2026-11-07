<?php

/*
| Default roles from the specification and the permissions they start with.
|
| Patterns use "*" wildcards: "sales_orders.*" (every action on sales orders),
| "*.view" (view anything). "except" removes matches again.
|
| `php artisan permissions:sync` creates missing roles and grants each NEW
| permission to the roles whose patterns match it. Permissions an admin has
| already changed in the UI are left alone.
|
| Admin is not listed: it always has every permission (see AppServiceProvider).
*/

$documentWork = ['view', 'create', 'edit', 'delete', 'post', 'cancel', 'print', 'export'];

$on = function (array $resources, array $actions = ['*']): array {
    $out = [];
    foreach ($resources as $resource) {
        foreach ($actions as $action) {
            $out[] = "{$resource}.{$action}";
        }
    }

    return $out;
};

return [

    'admin' => 'Admin',

    'defaults' => [

        'Manager' => [
            'allow' => ['*.view', '*.approve', '*.print', '*.export', 'reports.*', 'cost.view', 'profit.view', 'bank_balance.view', 'salary.view'],
            'except' => ['users.*', 'roles.*', 'settings.*', 'audit_log.*'],
        ],

        'Sales' => [
            'allow' => [
                ...$on(['customers', 'sales_orders', 'planning', 'deliveries', 'packing_lists', 'invoices', 'delivery_returns', 'credit_notes'], $documentWork),
                'items.view', 'stock.view', 'reservations.*', 'ledgers.view',
            ],
            'except' => ['*.approve', '*.override'],
        ],

        'Purchase' => [
            'allow' => [
                ...$on(['vendors', 'purchase_orders', 'purchase_returns'], $documentWork),
                'goods_receipts.view', 'goods_receipts.print', 'items.view', 'stock.view', 'planning.view', 'ledgers.view',
            ],
            'except' => ['*.approve', '*.override'],
        ],

        'Store' => [
            'allow' => [
                ...$on(['stock', 'stock_movements', 'lots', 'reservations', 'goods_receipts', 'production_transactions'], $documentWork),
                'stock_adjustments.view', 'stock_adjustments.create', 'stock_adjustments.edit', 'stock_adjustments.delete', 'stock_adjustments.print',
                'items.view', 'ptcs.view', 'purchase_orders.view', 'deliveries.view',
            ],
            'except' => ['*.approve', '*.override'],
        ],

        'Production' => [
            'allow' => [
                ...$on(['ptcs', 'material_processes', 'contractor_jobs', 'rejections', 'wage_work', 'production_transactions'], $documentWork),
                'items.view', 'routes.view', 'boms.view', 'stock.view', 'lots.view', 'contractors.view', 'employees.view', 'planning.view',
            ],
            'except' => ['*.approve', '*.override'],
        ],

        'Accountant' => [
            'allow' => [
                ...$on(['accounts', 'payments', 'receipts', 'transfers', 'currency_conversions', 'expenses', 'journal_vouchers', 'chart_of_accounts', 'invoices', 'vendor_bills', 'credit_notes', 'assets'], $documentWork),
                'ledgers.*', 'reports.*', 'bank_balance.view', 'cost.view', 'profit.view',
                'customers.view', 'vendors.view', 'contractors.view', 'employees.view',
            ],
            'except' => ['*.approve', '*.override'],
        ],

        'HR' => [
            'allow' => [
                ...$on(['employees', 'attendance', 'wage_work', 'loans', 'advances'], $documentWork),
                'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.delete', 'payroll.print', 'payroll.export',
                'salary.view', 'ledgers.view',
            ],
            'except' => ['*.approve', '*.override'],
        ],

    ],

];
