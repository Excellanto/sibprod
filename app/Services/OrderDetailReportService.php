<?php

namespace App\Services;

use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderDetailReportService
{
    public const HEADINGS = [
        'OrderId',
        'PsNumber',
        'ClientModel',
        'RefNumber',
        'VarnishId',
        'SpecialId',
        'QtyByOrder',
        'Gold',
        'Silver',
        'Status',
    ];

    public function countForDateRange(string $fromDate, string $toDate): int
    {
        return (int) $this->baseQuery($fromDate, $toDate)->count();
    }

    public function rowsForDateRange(string $fromDate, string $toDate): Collection
    {
        return $this->baseQuery($fromDate, $toDate)
            ->orderBy('po.id')
            ->get()
            ->map(fn ($row) => $this->mapExportRow($row));
    }

    public function rowsForDateRangeQuery(string $fromDate, string $toDate): Builder
    {
        return $this->baseQuery($fromDate, $toDate)->orderBy('po.id');
    }

    public function mapExportRow(object $row): array
    {
        static $statusMapCache = null;
        $statusMap = $statusMapCache ??= $this->orderPosStatusMap();
        $statusRaw = $row->Status ?? null;
        $statusDisplay = $statusRaw !== null && isset($statusMap[(string) $statusRaw])
            ? $statusMap[(string) $statusRaw]
            : ($statusRaw !== null && isset($statusMap[(int) $statusRaw])
                ? $statusMap[(int) $statusRaw]
                : (string) ($statusRaw ?? '---'));

        return [
            'OrderId' => $row->OrderId ?? '---',
            'PsNumber' => $row->PsNumber ?? '---',
            'ClientModel' => $row->ClientModel ?? '---',
            'RefNumber' => $row->RefNumber ?? '---',
            'VarnishId' => $this->formatId($row->VarnishId ?? null),
            'SpecialId' => $this->formatId($row->SpecialId ?? null),
            'QtyByOrder' => $this->formatValue($row->QtyByOrder ?? null),
            'Gold' => $this->formatValue($row->Gold ?? null),
            'Silver' => $this->formatValue($row->Silver ?? null),
            'Status' => $statusDisplay,
        ];
    }

    protected function baseQuery(string $fromDate, string $toDate): Builder
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        return DB::table('ordersPO as po')
            ->leftJoin('orders as ord', 'ord.OrderId', '=', 'po.OrderId')
            ->leftJoin('clientmodel as cm', function ($join) {
                $join->on('cm.ModelNo', '=', 'po.ClientModel')
                    ->on('cm.ClientId', '=', 'ord.ClientId');
            })
            ->whereBetween('ord.created_at', [$from, $to])
            ->select([
                'po.id as po_id',
                'po.OrderId',
                'po.PsNumber',
                'po.ClientModel',
                'po.RefNumber',
                'cm.VarnishColourId as VarnishId',
                'cm.SpecialId1 as SpecialId',
                'po.QtyByOrder',
                'po.Gold',
                'po.Silver',
                'po.Status',
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function orderPosStatusMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $setting = SiteSetting::where('key', 'orderpos_status')->first();
        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (is_array($decoded)) {
                foreach ($decoded as $status) {
                    if (isset($status['id'], $status['name'])) {
                        $map[(string) $status['id']] = $status['name'];
                        $map[(int) $status['id']] = $status['name'];
                    }
                }
            }
        }

        return $map;
    }

    protected function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '---';
        }

        return (string) $value;
    }

    protected function formatId(mixed $value): string|int
    {
        if ($value === null || $value === '') {
            return '---';
        }

        return (int) $value;
    }
}
