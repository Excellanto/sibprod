<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportHeadingRow;
use App\Services\ClientModelReportService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class ClientModelReportExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithStyles
{
    use StylesReportHeadingRow;
    public function __construct(
        protected ?int $clientId,
        protected ClientModelReportService $service,
        protected $onRow = null,
    ) {}

    public function query()
    {
        if ($this->clientId !== null) {
            return $this->service->rowsForClientQuery($this->clientId);
        }

        return $this->service->rowsForAllClientsQuery();
    }

    public function headings(): array
    {
        return ClientModelReportService::HEADINGS;
    }

    public function map($row): array
    {
        $mapped = $this->service->mapExportRow($row);
        if ($this->onRow) {
            ($this->onRow)();
        }

        return array_map(fn (string $heading) => $mapped[$heading], ClientModelReportService::HEADINGS);
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
