<?php

namespace App\Http\Controllers;

use App\Exports\ClientModelReportExport;
use App\Exports\OrderDetailReportExport;
use App\Exports\PackingOrderReportExport;
use App\Exports\ProductionOrderReportExport;
use App\Models\Client;
use App\Models\ReportGeneration;
use App\Services\ClientModelReportService;
use App\Services\OrderDetailReportService;
use App\Services\PackingOrderReportService;
use App\Services\ProductionOrderReportService;
use App\Services\ReportGenerationService;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ClientModelReportService $clientModelReportService,
        protected PackingOrderReportService $packingOrderReportService,
        protected ProductionOrderReportService $productionOrderReportService,
        protected OrderDetailReportService $orderDetailReportService,
        protected ReportGenerationService $reportGenerationService
    ) {}

    public function clientModelIndex(Request $request): Response
    {
        $this->ensureCanViewReports();

        return Inertia::render('Admin/Reports/ClientModelReport/Index', [
            'reportRuns' => $this->reportGenerationService
                ->listForType(ReportGeneration::TYPE_CLIENT_MODEL)
                ->values()
                ->all(),
        ]);
    }

    public function clientModelGenerate(Request $request): RedirectResponse
    {
        $this->ensureCanViewReports();

        $clientId = $request->integer('client_id');
        if (! $clientId) {
            return redirect()
                ->route('admin.reports.client-model.index')
                ->with('error', 'Please select a client.');
        }

        $client = $this->clientModelReportService->findClient($clientId);
        if (! $client) {
            return redirect()
                ->route('admin.reports.client-model.index')
                ->with('error', 'Client not found.');
        }

        $this->reportGenerationService->createClientModelReport(
            (int) auth()->id(),
            $client->id,
            $client->client_name
        );

        return redirect()
            ->route('admin.reports.client-model.index')
            ->with('success', 'Report queued for '.$client->client_name.'. You can keep working while it generates.');
    }

    public function clientModelGenerateAll(Request $request): RedirectResponse
    {
        $this->ensureCanViewReports();

        $this->reportGenerationService->createClientModelReportAllClients((int) auth()->id());

        return redirect()
            ->route('admin.reports.client-model.index')
            ->with('success', 'Report queued for all clients. You can keep working while it generates.');
    }

    public function packingOrderIndex(Request $request): Response
    {
        $this->ensureCanViewReports();

        $today = now()->toDateString();

        return Inertia::render('Admin/Reports/PackingOrderReport/Index', [
            'reportRuns' => $this->reportGenerationService
                ->listForType(ReportGeneration::TYPE_PACKING_ORDER)
                ->values()
                ->all(),
            'defaultFromDate' => $today,
            'defaultToDate' => $today,
        ]);
    }

    public function packingOrderGenerate(Request $request): RedirectResponse
    {
        $this->ensureCanViewReports();

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $this->reportGenerationService->createPackingOrderReport(
            (int) auth()->id(),
            $validated['from_date'],
            $validated['to_date']
        );

        return redirect()
            ->route('admin.reports.packing-order.index')
            ->with('success', 'Packing order report queued. You can keep working while it generates.');
    }

    public function searchClients(Request $request): JsonResponse
    {
        $this->ensureCanViewReports();

        $q = trim((string) $request->get('q', ''));
        if ($q === '' || strlen($q) < 1) {
            return response()->json([]);
        }

        $clients = Client::query()
            ->select(['id', 'client_name'])
            ->where('client_name', 'like', '%'.$q.'%')
            ->orderBy('client_name')
            ->limit(50)
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->client_name,
            ]);

        return response()->json($clients);
    }

    public function reportGenerationStatus(Request $request): JsonResponse
    {
        $this->ensureCanViewReports();

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->filter(fn ($id) => ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(50)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([]);
        }

        $rows = ReportGeneration::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (ReportGeneration $generation) => $generation->toStatusArray())
            ->values();

        return response()->json($rows);
    }

    public function reportGenerationExportExcel(ReportGeneration $reportGeneration): BinaryFileResponse
    {
        $this->ensureCanExportReports();
        $this->ensureReportReady($reportGeneration);

        if ($reportGeneration->excel_path) {
            $absolute = Storage::disk('local')->path($reportGeneration->excel_path);
            abort_unless(is_file($absolute), 404, 'Report file is missing. Generate the report again.');

            return response()->download($absolute, $reportGeneration->excelFilename());
        }

        return match ($reportGeneration->report_type) {
            ReportGeneration::TYPE_CLIENT_MODEL => $this->exportClientModelExcel($reportGeneration),
            ReportGeneration::TYPE_PACKING_ORDER => $this->exportPackingOrderExcel($reportGeneration),
            ReportGeneration::TYPE_PRODUCTION_ORDER => $this->exportProductionOrderExcel($reportGeneration),
            ReportGeneration::TYPE_ORDER_DETAIL => $this->exportOrderDetailExcel($reportGeneration),
            default => abort(404, 'Report not found.'),
        };
    }

    public function reportGenerationDestroy(ReportGeneration $reportGeneration): RedirectResponse
    {
        $this->ensureCanViewReports();

        $redirectRoute = match ($reportGeneration->report_type) {
            ReportGeneration::TYPE_CLIENT_MODEL => 'admin.reports.client-model.index',
            ReportGeneration::TYPE_PACKING_ORDER => 'admin.reports.packing-order.index',
            ReportGeneration::TYPE_PRODUCTION_ORDER => 'admin.reports.production-order.index',
            ReportGeneration::TYPE_ORDER_DETAIL => 'admin.reports.order-detail.index',
            default => 'admin.reports.client-model.index',
        };

        $reportGeneration->delete();

        return redirect()
            ->route($redirectRoute)
            ->with('success', 'Report deleted.');
    }

    public function reportGenerationPdf(Request $request, ReportGeneration $reportGeneration)
    {
        $this->ensureCanExportReports();
        $this->ensureReportReady($reportGeneration);

        if ($request->boolean('preview')) {
            if ($reportGeneration->html_path) {
                $absolute = Storage::disk('local')->path($reportGeneration->html_path);
                abort_unless(is_file($absolute), 404, 'Report file is missing. Generate the report again.');

                return response()->file($absolute, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]);
            }

            if ($reportGeneration->excel_path) {
                abort(422, $reportGeneration->pdfUnavailableReason() ?? 'HTML preview is not available for this report.');
            }
        } elseif ($reportGeneration->pdf_path) {
            $absolute = Storage::disk('local')->path($reportGeneration->pdf_path);
            abort_unless(is_file($absolute), 404, 'Report file is missing. Generate the report again.');

            return response()->file($absolute, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$reportGeneration->pdfFilename().'"',
            ]);
        } elseif ($reportGeneration->excel_path) {
            abort(422, $reportGeneration->pdfUnavailableReason() ?? 'PDF is not available for this report.');
        }

        return match ($reportGeneration->report_type) {
            ReportGeneration::TYPE_CLIENT_MODEL => $this->pdfClientModelReport($request, $reportGeneration),
            ReportGeneration::TYPE_PACKING_ORDER => $this->pdfPackingOrderReport($request, $reportGeneration),
            ReportGeneration::TYPE_PRODUCTION_ORDER => $this->pdfProductionOrderReport($request, $reportGeneration),
            ReportGeneration::TYPE_ORDER_DETAIL => $this->pdfOrderDetailReport($request, $reportGeneration),
            default => abort(404, 'Report not found.'),
        };
    }

    public function productionOrderIndex(Request $request): Response
    {
        $this->ensureCanViewReports();

        $today = now()->toDateString();

        return Inertia::render('Admin/Reports/ProductionOrderReport/Index', [
            'reportRuns' => $this->reportGenerationService
                ->listForType(ReportGeneration::TYPE_PRODUCTION_ORDER)
                ->values()
                ->all(),
            'defaultFromDate' => $today,
            'defaultToDate' => $today,
        ]);
    }

    public function productionOrderGenerate(Request $request): RedirectResponse
    {
        $this->ensureCanViewReports();

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $this->reportGenerationService->createProductionOrderReport(
            (int) auth()->id(),
            $validated['from_date'],
            $validated['to_date']
        );

        return redirect()
            ->route('admin.reports.production-order.index')
            ->with('success', 'Production order report queued. You can keep working while it generates.');
    }

    public function orderDetailIndex(Request $request): Response
    {
        $this->ensureCanViewReports();

        $today = now()->toDateString();

        return Inertia::render('Admin/Reports/OrderDetailReport/Index', [
            'reportRuns' => $this->reportGenerationService
                ->listForType(ReportGeneration::TYPE_ORDER_DETAIL)
                ->values()
                ->all(),
            'defaultFromDate' => $today,
            'defaultToDate' => $today,
        ]);
    }

    public function orderDetailGenerate(Request $request): RedirectResponse
    {
        $this->ensureCanViewReports();

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $this->reportGenerationService->createOrderDetailReport(
            (int) auth()->id(),
            $validated['from_date'],
            $validated['to_date']
        );

        return redirect()
            ->route('admin.reports.order-detail.index')
            ->with('success', 'Order detail report queued. You can keep working while it generates.');
    }

    protected function exportClientModelExcel(ReportGeneration $reportGeneration): BinaryFileResponse
    {
        if ($reportGeneration->isAllClients()) {
            return Excel::download(
                new ClientModelReportExport(null, $this->clientModelReportService),
                'ClientModel-AllClients.xlsx'
            );
        }

        $clientId = $reportGeneration->clientId();
        if (! $clientId) {
            abort(422, 'Invalid report parameters.');
        }

        $client = $this->clientModelReportService->findClient($clientId);
        if (! $client) {
            abort(404, 'Client not found.');
        }

        return Excel::download(
            new ClientModelReportExport($clientId, $this->clientModelReportService),
            'ClientModel'.$clientId.'.xlsx'
        );
    }

    protected function exportPackingOrderExcel(ReportGeneration $reportGeneration): BinaryFileResponse
    {
        $fromDate = $reportGeneration->fromDate();
        $toDate = $reportGeneration->toDate();
        if (! $fromDate || ! $toDate) {
            abort(422, 'Invalid report parameters.');
        }

        $safeFrom = str_replace('-', '', $fromDate);
        $safeTo = str_replace('-', '', $toDate);

        return Excel::download(
            new PackingOrderReportExport($fromDate, $toDate, $this->packingOrderReportService),
            'PackingOrder-'.$safeFrom.'-'.$safeTo.'.xlsx'
        );
    }

    protected function exportProductionOrderExcel(ReportGeneration $reportGeneration): BinaryFileResponse
    {
        $fromDate = $reportGeneration->fromDate();
        $toDate = $reportGeneration->toDate();
        if (! $fromDate || ! $toDate) {
            abort(422, 'Invalid report parameters.');
        }

        $safeFrom = str_replace('-', '', $fromDate);
        $safeTo = str_replace('-', '', $toDate);

        return Excel::download(
            new ProductionOrderReportExport($fromDate, $toDate, $this->productionOrderReportService),
            'ProductionOrder-'.$safeFrom.'-'.$safeTo.'.xlsx'
        );
    }

    protected function exportOrderDetailExcel(ReportGeneration $reportGeneration): BinaryFileResponse
    {
        $fromDate = $reportGeneration->fromDate();
        $toDate = $reportGeneration->toDate();
        if (! $fromDate || ! $toDate) {
            abort(422, 'Invalid report parameters.');
        }

        $safeFrom = str_replace('-', '', $fromDate);
        $safeTo = str_replace('-', '', $toDate);

        return Excel::download(
            new OrderDetailReportExport($fromDate, $toDate, $this->orderDetailReportService),
            'OrderDetail-'.$safeFrom.'-'.$safeTo.'.xlsx'
        );
    }

    protected function pdfClientModelReport(Request $request, ReportGeneration $reportGeneration)
    {
        try {
            if ($reportGeneration->isAllClients()) {
                $rows = $this->clientModelReportService->rowsForAllClients()->values()->all();

                if (count($rows) === 0) {
                    abort(404, 'No client models found.');
                }

                $clientCount = $reportGeneration->allClientsCount() ?? $this->clientModelReportService->clientsWithModelCounts()->count();
                $generatedAt = $reportGeneration->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

                $data = [
                    'report_title' => 'Client Model Report',
                    'subtitle' => 'All Clients'
                        .' | Models: '.count($rows)
                        .' | Clients: '.$clientCount
                        .' | Generated: '.$generatedAt,
                    'headings' => ClientModelReportService::HEADINGS,
                    'rows' => $rows,
                ];

                return $this->renderGridReportPdf($request, $data, 'ClientModel-AllClients');
            }

            $clientId = $reportGeneration->clientId();
            if (! $clientId) {
                abort(422, 'Invalid report parameters.');
            }

            $client = $this->clientModelReportService->findClient($clientId);
            if (! $client) {
                abort(404, 'Client not found.');
            }

            $rows = $this->clientModelReportService->rowsForClient($client->id)->values()->all();

            if (count($rows) === 0) {
                abort(404, 'No client models found for this client.');
            }

            $data = [
                'report_title' => 'Client Model Report',
                'subtitle' => 'Client: '.($client->client_name ?? '-')
                    .' | Models: '.count($rows)
                    .' | Generated: '.($reportGeneration->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')),
                'headings' => ClientModelReportService::HEADINGS,
                'rows' => $rows,
            ];

            return $this->renderGridReportPdf($request, $data, 'ClientModel-'.preg_replace('/[^a-zA-Z0-9\-_]/', '_', $client->client_name ?? 'client').'-'.$client->id);
        } catch (\Throwable $e) {
            Log::error('Error generating client model report PDF: '.$e->getMessage(), [
                'report_generation_id' => $reportGeneration->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('admin.reports.client-model.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    protected function pdfPackingOrderReport(Request $request, ReportGeneration $reportGeneration)
    {
        $fromDate = $reportGeneration->fromDate();
        $toDate = $reportGeneration->toDate();
        if (! $fromDate || ! $toDate) {
            abort(422, 'Invalid report parameters.');
        }

        try {
            $rows = $this->packingOrderReportService->rowsForDateRange($fromDate, $toDate)->values()->all();

            if (count($rows) === 0) {
                abort(404, 'No packing orders found for this date range.');
            }

            $fromLabel = Carbon::parse($fromDate)->format('d/m/Y');
            $toLabel = Carbon::parse($toDate)->format('d/m/Y');

            $data = [
                'report_title' => 'Packing Order Report',
                'subtitle' => 'From: '.$fromLabel.' | To: '.$toLabel
                    .' | Rows: '.count($rows)
                    .' | Generated: '.($reportGeneration->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')),
                'headings' => PackingOrderReportService::HEADINGS,
                'rows' => $rows,
            ];

            return $this->renderGridReportPdf($request, $data, 'PackingOrder-'.$fromDate.'-'.$toDate);
        } catch (\Throwable $e) {
            Log::error('Error generating packing order report PDF: '.$e->getMessage(), [
                'report_generation_id' => $reportGeneration->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('admin.reports.packing-order.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    protected function pdfProductionOrderReport(Request $request, ReportGeneration $reportGeneration)
    {
        $fromDate = $reportGeneration->fromDate();
        $toDate = $reportGeneration->toDate();
        if (! $fromDate || ! $toDate) {
            abort(422, 'Invalid report parameters.');
        }

        try {
            $rows = $this->productionOrderReportService->rowsForDateRange($fromDate, $toDate)->values()->all();

            if (count($rows) === 0) {
                abort(404, 'No production orders found for this date range.');
            }

            $fromLabel = Carbon::parse($fromDate)->format('d/m/Y');
            $toLabel = Carbon::parse($toDate)->format('d/m/Y');

            $data = [
                'report_title' => 'Production Order Report',
                'subtitle' => 'From: '.$fromLabel.' | To: '.$toLabel
                    .' | Rows: '.count($rows)
                    .' | Generated: '.($reportGeneration->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')),
                'headings' => ProductionOrderReportService::HEADINGS,
                'rows' => $rows,
            ];

            return $this->renderGridReportPdf($request, $data, 'ProductionOrder-'.$fromDate.'-'.$toDate);
        } catch (\Throwable $e) {
            Log::error('Error generating production order report PDF: '.$e->getMessage(), [
                'report_generation_id' => $reportGeneration->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('admin.reports.production-order.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    protected function pdfOrderDetailReport(Request $request, ReportGeneration $reportGeneration)
    {
        $fromDate = $reportGeneration->fromDate();
        $toDate = $reportGeneration->toDate();
        if (! $fromDate || ! $toDate) {
            abort(422, 'Invalid report parameters.');
        }

        try {
            $rows = $this->orderDetailReportService->rowsForDateRange($fromDate, $toDate)->values()->all();

            if (count($rows) === 0) {
                abort(404, 'No order details found for this date range.');
            }

            $fromLabel = Carbon::parse($fromDate)->format('d/m/Y');
            $toLabel = Carbon::parse($toDate)->format('d/m/Y');

            $data = [
                'report_title' => 'Order Detail Report',
                'subtitle' => 'Order created from: '.$fromLabel.' | To: '.$toLabel
                    .' | Rows: '.count($rows)
                    .' | Generated: '.($reportGeneration->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')),
                'headings' => OrderDetailReportService::HEADINGS,
                'rows' => $rows,
            ];

            return $this->renderGridReportPdf($request, $data, 'OrderDetail-'.$fromDate.'-'.$toDate);
        } catch (\Throwable $e) {
            Log::error('Error generating order detail report PDF: '.$e->getMessage(), [
                'report_generation_id' => $reportGeneration->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('admin.reports.order-detail.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderGridReportPdf(Request $request, array $data, string $filenameBase)
    {
        $html = view('pdf.report-data-grid', $data)->render();

        if ($request->boolean('preview')) {
            return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $pdf = PDF::loadHTML($html)
            ->setOption('margin-top', 5)
            ->setOption('margin-bottom', 5)
            ->setOption('margin-left', 5)
            ->setOption('margin-right', 5)
            ->setOption('encoding', 'UTF-8')
            ->setOption('orientation', 'Landscape')
            ->setOption('no-outline', true);

        return $pdf->inline($filenameBase.'.pdf');
    }

    protected function ensureReportReady(ReportGeneration $reportGeneration): void
    {
        if ($reportGeneration->status === ReportGeneration::STATUS_FAILED) {
            abort(422, $reportGeneration->error ?: 'This report failed to generate.');
        }

        if ($reportGeneration->status !== ReportGeneration::STATUS_READY) {
            abort(409, 'This report is still generating.');
        }
    }

    protected function ensureCanViewReports(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('Admin') || $user->hasRole('admin') || $user->hasRole('Administrator')) {
            return;
        }

        if (! $user->can('reports.view')) {
            abort(403, 'You do not have permission to view reports.');
        }
    }

    protected function ensureCanExportReports(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('Admin') || $user->hasRole('admin') || $user->hasRole('Administrator')) {
            return;
        }

        if (! $user->can('reports.export') && ! $user->can('reports.view')) {
            abort(403, 'You do not have permission to export reports.');
        }
    }
}
