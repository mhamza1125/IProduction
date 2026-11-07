<?php

namespace App\Modules\Opening\Services;

/** Values already seen in a file, to catch duplicates inside it. */
final class OpeningContext
{
    /** @var array<string, true> */
    private array $seen = [];

    /** Returns false when $key was already used in this file. */
    public function claim(string $key): bool
    {
        if (isset($this->seen[$key])) {
            return false;
        }
        $this->seen[$key] = true;

        return true;
    }
}
