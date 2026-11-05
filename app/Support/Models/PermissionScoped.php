<?php

namespace App\Support\Models;

/**
 * A record whose access follows a permission resource, e.g. a sales order
 * follows "sales_orders.view" / "sales_orders.edit". Attachments, notes and
 * activity history on the record use the same permissions.
 */
interface PermissionScoped
{
    public function permissionKey(): string;
}
