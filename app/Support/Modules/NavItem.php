<?php

namespace App\Support\Modules;

/**
 * One sidebar link. Items are grouped by $group and sorted by $order.
 */
final readonly class NavItem
{
    public function __construct(
        public string $label,
        public string $route,
        public string $icon = 'squares',
        public ?string $permission = null,
        public string $group = 'General',
        public int $order = 100,
        /** Route name pattern used to highlight the link, e.g. "core.users.*". Defaults to $route. */
        public ?string $activePattern = null,
    ) {}

    public function isActive(): bool
    {
        return request()->routeIs($this->activePattern ?? $this->route);
    }
}
