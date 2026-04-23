<?php

namespace Foundry\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Logable
{
    /**
     * Registered model-to-format mappers.
     *
     * @var array<class-string<Model>, Closure>
     */
    protected static array $mappers = [];

    /**
     * Register a log format mapper for a given model class.
     *
     * The closure receives the model instance and must return an array
     * with a 'name' key and optionally a 'route' key.
     *
     * Example (in a service provider):
     *   Logable::add(Page::class, fn ($page) => [
     *       'name'  => $page->title,
     *       'route' => route('admin.pages.edit', $page->id),
     *   ]);
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function add(string $modelClass, Closure $mapper): void
    {
        static::$mappers[$modelClass] = $mapper;
    }

    /**
     * Resolve the log resource data for a given model instance.
     *
     * Returns an array with 'type', 'name', and 'route' keys.
     * Falls back to a human-readable type with null name/route when no mapper is registered.
     *
     * @return array{type: string|null, name: string|null, route: string|null}
     */
    public static function resolve(?Model $model): array
    {
        if (! $model) {
            return ['type' => null, 'name' => null, 'route' => null];
        }

        $class = get_class($model);
        $type = Str::of(class_basename($class))->snake()->replace('_', ' ')->title()->toString();

        if (isset(static::$mappers[$class])) {
            $result = (static::$mappers[$class])($model);

            return [
                'type' => $type,
                'name' => $result['name'] ?? null,
                'route' => $result['route'] ?? null,
            ];
        }

        return ['type' => $type, 'name' => null, 'route' => null];
    }
}
