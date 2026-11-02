<?php

namespace App\Services;

use App\Exceptions\ReportCancelledException;
use App\Exports\ClientModelReportExport;
use App\Exports\OrderDetailReportExport;
use App\Exports\PackingOrderReportExport;
use App\Exports\ProductionOrderReportExport;
use App\Models\ReportGeneration;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ReportFileWriter
{
    public function __construct(
        protected ClientModelReportService $clientModelReportService,
        protected PackingOrderReportService $packingOrderReportService,
        protected ProductionOrderReportService $productionOrderReportService,
        protected OrderDetailReportService $orderDetailReportService,
    ) {}

    public function write(ReportGeneration $report): void
    {
        try {
            $this->writeFiles($report);
        } catch (ReportCancelledException) {
            $report->deleteStoredFiles();
        }
    }

    protected function writeFiles(ReportGeneration $report): void
    {
        if ($this->missingDateRange($report)) {
            $this->markFailed($report, 'Invalid report parameters.');

            return;
        }

        $total = $this->countRows($report);
        if ($total === 0) {
            $this->markFailed($report, $this->emptyMessage($report));

            return;
        }

        $this->ensureStillExists($report);

        $report->update([
            'row_count' => $total,
            'total_rows' => $total,
            'processed_rows' => 0,
            'progress' => 5,
        ]);

        $excelWeight = 74;
        $excelRelative = 'reports/'.$report->id.'.xlsx';
        $processed = 0;

        $export = $this->makeExport($report, function () use (&$processed, $report, $total, $excelWeight) {
            $processed++;
            if ($processed !== $total && ($processed % 500) !== 0) {
                return;
            }

            $this->ensureStillExists($report);
            ReportGeneration::query()->whereKey($report->id)->update([
                'processed_rows' => $processed,
                'progress' => min(99, 5 + (int) floor(($processed / $total) * $excelWeight)),
            ]);
        });

        Excel::store($export, $excelRelative, 'local');
        $this->ensureStillExists($report);

        $pdfRelative = 'reports/'.$report->id.'.pdf';
        $htmlRelative = 'reports/'.$report->id.'.html';
        $warning = null;

        ReportGeneration::query()->whereKey($report->id)->update(['progress' => 85]);

        try {
            $html = view('pdf.report-data-grid', $this->pdfViewData($report))->render();
            Storage::disk('local')->put($htmlRelative, $html);

            $this->ensureStillExists($report);
            ReportGeneration::query()->whereKey($report->id)->update(['progress' => 92]);

            $binary = PDF::loadHTML($html)
                ->setOption('margin-top', 5)
                ->setOption('margin-bottom', 5)
                ->setOption('margin-left', 5)
                ->setOption('margin-right', 5)
                ->setOption('encoding', 'UTF-8')
                ->setOption('orientation', 'Landscape')
                ->setOption('no-outline', true)
                ->output();

            Storage::disk('local')->put($pdfRelative, $binary);
        } catch (ReportCancelledException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Report PDF failed after Excel was stored: '.$e->getMessage(), [
                'report_generation_id' => $report->id,
            ]);
            Storage::disk('local')->delete([$htmlRelative, $pdfRelative]);
            $htmlRelative = null;
            $pdfRelative = null;
            $warning = Str::limit('Excel is ready. PDF could not be created: '.$e->getMessage(), 500);
        }

        $updated = ReportGeneration::query()->whereKey($report->id)->update([
            'status' => ReportGeneration::STATUS_READY,
            'progress' => 100,
            'processed_rows' => $total,
            'row_count' => $total,
            'excel_path' => $excelRelative,
            'pdf_path' => $pdfRelative,
            'html_path' => $htmlRelative,
            'error' => $warning,
        ]);

        if ($updated === 0) {
            throw new ReportCancelledException();
        }
    }

    protected function markFailed(ReportGeneration $report, string $message): void
    {
        ReportGeneration::query()->whereKey($report->id)->update([
            'status' => ReportGeneration::STATUS_FAILED,
            'progress' => 0,
            'row_count' => 0,
            'error' => $message,
        ]);
    }

    protected function ensureStillExists(ReportGeneration $report): void
    {
        if (! ReportGeneration::query()->whereKey($report->id)->exists()) {
            throw new ReportCancelledException();
        }
    }

    protected function missingDateRange(ReportGeneration $report): bool
    {
        if (! in_array($report->report_type, [
            ReportGeneration::TYPE_PACKING_ORDER,
            ReportGeneration::TYPE_PRODUCTION_ORDER,
            ReportGeneration::TYPE_ORDER_DETAIL,
        ], true)) {
            return false;
        }

        return ! $report->fromDate() || ! $report->toDate();
    }

    protected function countRows(ReportGeneration $report): int
    {
        return match ($report->report_type) {
            ReportGeneration::TYPE_CLIENT_MODEL => $report->isAllClients()
                ? $this->clientModelReportService->reportCountAllClients()
                : $this->clientModelReportService->reportCountForClient((int) $report->clientId()),
            ReportGeneration::TYPE_PACKING_ORDER => $this->packingOrderReportService->countForDateRange(
                (string) $report->fromDate(),
                (string) $report->toDate()
            ),
            ReportGeneration::TYPE_PRODUCTION_ORDER => $this->productionOrderReportService->countForDateRange(
                (string) $report->fromDate(),
                (string) $report->toDate()
            ),
            ReportGeneration::TYPE_ORDER_DETAIL => $this->orderDetailReportService->countForDateRange(
                (string) $report->fromDate(),
                (string) $report->toDate()
            ),
            default => throw new \RuntimeException('Unknown report type.'),
        };
    }

    protected function makeExport(ReportGeneration $report, callable $onRow): object
    {
        return match ($report->report_type) {
            ReportGeneration::TYPE_CLIENT_MODEL => new ClientModelReportExport(
                $report->isAllClients() ? null : $report->clientId(),
                $this->clientModelReportService,
                $onRow
            ),
            ReportGeneration::TYPE_PACKING_ORDER => new PackingOrderReportExport(
                (string) $report->fromDate(),
                (string) $report->toDate(),
                $this->packingOrderReportService,
                $onRow
            ),
            ReportGeneration::TYPE_PRODUCTION_ORDER => new ProductionOrderReportExport(
                (string) $report->fromDate(),
                (string) $report->toDate(),
                $this->productionOrderReportService,
                $onRow
            ),
            ReportGeneration::TYPE_ORDER_DETAIL => new OrderDetailReportExport(
                (string) $report->fromDate(),
                (string) $report->toDate(),
                $this->orderDetailReportService,
                $onRow
            ),
            default => throw new \RuntimeException('Unknown report type.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function pdfViewData(ReportGeneration $report): array
    {
        $generatedAt = $report->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

        return match ($report->report_type) {
            ReportGeneration::TYPE_CLIENT_MODEL => $this->clientModelPdfData($report, $generatedAt),
            ReportGeneration::TYPE_PACKING_ORDER => $this->dateRangePdfData(
                $report,
                'Packing Order Report',
                'From: ',
                PackingOrderReportService::HEADINGS,
                $this->packingOrderReportService->rowsForDateRange(
                    (string) $report->fromDate(),
                    (string) $report->toDate()
                )->values()->all(),
                $generatedAt
            ),
            ReportGeneration::TYPE_PRODUCTION_ORDER => $this->dateRangePdfData(
                $report,
                'Production Order Report',
                'From: ',
                ProductionOrderReportService::HEADINGS,
                $this->productionOrderReportService->rowsForDateRange(
                    (string) $report->fromDate(),
                    (string) $report->toDate()
                )->values()->all(),
                $generatedAt
            ),
            ReportGeneration::TYPE_ORDER_DETAIL => $this->dateRangePdfData(
                $report,
                'Order Detail Report',
                'Order created from: ',
                OrderDetailReportService::HEADINGS,
                $this->orderDetailReportService->rowsForDateRange(
                    (string) $report->fromDate(),
                    (string) $report->toDate()
                )->values()->all(),
                $generatedAt
            ),
            default => throw new \RuntimeException('Unknown report type.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function clientModelPdfData(ReportGeneration $report, string $generatedAt): array
    {
        if ($report->isAllClients()) {
            $rows = $this->clientModelReportService->rowsForAllClients()->values()->all();
            $clientCount = collect($rows)->pluck('ClientId')->filter(fn ($id) => $id !== null && $id !== '---')->unique()->count();

            return [
                'report_title' => 'Client Model Report',
                'subtitle' => 'All Clients'
                    .' | Models: '.count($rows)
                    .' | Clients: '.$clientCount
                    .' | Generated: '.$generatedAt,
                'headings' => ClientModelReportService::HEADINGS,
                'rows' => $rows,
            ];
        }

        $rows = $this->clientModelReportService->rowsForClient((int) $report->clientId())->values()->all();

        return [
            'report_title' => 'Client Model Report',
            'subtitle' => 'Client: '.($report->label ?? '-')
                .' | Models: '.count($rows)
                .' | Generated: '.$generatedAt,
            'headings' => ClientModelReportService::HEADINGS,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function dateRangePdfData(
        ReportGeneration $report,
        string $title,
        string $fromPrefix,
        array $headings,
        array $rows,
        string $generatedAt
    ): array {
        $fromLabel = Carbon::parse((string) $report->fromDate())->format('d/m/Y');
        $toLabel = Carbon::parse((string) $report->toDate())->format('d/m/Y');

        return [
            'report_title' => $title,
            'subtitle' => $fromPrefix.$fromLabel.' | To: '.$toLabel
                .' | Rows: '.count($rows)
                .' | Generated: '.$generatedAt,
            'headings' => $headings,
            'rows' => $rows,
        ];
    }

    protected function emptyMessage(ReportGeneration $report): string
    {
        if ($report->report_type === ReportGeneration::TYPE_CLIENT_MODEL) {
            return $report->isAllClients()
                ? 'No client models found for any client.'
                : 'No client models found for this client.';
        }

        $fromLabel = Carbon::parse((string) $report->fromDate())->format('d/m/Y');
        $toLabel = Carbon::parse((string) $report->toDate())->format('d/m/Y');

        return match ($report->report_type) {
            ReportGeneration::TYPE_PACKING_ORDER => "No packing orders with a production date between {$fromLabel} and {$toLabel}. Try a wider date range.",
            ReportGeneration::TYPE_PRODUCTION_ORDER => "No production orders with a production date between {$fromLabel} and {$toLabel}. Try a wider date range.",
            ReportGeneration::TYPE_ORDER_DETAIL => "No production orders found for orders created between {$fromLabel} and {$toLabel}. Try a wider date range.",
            default => 'No rows found for this report.',
        };
    }
}
