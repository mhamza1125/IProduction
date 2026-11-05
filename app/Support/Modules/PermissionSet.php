<?php

namespace App\Support\Modules;

/**
 * The actions a role can be given on one resource, e.g. sales_orders: view, create, approve...
 * Permission names are "{key}.{action}", e.g. "sales_orders.post".
 */
final readonly class PermissionSet
{
    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'approve', 'post', 'cancel', 'print', 'export', 'override'];

    /** Actions most document types support. */
    public const DOCUMENT = ['view', 'create', 'edit', 'delete', 'approve', 'post', 'cancel', 'print', 'export'];

    /** Actions most master-data screens support. */
    public const MASTER = ['view', 'create', 'edit', 'delete', 'export'];

    /**
     * @param  list<string>  $actions
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $actions,
        public string $group = 'General',
    ) {}

    /**
     * @return array<string, string> permission name => description
     */
    public function permissions(): array
    {
        $out = [];

        foreach ($this->actions as $action) {
            $out[$this->key.'.'.$action] = ucfirst($action).' '.mb_strtolower($this->label);
        }

        return $out;
    }
}
