<?php

namespace Foundry\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PreserveWhitespaceJson implements CastsAttributes
{
    /**
     * Cast the given value.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        // If the value is already an array/object, return it as-is
        if (is_array($value) || is_object($value)) {
            return $value;
        }

        // Decode JSON with flags that preserve formatting
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

        return $decoded;
    }

    /**
     * Prepare the given value for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        // If it's already a JSON string, validate and return it
        if (is_string($value)) {
            // Test if it's valid JSON
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }

        // Encode with flags that preserve Unicode and formatting
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the serialized representation of the value.
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
