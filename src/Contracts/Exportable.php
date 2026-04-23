<?php

namespace Foundry\Contracts;

use Illuminate\Support\Collection;

interface Exportable
{
    /**
     * Get the data to be exported.
     *
     * @return Collection|array
     */
    public function getData();

    /**
     * Get the headings for the export.
     */
    public function getHeadings(): array;
}
