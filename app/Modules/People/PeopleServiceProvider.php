<?php

namespace App\Modules\People;

use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Customers, vendors, employees and contractors (Module 2). Each has its own
 * screens and ledger; vendor and contractor profiles of one business share a party.
 */
class PeopleServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'People';
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('customers', 'Customers', PermissionSet::MASTER, 'Sales'),
            new PermissionSet('vendors', 'Vendors', PermissionSet::MASTER, 'Purchasing'),
            new PermissionSet('employees', 'Employees', PermissionSet::MASTER, 'HR'),
            new PermissionSet('contractors', 'Contractors', PermissionSet::MASTER, 'HR'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('customer', 'Customer code', 'CUS', approvable: false, yearFormat: 'none'),
            new DocumentType('vendor', 'Vendor code', 'VEN', approvable: false, yearFormat: 'none'),
            new DocumentType('employee', 'Employee code', 'EMP', approvable: false, yearFormat: 'none'),
            new DocumentType('contractor', 'Contractor code', 'CON', approvable: false, yearFormat: 'none'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Customers', 'people.customers.index', 'users', 'customers.view', 'Sales', 100, 'people.customers.*'),
            new NavItem('Vendors', 'people.vendors.index', 'building', 'vendors.view', 'Purchasing', 200, 'people.vendors.*'),
            new NavItem('Employees', 'people.employees.index', 'user', 'employees.view', 'HR', 700, 'people.employees.*'),
            new NavItem('Contractors', 'people.contractors.index', 'briefcase', 'contractors.view', 'HR', 750, 'people.contractors.*'),
        ];
    }
}
