<?php

namespace Foundry\Services;

use Foundry\Contracts\Exportable;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService
{
    /**
     * Export the given exportable to the specified format.
     *
     * @return BinaryFileResponse
     */
    public function export(Exportable $exportable, string $filename, string $type = 'xlsx')
    {
        $path = storage_path("app/public/{$filename}-".time().".{$type}");
        $writer = SimpleExcelWriter::create($path)
            ->addHeader($exportable->getHeadings())
            ->addRows($exportable->getData());

        return response()->download($path)->deleteFileAfterSend();
    }
}
