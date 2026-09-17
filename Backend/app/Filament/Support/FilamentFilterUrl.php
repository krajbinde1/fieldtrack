<?php

namespace App\Filament\Support;

final class FilamentFilterUrl
{
    /**
     * @param  class-string  $resource
     * @param  array<string, mixed>  $filters
     */
    public static function for(string $resource, array $filters = []): string
    {
        return $resource::getUrl('index', $filters === [] ? [] : ['filters' => $filters]);
    }
}
