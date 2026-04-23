<?php

namespace Foundry\Exports;

use Foundry\Contracts\Exportable;

abstract class BaseExport implements Exportable
{
    protected $data;

    public function __construct($data = null)
    {
        $this->data = $data;
    }

    abstract public function getData();

    abstract public function getHeadings(): array;
}
