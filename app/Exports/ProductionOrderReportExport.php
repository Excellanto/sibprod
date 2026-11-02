<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportHeadingRow;
use App\Services\ProductionOrderReportService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class ProductionOrderReportExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithStyles
{
    use StylesReportHeadingRow;
    public function __construct(
        protected string $fromDate,
        protected string $toDate,
        protected ProductionOrderReportService $service,
        protected $onRow = null,
    ) {}

    public function query()
    {
        return $this->service->rowsForDateRangeQuery($this->fromDate, $this->toDate);
    }

    public function headings(): array
    {
        return ProductionOrderReportService::HEADINGS;
    }

    public function map($row): array
    {
        $mapped = $this->service->mapExportRow($row);
        if ($this->onRow) {
            ($this->onRow)();
        }

        return array_map(fn (string $heading) => $mapped[$heading], ProductionOrderReportService::HEADINGS);
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
