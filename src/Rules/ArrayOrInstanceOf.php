<?php

namespace Foundry\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ArrayOrInstanceOf implements ValidationRule
{
    protected string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) && ! $value instanceof $this->class) {
            $fail("The :attribute must be an array or instance of {$this->class}.");
        }
    }
}
