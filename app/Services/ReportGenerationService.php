<?php

namespace App\Services;

use App\Jobs\GenerateReportJob;
use App\Models\ReportGeneration;
use Illuminate\Support\Collection;

class ReportGenerationService
{
    public function createClientModelReport(
        int $userId,
        int $clientId,
        string $clientName
    ): ReportGeneration {
        return $this->queue(ReportGeneration::query()->create([
            'report_type' => ReportGeneration::TYPE_CLIENT_MODEL,
            'user_id' => $userId,
            'label' => $clientName,
            'parameters' => [
                'client_id' => $clientId,
            ],
            ...$this->queuedAttributes(),
        ]));
    }

    public function createClientModelReportAllClients(int $userId): ReportGeneration
    {
        return $this->queue(ReportGeneration::query()->create([
            'report_type' => ReportGeneration::TYPE_CLIENT_MODEL,
            'user_id' => $userId,
            'label' => 'All Clients',
            'parameters' => [
                'all_clients' => true,
            ],
            ...$this->queuedAttributes(),
        ]));
    }

    public function createPackingOrderReport(
        int $userId,
        string $fromDate,
        string $toDate
    ): ReportGeneration {
        return $this->createDateRangeReport(
            ReportGeneration::TYPE_PACKING_ORDER,
            $userId,
            $fromDate,
            $toDate
        );
    }

    public function createProductionOrderReport(
        int $userId,
        string $fromDate,
        string $toDate
    ): ReportGeneration {
        return $this->createDateRangeReport(
            ReportGeneration::TYPE_PRODUCTION_ORDER,
            $userId,
            $fromDate,
            $toDate
        );
    }

    public function createOrderDetailReport(
        int $userId,
        string $fromDate,
        string $toDate
    ): ReportGeneration {
        return $this->createDateRangeReport(
            ReportGeneration::TYPE_ORDER_DETAIL,
            $userId,
            $fromDate,
            $toDate
        );
    }

    protected function createDateRangeReport(
        string $reportType,
        int $userId,
        string $fromDate,
        string $toDate
    ): ReportGeneration {
        $fromLabel = \Carbon\Carbon::parse($fromDate)->format('d/m/Y');
        $toLabel = \Carbon\Carbon::parse($toDate)->format('d/m/Y');

        return $this->queue(ReportGeneration::query()->create([
            'report_type' => $reportType,
            'user_id' => $userId,
            'label' => $fromLabel.' – '.$toLabel,
            'parameters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            ...$this->queuedAttributes(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function queuedAttributes(): array
    {
        return [
            'row_count' => 0,
            'status' => ReportGeneration::STATUS_QUEUED,
            'progress' => 0,
            'processed_rows' => 0,
        ];
    }

    protected function queue(ReportGeneration $report): ReportGeneration
    {
        GenerateReportJob::dispatch($report->id)->afterCommit();

        return $report;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForType(string $reportType, int $limit = 100): Collection
    {
        return ReportGeneration::query()
            ->where('report_type', $reportType)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ReportGeneration $generation) => $generation->toListArray());
    }

    public function findForExport(int $id, string $expectedType): ?ReportGeneration
    {
        return ReportGeneration::query()
            ->where('id', $id)
            ->where('report_type', $expectedType)
            ->first();
    }
}
