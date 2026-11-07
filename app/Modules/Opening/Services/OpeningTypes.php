<?php

namespace App\Modules\Opening\Services;

use App\Modules\Opening\Types\OpeningType;
use InvalidArgumentException;

class OpeningTypes
{
    /** @var array<string, class-string<OpeningType>> */
    private static array $types = [];

    /** @param  class-string<OpeningType>  $class */
    public static function register(string $class): void
    {
        self::$types[app($class)->key()] = $class;
    }

    public function get(string $key): OpeningType
    {
        return isset(self::$types[$key]) ? app(self::$types[$key]) : throw new InvalidArgumentException("Unknown opening type [{$key}].");
    }

    /** @return array<string, string> key => label, in the order they should be imported */
    public function options(): array
    {
        $out = [];
        foreach (self::$types as $key => $class) {
            $out[$key] = app($class)->label();
        }

        return $out;
    }
}
