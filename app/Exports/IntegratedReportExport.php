<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Builds the integrated annual/monthly report workbook
 * (LAPORAN_TERPADU_AMCS.xlsx equivalent).
 */
class IntegratedReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<int, array<int, mixed>>  $detailRows  full rows incl. header
     * @param  array<int, array<int, mixed>>  $recapRows  full rows incl. header
     */
    public function __construct(
        protected array $detailRows,
        protected array $recapRows,
    ) {}

    /**
     * @return array<int, ArraySheetExport>
     */
    public function sheets(): array
    {
        $recapHeadings = array_shift($this->recapRows) ?? [];
        $detailHeadings = array_shift($this->detailRows) ?? [];

        return [
            new ArraySheetExport($this->recapRows, $recapHeadings, 'Rekap Tahunan'),
            new ArraySheetExport($this->detailRows, $detailHeadings, 'Detail Pergerakan'),
        ];
    }
}
