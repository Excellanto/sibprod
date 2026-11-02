<?php

namespace App\Jobs;

use App\Exceptions\ReportCancelledException;
use App\Models\ReportGeneration;
use App\Services\ReportFileWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $reportGenerationId)
    {
        $this->onQueue('reports');
    }

    public function handle(ReportFileWriter $writer): void
    {
        $report = ReportGeneration::query()->find($this->reportGenerationId);
        if (! $report || $report->status === ReportGeneration::STATUS_READY) {
            return;
        }

        $lock = Cache::lock('report-generation-'.$report->id, 3700);
        if (! $lock->get()) {
            return;
        }

        try {
            ini_set('memory_limit', '1024M');

            $report->update([
                'status' => ReportGeneration::STATUS_RUNNING,
                'progress' => 0,
                'error' => null,
            ]);

            $writer->write($report->fresh());
        } catch (ReportCancelledException) {
            $report->deleteStoredFiles();
        } catch (Throwable $e) {
            Log::error('Report generation failed: '.$e->getMessage(), [
                'report_generation_id' => $this->reportGenerationId,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->markFailed($e);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->markFailed($e);
    }

    protected function markFailed(?Throwable $e): void
    {
        $report = ReportGeneration::query()->find($this->reportGenerationId);
        if (! $report || $report->status === ReportGeneration::STATUS_READY) {
            return;
        }

        $report->deleteStoredFiles();
        $report->update([
            'status' => ReportGeneration::STATUS_FAILED,
            'excel_path' => null,
            'pdf_path' => null,
            'html_path' => null,
            'error' => Str::limit($e?->getMessage() ?: 'Report generation failed.', 500),
        ]);
    }
}
