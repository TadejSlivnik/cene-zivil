<?php

namespace App\Tests\Support;

final class Fixtures
{
    public static function path(string $name): string
    {
        return __DIR__.'/../Fixtures/'.$name;
    }

    public static function json(string $name): array
    {
        return json_decode(file_get_contents(self::path($name)), true, 512, \JSON_THROW_ON_ERROR);
    }

    public static function contents(string $name): string
    {
        return file_get_contents(self::path($name));
    }
}
