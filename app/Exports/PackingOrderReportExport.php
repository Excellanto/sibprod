<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportHeadingRow;
use App\Services\PackingOrderReportService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class PackingOrderReportExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithStyles
{
    use StylesReportHeadingRow;
    public function __construct(
        protected string $fromDate,
        protected string $toDate,
        protected PackingOrderReportService $service,
        protected $onRow = null,
    ) {}

    public function query()
    {
        return $this->service->rowsForDateRangeQuery($this->fromDate, $this->toDate);
    }

    public function headings(): array
    {
        return PackingOrderReportService::HEADINGS;
    }

    public function map($row): array
    {
        $mapped = $this->service->mapExportRow($row);
        if ($this->onRow) {
            ($this->onRow)();
        }

        return array_map(fn (string $heading) => $mapped[$heading], PackingOrderReportService::HEADINGS);
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
