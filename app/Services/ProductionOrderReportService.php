<?php

namespace App\Services;

use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductionOrderReportService
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
        'BigLoopQty',
        'SmallLoopQty',
        'LiningName',
        'SpecialDescriptionLine1',
        'SpecialDescriptionLine2',
        'LoopBandSize',
        'EndWidth',
        'BuckleType',
        'BuckleWidth',
        'DesignName',
        'DesignDescription',
        'VarnishColour',
        'TopThreadColour',
        'BottomThreadColour',
        'TopThreadSize',
        'BottomThreadSize',
        'StampingShort',
        'StampingLong',
        'PictureName',
        'NoteInfoLine1',
        'NoteInfoLine2',
        'DateInProduction',
        'QtyByProduction',
        'NoOfPrintOut',
        'Status',
        'FirstCuttingTool',
        'InsideCuttingTool',
        'LoopCuttingTool',
        'LoopFoldingTool',
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
            'BigLoopQty' => $this->formatValue($row->BigLoopQty ?? null),
            'SmallLoopQty' => $this->formatValue($row->SmallLoopQty ?? null),
            'LiningName' => $this->formatValue($row->LiningName ?? null),
            'SpecialDescriptionLine1' => $this->formatValue($row->SpecialDescriptionLine1 ?? null),
            'SpecialDescriptionLine2' => $this->formatValue($row->SpecialDescriptionLine2 ?? null),
            'LoopBandSize' => $this->formatValue($row->LoopBandSize ?? null),
            'EndWidth' => $this->formatValue($row->EndWidth ?? null),
            'BuckleType' => $row->BuckleType === null || $row->BuckleType === '' ? '----' : (string) $row->BuckleType,
            'BuckleWidth' => $this->formatValue($row->BuckleWidth ?? null),
            'DesignName' => $this->formatValue($row->DesignName ?? null),
            'DesignDescription' => $this->formatValue($row->DesignDescription ?? null),
            'VarnishColour' => $this->formatValue($row->VarnishColour ?? null),
            'TopThreadColour' => $this->formatValue($row->TopThreadColour ?? null),
            'BottomThreadColour' => $this->formatValue($row->BottomThreadColour ?? null),
            'TopThreadSize' => $this->formatValue($row->TopThreadSize ?? null),
            'BottomThreadSize' => $this->formatValue($row->BottomThreadSize ?? null),
            'StampingShort' => $this->formatValue($row->StampingShort ?? null),
            'StampingLong' => $this->formatValue($row->StampingLong ?? null),
            'PictureName' => $this->formatValue($row->PictureName ?? null),
            'NoteInfoLine1' => $this->formatValue($row->NoteInfoLine1 ?? null),
            'NoteInfoLine2' => $this->formatValue($row->NoteInfoLine2 ?? null),
            'DateInProduction' => $this->formatDate($row->DateInProduction ?? null),
            'QtyByProduction' => $this->formatValue($row->QtyByProduction ?? null),
            'NoOfPrintOut' => $this->formatValue($row->NoOfPrintOut ?? null),
            'Status' => $statusDisplay,
            'FirstCuttingTool' => $this->formatValue($row->FirstCuttingTool ?? null),
            'InsideCuttingTool' => $this->formatValue($row->InsideCuttingTool ?? null),
            'LoopCuttingTool' => $this->formatValue($row->LoopCuttingTool ?? null),
            'LoopFoldingTool' => $this->formatValue($row->LoopFoldingTool ?? null),
        ];
    }

    protected function baseQuery(string $fromDate, string $toDate): Builder
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        $qcSub = DB::table('quality_control')
            ->select([
                'orderspo_id',
                DB::raw('COALESCE(SUM(Qty), 0) as QtyByProduction'),
            ])
            ->groupBy('orderspo_id');

        return DB::table('ordersPO as po')
            ->leftJoin('orders as ord', 'ord.OrderId', '=', 'po.OrderId')
            ->leftJoin('clientmodel as cm', function ($join) {
                $join->on('cm.ModelNo', '=', 'po.ClientModel')
                    ->on('cm.ClientId', '=', 'ord.ClientId');
            })
            ->leftJoin('linings as lining', 'lining.LiningId', '=', 'cm.LiningId')
            ->leftJoin('special as special1', 'special1.SpecialId', '=', 'cm.SpecialId1')
            ->leftJoin('special as special2', 'special2.SpecialId', '=', 'cm.SpecialId2')
            ->leftJoin('loopband', 'loopband.LoopBandId', '=', 'cm.LoopBandId')
            ->leftJoin('endwidth', 'endwidth.EndWidthId', '=', 'cm.EndWidthId')
            ->leftJoin('buckle', 'buckle.BuckleId', '=', 'cm.BuckleId')
            ->leftJoin('bucklewidth', 'bucklewidth.BuckleWidthId', '=', 'cm.BuckleWidthId')
            ->leftJoin('designname', 'designname.DesignNameId', '=', 'cm.DesignNameId')
            ->leftJoin('designdescription', 'designdescription.DesignDescriptionId', '=', 'cm.DesignDescriptionId')
            ->leftJoin('colour as varnish_colour', 'varnish_colour.ColourId', '=', 'cm.VarnishColourId')
            ->leftJoin('colour as top_thread_colour', 'top_thread_colour.ColourId', '=', 'cm.TopThreadColourId')
            ->leftJoin('colour as bottom_thread_colour', 'bottom_thread_colour.ColourId', '=', 'cm.BottomThreadColourId')
            ->leftJoin('threadsize as top_thread_size', 'top_thread_size.ThreadSizeId', '=', 'cm.TopThreadSizeId')
            ->leftJoin('threadsize as bottom_thread_size', 'bottom_thread_size.ThreadSizeId', '=', 'cm.BottomThreadSizeId')
            ->leftJoin('stampingshort', 'stampingshort.StampingShortId', '=', 'cm.StampingShortId')
            ->leftJoin('stampinglong', 'stampinglong.StampingLongId', '=', 'cm.StampingLongId')
            ->leftJoin('strappictures', 'strappictures.StrapPictureId', '=', 'cm.StrapPictureId')
            ->leftJoin('noteinfoline1', 'noteinfoline1.NoteInfoLine1Id', '=', 'cm.NoteInfoLine1Id')
            ->leftJoin('noteinfoline2', 'noteinfoline2.NoteInfoLine2Id', '=', 'cm.NoteInfoLine2Id')
            ->leftJoin('tools as first_tool', 'first_tool.ToolId', '=', 'cm.FirstCuttingToolId')
            ->leftJoin('tools as inside_tool', 'inside_tool.ToolId', '=', 'cm.InsideCuttingToolId')
            ->leftJoin('tools as loop_cut_tool', 'loop_cut_tool.ToolId', '=', 'cm.LoopCuttingToolId')
            ->leftJoin('tools as loop_fold_tool', 'loop_fold_tool.ToolId', '=', 'cm.LoopFoldingToolId')
            ->leftJoinSub($qcSub, 'qc_agg', 'qc_agg.orderspo_id', '=', 'po.id')
            ->whereNotNull('po.DateInProduction')
            ->whereBetween('po.DateInProduction', [$from->toDateString(), $to->toDateString()])
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
                'cm.BigLoopQty',
                'cm.SmallLoopQty',
                'lining.Name as LiningName',
                'special1.Description as SpecialDescriptionLine1',
                'special2.Description as SpecialDescriptionLine2',
                'loopband.LoopBandSize',
                'endwidth.EndWidthSize as EndWidth',
                'buckle.BuckleType',
                'bucklewidth.BuckleWidthSize as BuckleWidth',
                'designname.Name as DesignName',
                'designdescription.Description as DesignDescription',
                'varnish_colour.ColourName as VarnishColour',
                'top_thread_colour.ColourName as TopThreadColour',
                'bottom_thread_colour.ColourName as BottomThreadColour',
                'top_thread_size.ThreadSize as TopThreadSize',
                'bottom_thread_size.ThreadSize as BottomThreadSize',
                'stampingshort.Description as StampingShort',
                'stampinglong.Description as StampingLong',
                'strappictures.Name as PictureName',
                'noteinfoline1.Line1 as NoteInfoLine1',
                'noteinfoline2.Line2 as NoteInfoLine2',
                'po.DateInProduction',
                'qc_agg.QtyByProduction',
                'po.NoOfPrintOut',
                'po.Status',
                'first_tool.Name as FirstCuttingTool',
                'inside_tool.Name as InsideCuttingTool',
                'loop_cut_tool.Name as LoopCuttingTool',
                'loop_fold_tool.Name as LoopFoldingTool',
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

    protected function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '---';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
