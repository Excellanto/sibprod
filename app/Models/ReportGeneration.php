<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportGeneration extends Model
{
    public const TYPE_CLIENT_MODEL = 'client_model';

    public const TYPE_PRODUCTION_ORDER = 'production_order';

    public const TYPE_PACKING_ORDER = 'packing_order';

    public const TYPE_ORDER_DETAIL = 'order_detail';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'report_type',
        'user_id',
        'label',
        'parameters',
        'row_count',
        'status',
        'progress',
        'processed_rows',
        'total_rows',
        'error',
        'excel_path',
        'pdf_path',
        'html_path',
    ];

    protected $casts = [
        'parameters' => 'array',
        'row_count' => 'integer',
        'progress' => 'integer',
        'processed_rows' => 'integer',
        'total_rows' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ReportGeneration $report) {
            $report->deleteQueuedJob();
            $report->deleteStoredFiles();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clientId(): ?int
    {
        if ($this->isAllClients()) {
            return null;
        }

        $id = $this->parameters['client_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    public function isAllClients(): bool
    {
        return (bool) ($this->parameters['all_clients'] ?? false);
    }

    public function allClientsCount(): ?int
    {
        $count = $this->parameters['client_count'] ?? null;

        return $count !== null ? (int) $count : null;
    }

    public function fromDate(): ?string
    {
        return $this->parameters['from_date'] ?? null;
    }

    public function toDate(): ?string
    {
        return $this->parameters['to_date'] ?? null;
    }

    public function pdfAvailable(): bool
    {
        if (($this->status ?: self::STATUS_READY) !== self::STATUS_READY) {
            return false;
        }

        if ($this->excel_path) {
            return (bool) $this->pdf_path;
        }

        return true;
    }

    public function pdfUnavailableReason(): ?string
    {
        if ($this->pdfAvailable() || ($this->status ?: self::STATUS_READY) !== self::STATUS_READY) {
            return null;
        }

        return $this->error ?: 'PDF and HTML are not available for this report.';
    }

    public function excelFilename(): string
    {
        return match ($this->report_type) {
            self::TYPE_CLIENT_MODEL => $this->isAllClients()
                ? 'ClientModel-AllClients.xlsx'
                : 'ClientModel'.($this->clientId() ?? 'client').'.xlsx',
            self::TYPE_PACKING_ORDER => 'PackingOrder-'.$this->compactDate($this->fromDate()).'-'.$this->compactDate($this->toDate()).'.xlsx',
            self::TYPE_PRODUCTION_ORDER => 'ProductionOrder-'.$this->compactDate($this->fromDate()).'-'.$this->compactDate($this->toDate()).'.xlsx',
            self::TYPE_ORDER_DETAIL => 'OrderDetail-'.$this->compactDate($this->fromDate()).'-'.$this->compactDate($this->toDate()).'.xlsx',
            default => 'report-'.$this->id.'.xlsx',
        };
    }

    public function pdfFilename(): string
    {
        if ($this->report_type === self::TYPE_CLIENT_MODEL && ! $this->isAllClients()) {
            $name = preg_replace('/[^a-zA-Z0-9\-_]/', '_', (string) ($this->label ?? 'client'));

            return 'ClientModel-'.$name.'-'.($this->clientId() ?? '').'.pdf';
        }

        return match ($this->report_type) {
            self::TYPE_CLIENT_MODEL => 'ClientModel-AllClients.pdf',
            self::TYPE_PACKING_ORDER => 'PackingOrder-'.$this->fromDate().'-'.$this->toDate().'.pdf',
            self::TYPE_PRODUCTION_ORDER => 'ProductionOrder-'.$this->fromDate().'-'.$this->toDate().'.pdf',
            self::TYPE_ORDER_DETAIL => 'OrderDetail-'.$this->fromDate().'-'.$this->toDate().'.pdf',
            default => 'report-'.$this->id.'.pdf',
        };
    }

    public function deleteQueuedJob(): void
    {
        $reportId = (int) $this->id;

        $jobIds = DB::table('jobs')
            ->where('queue', 'reports')
            ->whereNull('reserved_at')
            ->where('payload', 'like', '%GenerateReportJob%')
            ->get(['id', 'payload'])
            ->filter(function ($job) use ($reportId) {
                $payload = json_decode($job->payload, true);
                $command = $payload['data']['command'] ?? '';

                return is_string($command)
                    && preg_match('/reportGenerationId";i:(\d+);/', $command, $matches) === 1
                    && (int) $matches[1] === $reportId;
            })
            ->pluck('id');

        if ($jobIds->isEmpty()) {
            return;
        }

        DB::table('jobs')
            ->whereIn('id', $jobIds)
            ->whereNull('reserved_at')
            ->delete();
    }

    public function deleteStoredFiles(): void
    {
        $disk = Storage::disk('local');
        $paths = array_filter([
            $this->excel_path,
            $this->pdf_path,
            $this->html_path,
            'reports/'.$this->id.'.xlsx',
            'reports/'.$this->id.'.pdf',
            'reports/'.$this->id.'.html',
        ]);

        foreach (array_unique($paths) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status ?: self::STATUS_READY,
            'progress' => (int) ($this->progress ?? 0),
            'processed_rows' => (int) ($this->processed_rows ?? 0),
            'total_rows' => $this->total_rows,
            'row_count' => (int) $this->row_count,
            'error' => $this->error,
            'pdf_available' => $this->pdfAvailable(),
            'html_available' => $this->pdfAvailable(),
            'pdf_unavailable_reason' => $this->pdfUnavailableReason(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        $base = [
            'id' => $this->id,
            'created_at' => $this->created_at?->format('d/m/Y H:i') ?? '-',
            'row_count' => $this->row_count,
            'report_type' => $this->report_type,
            ...$this->toStatusArray(),
        ];

        if (in_array($this->report_type, [
            self::TYPE_PACKING_ORDER,
            self::TYPE_PRODUCTION_ORDER,
            self::TYPE_ORDER_DETAIL,
        ], true)) {
            $from = $this->fromDate() ?? '';
            $to = $this->toDate() ?? '';

            return array_merge($base, [
                'date_range' => $this->formatDateRangeLabel($from, $to),
                'from_date' => $from,
                'to_date' => $to,
            ]);
        }

        return array_merge($base, [
            'client_id' => $this->clientId(),
            'client_name' => $this->label ?? '-',
        ]);
    }

    protected function formatDateRangeLabel(string $from, string $to): string
    {
        if ($from === '' && $to === '') {
            return '-';
        }

        $fromLabel = $from !== '' ? \Carbon\Carbon::parse($from)->format('d/m/Y') : '?';
        $toLabel = $to !== '' ? \Carbon\Carbon::parse($to)->format('d/m/Y') : '?';

        return $fromLabel.' – '.$toLabel;
    }

    protected function compactDate(?string $date): string
    {
        return str_replace('-', '', (string) $date);
    }
}
