<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPO;
use App\Models\Package;
use App\Models\QualityControl;
use App\Models\QcRegister;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardDataService
{
    public function orderFunnel(): array
    {
        $ordersStatusIds = $this->resolveOrdersStatusIds();
        $statusIds = [
            $ordersStatusIds['newlyCreated'],
            $ordersStatusIds['inProduction'],
            $ordersStatusIds['qcDone'],
        ];

        $counts = Order::query()
            ->selectRaw('Status, COUNT(*) as count')
            ->whereIn('Status', $statusIds)
            ->groupBy('Status')
            ->pluck('count', 'Status');

        $getCount = function (int $statusId) use ($counts): int {
            return (int) ($counts[(string) $statusId] ?? $counts[$statusId] ?? 0);
        };

        return [
            [
                'key' => 'newly_created',
                'label' => $ordersStatusIds['labels']['newlyCreated'],
                'count' => $getCount($ordersStatusIds['newlyCreated']),
            ],
            [
                'key' => 'in_production',
                'label' => $ordersStatusIds['labels']['inProduction'],
                'count' => $getCount($ordersStatusIds['inProduction']),
            ],
            [
                'key' => 'qc_done',
                'label' => $ordersStatusIds['labels']['qcDone'],
                'count' => $getCount($ordersStatusIds['qcDone']),
            ],
        ];
    }

    public function productionPipeline(): array
    {
        $orderPoStatusIds = $this->resolveOrderPoStatusIds();
        $statusMap = [
            'in_prod' => $orderPoStatusIds['inProduction'],
            'qc_start' => $orderPoStatusIds['qcStarted'],
            'qc_comp' => $orderPoStatusIds['produced'],
        ];

        $counts = OrderPO::query()
            ->selectRaw('Status, COUNT(*) as count')
            ->whereIn('Status', array_values($statusMap))
            ->groupBy('Status')
            ->pluck('count', 'Status');

        $getCount = function (int $statusId) use ($counts): int {
            return (int) ($counts[(string) $statusId] ?? $counts[$statusId] ?? 0);
        };

        $segments = [
            ['key' => 'in_prod', 'label' => 'In Prod', 'count' => $getCount($statusMap['in_prod'])],
            ['key' => 'qc_start', 'label' => 'QC Start', 'count' => $getCount($statusMap['qc_start'])],
            ['key' => 'qc_comp', 'label' => 'QC Comp', 'count' => $getCount($statusMap['qc_comp'])],
        ];

        $total = array_sum(array_column($segments, 'count'));

        foreach ($segments as &$segment) {
            $segment['percent'] = $total > 0
                ? (int) round($segment['count'] / $total * 100)
                : 0;
        }
        unset($segment);

        return [
            'total' => $total,
            'segments' => $segments,
        ];
    }

    public function topClients(): array
    {
        $inProductionOrderStatusId = $this->resolveOrdersStatusIds()['inProduction'];

        $rows = Order::query()
            ->join('clients', 'clients.id', '=', 'orders.ClientId')
            ->groupBy('clients.id', 'clients.client_name')
            ->selectRaw(
                'clients.client_name as client,
				SUM(CASE WHEN orders.Status = ? THEN orders.QtyTotal ELSE 0 END) as open_qty,
				SUM(orders.QtyTotal) as total_qty,
				MIN(CASE WHEN orders.Status = ? THEN orders.DeliveryDate END) as nearest_delivery',
                [$inProductionOrderStatusId, $inProductionOrderStatusId]
            )
            ->havingRaw('SUM(CASE WHEN orders.Status = ? THEN orders.QtyTotal ELSE 0 END) > 0', [$inProductionOrderStatusId])
            ->orderByDesc('open_qty')
            ->limit(5)
            ->get();

        return $rows->map(function ($row) {
            $nearestDelivery = $row->nearest_delivery;
            if ($nearestDelivery instanceof \DateTimeInterface) {
                $nearestDelivery = $nearestDelivery->format('Y-m-d');
            } elseif ($nearestDelivery) {
                $nearestDelivery = substr((string) $nearestDelivery, 0, 10);
            }

            return [
                'client' => $row->client,
                'open_qty' => (int) $row->open_qty,
                'total_qty' => (int) $row->total_qty,
                'nearest_delivery' => $nearestDelivery ?: null,
            ];
        })->values()->all();
    }

    public function production(): array
    {
        $workSlips = $this->workSlipProgress();
        $qc = $this->qcPassFail();
        $output = $this->outputVolume();

        $inProgress = 0;
        $inProgressQty = 0;
        foreach ($workSlips['segments'] as $segment) {
            if ($segment['key'] !== 'produced') {
                $inProgress += $segment['count'];
                $inProgressQty += $segment['qty'];
            }
        }

        return [
            'summary' => [
                ['label' => 'Slips in progress', 'value' => $this->formatInt($inProgress), 'hint' => $this->formatInt($inProgressQty).' pcs'],
                ['label' => 'QC pass rate', 'value' => $qc['passRate'].'%', 'hint' => $this->formatInt($qc['passQty']).' pass / '.$this->formatInt($qc['failQty']).' fail'],
                ['label' => 'Output this month', 'value' => $this->formatInt($output['month']), 'hint' => $this->formatInt($output['week']).' this week'],
            ],
            'workSlips' => $workSlips,
            'qc' => $qc,
            'output' => $output,
        ];
    }

    public function sales(): array
    {
        $ordersStatus = $this->resolveOrdersStatusIds();
        $newOrders = $this->newOrders($ordersStatus['newlyCreated']);
        $topProducts = $this->topProducts();
        $materials = $this->materialsUsed();
        $invoices = $this->invoicePeriods();

        $monthInvoices = array_slice($invoices['month'], -1);
        $monthInvoice = $monthInvoices[0] ?? ['count' => 0, 'amount' => 0];
        $topName = $topProducts[0]['name'] ?? '—';

        return [
            'summary' => [
                ['label' => 'New orders this month', 'value' => $this->formatInt($newOrders['month']['count']), 'hint' => $this->formatInt($newOrders['month']['qty']).' pcs'],
                ['label' => 'Top product', 'value' => $topName, 'hint' => isset($topProducts[0]) ? $this->formatInt($topProducts[0]['qty']).' pcs invoiced' : 'No invoice lines yet'],
                ['label' => 'Invoices this month', 'value' => $this->formatInt($monthInvoice['count']), 'hint' => $this->formatAmount($monthInvoice['amount'])],
            ],
            'newOrders' => $newOrders,
            'topProducts' => $topProducts,
            'materials' => $materials,
            'invoices' => $invoices,
            'orderDates' => $this->orderDateBars(),
        ];
    }

    public function packing(): array
    {
        $packed = $this->packedOrders();
        $qcStatus = $this->qcStatusBreakdown();
        $dispatch = $this->dispatchStatus();

        $openQc = 0;
        foreach ($qcStatus as $row) {
            if (in_array($row['key'], ['pending', 'in_progress'], true)) {
                $openQc += $row['count'];
            }
        }

        $sent = 0;
        foreach ($dispatch['invoices'] as $row) {
            if ($row['key'] === 'sent_to_client') {
                $sent = $row['count'];
            }
        }

        return [
            'summary' => [
                ['label' => 'Orders packed', 'value' => $this->formatInt($packed['orders']), 'hint' => $this->formatInt($packed['qty']).' pcs in '.$this->formatInt($packed['boxes']).' boxes'],
                ['label' => 'QC still open', 'value' => $this->formatInt($openQc), 'hint' => 'Pending or in progress'],
                ['label' => 'Sent to client', 'value' => $this->formatInt($sent), 'hint' => $this->formatInt($dispatch['shipped']).' packages shipped'],
            ],
            'packed' => $packed,
            'qcStatus' => $qcStatus,
            'dispatch' => $dispatch,
        ];
    }

    private function workSlipProgress(): array
    {
        $statuses = $this->decodeSiteSettingJson('orderpos_status');
        if (empty($statuses)) {
            $statuses = [
                ['id' => 1, 'name' => 'Newly Added'],
                ['id' => 2, 'name' => 'In Production'],
                ['id' => 3, 'name' => 'QC Started'],
                ['id' => 4, 'name' => 'Produced'],
            ];
        }

        $ids = [];
        foreach ($statuses as $status) {
            if (isset($status['id'])) {
                $ids[] = (int) $status['id'];
            }
        }

        $rows = OrderPO::query()
            ->selectRaw('Status, COUNT(*) as count, SUM(COALESCE(QtyByOrder, 0)) as qty')
            ->when($ids !== [], fn ($query) => $query->whereIn('Status', $ids))
            ->groupBy('Status')
            ->get();

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[(string) $row->Status] = $row;
        }

        $segments = [];
        $lastIndex = count($statuses) - 1;
        foreach ($statuses as $index => $status) {
            $id = (int) ($status['id'] ?? 0);
            $row = $byStatus[(string) $id] ?? null;
            $key = $index === $lastIndex ? 'produced' : 'stage_'.$id;
            $segments[] = [
                'key' => $key,
                'label' => (string) ($status['name'] ?? 'Status '.$id),
                'count' => (int) ($row->count ?? 0),
                'qty' => (int) ($row->qty ?? 0),
            ];
        }

        $total = array_sum(array_column($segments, 'count'));
        $totalQty = array_sum(array_column($segments, 'qty'));

        return [
            'total' => $total,
            'totalQty' => $totalQty,
            'segments' => $segments,
        ];
    }

    private function qcPassFail(): array
    {
        $reasonMap = $this->qcReasonMap();
        $passIds = [];
        $failIds = [];
        foreach ($reasonMap as $id => $name) {
            if ($this->isQcReasonAccepted($name)) {
                $passIds[] = $id;
            } else {
                $failIds[] = $id;
            }
        }

        $passQty = $passIds === []
            ? 0
            : (int) QcRegister::query()->whereIn('qc_reason_id', $passIds)->sum('qty');
        $failQty = $failIds === []
            ? 0
            : (int) QcRegister::query()->whereIn('qc_reason_id', $failIds)->sum('qty');
        $rated = $passQty + $failQty;

        $statuses = $this->qcStatusList();
        $counts = QualityControl::query()
            ->selectRaw('QC_status, COUNT(*) as count, SUM(COALESCE(Qty, 0)) as qty')
            ->groupBy('QC_status')
            ->get();

        $byStatus = [];
        foreach ($counts as $row) {
            $byStatus[(string) $row->QC_status] = $row;
        }

        $byStatusRows = [];
        foreach ($statuses as $index => $status) {
            $id = (int) ($status['id'] ?? 0);
            $row = $byStatus[(string) $id] ?? null;
            $byStatusRows[] = [
                'key' => $this->qcStatusKey($index, (string) ($status['name'] ?? '')),
                'label' => (string) ($status['name'] ?? 'Status '.$id),
                'count' => (int) ($row->count ?? 0),
                'qty' => (int) ($row->qty ?? 0),
            ];
        }

        return [
            'passQty' => $passQty,
            'failQty' => $failQty,
            'passRate' => $rated > 0 ? (int) round($passQty / $rated * 100) : 0,
            'byStatus' => $byStatusRows,
        ];
    }

    private function outputVolume(): array
    {
        $now = now();
        $weekStart = $now->copy()->startOfWeek();
        $chartStart = $weekStart->copy()->subWeeks(7);

        $rows = QualityControl::query()
            ->whereNotNull('QC_completion_date')
            ->whereDate('QC_completion_date', '>=', $chartStart->toDateString())
            ->selectRaw('QC_completion_date as day, SUM(COALESCE(Qty, 0)) as qty')
            ->groupBy('QC_completion_date')
            ->get();

        $qtyByDay = [];
        foreach ($rows as $row) {
            $day = $row->day instanceof \DateTimeInterface
                ? $row->day->format('Y-m-d')
                : substr((string) $row->day, 0, 10);
            $qtyByDay[$day] = (int) $row->qty;
        }

        $bars = [];
        for ($i = 0; $i < 8; $i++) {
            $start = $chartStart->copy()->addWeeks($i);
            $qty = 0;
            for ($d = 0; $d < 7; $d++) {
                $key = $start->copy()->addDays($d)->toDateString();
                $qty += $qtyByDay[$key] ?? 0;
            }
            $bars[] = [
                'label' => $start->format('j M'),
                'qty' => $qty,
            ];
        }

        $sumSince = function (Carbon $from) {
            return (int) QualityControl::query()
                ->whereNotNull('QC_completion_date')
                ->whereDate('QC_completion_date', '>=', $from->toDateString())
                ->sum('Qty');
        };

        return [
            'week' => $sumSince($weekStart),
            'month' => $sumSince($now->copy()->startOfMonth()),
            'year' => $sumSince($now->copy()->startOfYear()),
            'bars' => $bars,
        ];
    }

    /**
     * Order counts for the last 12 months, keyed by the date the chart can plot.
     *
     * @return array{created: list<array{label: string, title: string, count: int, qty: int}>, delivery: list<array{label: string, title: string, count: int, qty: int}>}
     */
    private function orderDateBars(): array
    {
        return [
            'created' => $this->monthlyOrderBars('created_at'),
            'delivery' => $this->monthlyOrderBars('DeliveryDate'),
        ];
    }

    /**
     * @return list<array{label: string, title: string, count: int, qty: int}>
     */
    private function monthlyOrderBars(string $column): array
    {
        if (! in_array($column, ['created_at', 'DeliveryDate'], true)) {
            return [];
        }

        $end = now()->startOfMonth();
        $start = $end->copy()->subMonths(11);

        $rows = Order::query()
            ->whereNotNull($column)
            ->whereDate($column, '>=', $start->toDateString())
            ->whereDate($column, '<', $end->copy()->addMonth()->toDateString())
            ->selectRaw("DATE_FORMAT(`{$column}`, '%Y-%m') as ym, COUNT(*) as count, SUM(COALESCE(QtyTotal, 0)) as qty")
            ->groupByRaw("DATE_FORMAT(`{$column}`, '%Y-%m')")
            ->get();

        $byMonth = [];
        foreach ($rows as $row) {
            $byMonth[(string) $row->ym] = $row;
        }

        $bars = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $row = $byMonth[$month->format('Y-m')] ?? null;
            $bars[] = [
                'label' => $month->format('M'),
                'title' => $month->format('F Y'),
                'count' => (int) ($row->count ?? 0),
                'qty' => (int) ($row->qty ?? 0),
            ];
        }

        return $bars;
    }

    private function newOrders(int $newlyCreatedStatusId): array
    {
        $now = now();
        $bucket = function (Carbon $from) {
            $row = Order::query()
                ->where('created_at', '>=', $from)
                ->selectRaw('COUNT(*) as count, SUM(COALESCE(QtyTotal, 0)) as qty')
                ->first();

            return [
                'count' => (int) ($row->count ?? 0),
                'qty' => (int) ($row->qty ?? 0),
            ];
        };

        $open = Order::query()
            ->where('Status', $newlyCreatedStatusId)
            ->selectRaw('COUNT(*) as count, SUM(COALESCE(QtyTotal, 0)) as qty')
            ->first();

        return [
            'open' => [
                'count' => (int) ($open->count ?? 0),
                'qty' => (int) ($open->qty ?? 0),
            ],
            'week' => $bucket($now->copy()->startOfWeek()),
            'month' => $bucket($now->copy()->startOfMonth()),
        ];
    }

    private function topProducts(): array
    {
        $nameExpr = "COALESCE(NULLIF(TRIM(li.design_name), ''), NULLIF(TRIM(po.ClientModel), ''), 'Unnamed')";

        $lines = DB::table('invoice_line_items as li')
            ->leftJoin('ordersPO as po', 'po.id', '=', 'li.orders_po_id')
            ->selectRaw($nameExpr.' as name')
            ->selectRaw('COALESCE(li.quantity, 0) as qty')
            ->selectRaw('COALESCE(li.quantity, 0) * COALESCE(li.rate, 0) * (1 - COALESCE(li.discount_percent, 0) / 100) as amount');

        $rows = DB::query()
            ->fromSub($lines, 'lines')
            ->selectRaw('name, SUM(qty) as qty, SUM(amount) as amount')
            ->groupBy('name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            $rows = DB::table('ordersPO')
                ->whereNotNull('ClientModel')
                ->where('ClientModel', '!=', '')
                ->selectRaw("ClientModel as name, SUM(COALESCE(QtyByOrder, 0)) as qty, 0 as amount")
                ->groupBy('ClientModel')
                ->orderByDesc('qty')
                ->limit(5)
                ->get();
        }

        return $rows->map(fn ($row) => [
            'name' => (string) $row->name,
            'qty' => (int) round((float) $row->qty),
            'amount' => round((float) $row->amount, 2),
        ])->all();
    }

    private function materialsUsed(): array
    {
        $usage = DB::table('ordersPO as po')
            ->join('orders as o', 'o.OrderId', '=', 'po.OrderId')
            ->whereNotNull('po.ClientModel')
            ->where('po.ClientModel', '!=', '')
            ->selectRaw('o.ClientId as ClientId, po.ClientModel as ModelNo, SUM(COALESCE(po.QtyByOrder, 0)) as qty')
            ->groupBy('o.ClientId', 'po.ClientModel')
            ->get();

        $lookup = [];
        $models = DB::table('clientmodel')
            ->orderBy('clientmodelId')
            ->get(['ClientId', 'ModelNo', 'LeatherId', 'LeatherColourId']);
        foreach ($models as $model) {
            $key = $model->ClientId.'|'.$model->ModelNo;
            if (! isset($lookup[$key])) {
                $lookup[$key] = $model;
            }
        }

        $leatherQty = [];
        $colourQty = [];
        foreach ($usage as $row) {
            $model = $lookup[$row->ClientId.'|'.$row->ModelNo] ?? null;
            if (! $model) {
                continue;
            }
            $qty = (int) $row->qty;
            if ($model->LeatherId) {
                $leatherQty[$model->LeatherId] = ($leatherQty[$model->LeatherId] ?? 0) + $qty;
            }
            if ($model->LeatherColourId) {
                $colourQty[$model->LeatherColourId] = ($colourQty[$model->LeatherColourId] ?? 0) + $qty;
            }
        }

        $top = function (array $qtyById, string $table, string $idColumn, string $nameColumn): array {
            if ($qtyById === []) {
                return [];
            }
            arsort($qtyById);
            $qtyById = array_slice($qtyById, 0, 6, true);
            $names = DB::table($table)
                ->whereIn($idColumn, array_keys($qtyById))
                ->pluck($nameColumn, $idColumn);

            $rows = [];
            foreach ($qtyById as $id => $qty) {
                $name = $names[$id] ?? $names[(string) $id] ?? null;
                if (! $name) {
                    continue;
                }
                $rows[] = ['name' => (string) $name, 'qty' => (int) $qty];
            }

            return $rows;
        };

        return [
            'leather' => $top($leatherQty, 'leather', 'LeatherId', 'Name'),
            'colour' => $top($colourQty, 'colour', 'ColourId', 'ColourName'),
        ];
    }

    private function invoicePeriods(): array
    {
        $now = now();
        $from = $now->copy()->subYears(4)->startOfYear();

        $rows = DB::table('invoices as i')
            ->leftJoin('invoice_line_groups as g', 'g.invoice_id', '=', 'i.id')
            ->leftJoin('invoice_line_items as li', 'li.invoice_line_group_id', '=', 'g.id')
            ->whereDate('i.invoice_date', '>=', $from->toDateString())
            ->groupBy('i.id', 'i.invoice_date')
            ->selectRaw('i.invoice_date as invoice_date')
            ->selectRaw('SUM(COALESCE(li.quantity, 0) * COALESCE(li.rate, 0) * (1 - COALESCE(li.discount_percent, 0) / 100)) as amount')
            ->get();

        $dated = [];
        foreach ($rows as $row) {
            $day = $row->invoice_date instanceof \DateTimeInterface
                ? $row->invoice_date->format('Y-m-d')
                : substr((string) $row->invoice_date, 0, 10);
            $dated[] = [
                'date' => Carbon::parse($day),
                'amount' => (float) $row->amount,
            ];
        }

        $bucket = function (Carbon $start, int $steps, string $unit, string $labelFormat) use ($dated) {
            $bars = [];
            for ($i = 0; $i < $steps; $i++) {
                $cursor = $start->copy();
                if ($unit === 'week') {
                    $cursor->addWeeks($i);
                    $end = $cursor->copy()->endOfWeek();
                } elseif ($unit === 'month') {
                    $cursor->addMonths($i);
                    $end = $cursor->copy()->endOfMonth();
                } else {
                    $cursor->addYears($i);
                    $end = $cursor->copy()->endOfYear();
                }

                $count = 0;
                $amount = 0.0;
                foreach ($dated as $invoice) {
                    if ($invoice['date']->between($cursor, $end)) {
                        $count++;
                        $amount += $invoice['amount'];
                    }
                }

                $bars[] = [
                    'label' => $cursor->format($labelFormat),
                    'count' => $count,
                    'amount' => round($amount, 2),
                ];
            }

            return $bars;
        };

        return [
            'week' => $bucket($now->copy()->startOfWeek()->subWeeks(7), 8, 'week', 'j M'),
            'month' => $bucket($now->copy()->startOfMonth()->subMonths(11), 12, 'month', 'M Y'),
            'year' => $bucket($now->copy()->startOfYear()->subYears(4), 5, 'year', 'Y'),
        ];
    }

    private function packedOrders(): array
    {
        $statuses = $this->decodeSiteSettingJson('packaging_status');
        if (empty($statuses)) {
            $statuses = [
                ['id' => 1, 'name' => 'Created'],
                ['id' => 2, 'name' => 'In Process'],
                ['id' => 3, 'name' => 'Completed'],
            ];
        }

        $counts = Package::query()
            ->selectRaw('status, COUNT(*) as count, SUM(COALESCE(total_boxes, 0)) as boxes')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach ($counts as $row) {
            $byStatus[(string) $row->status] = $row;
        }

        $segments = [];
        foreach ($statuses as $status) {
            $id = (int) ($status['id'] ?? 0);
            $row = $byStatus[(string) $id] ?? null;
            $segments[] = [
                'key' => 'status_'.$id,
                'label' => (string) ($status['name'] ?? 'Status '.$id),
                'count' => (int) ($row->count ?? 0),
                'boxes' => (int) ($row->boxes ?? 0),
            ];
        }

        $totals = DB::table('package_box_order_po as p')
            ->join('ordersPO as po', 'po.id', '=', 'p.order_po_id')
            ->selectRaw('COUNT(DISTINCT po.OrderId) as orders, SUM(COALESCE(p.qty, 0)) as qty')
            ->first();

        return [
            'orders' => (int) ($totals->orders ?? 0),
            'qty' => (int) ($totals->qty ?? 0),
            'boxes' => (int) Package::query()->sum('total_boxes'),
            'segments' => $segments,
        ];
    }

    private function qcStatusBreakdown(): array
    {
        $statuses = $this->qcStatusList();
        $counts = QualityControl::query()
            ->selectRaw('QC_status, COUNT(*) as count, SUM(COALESCE(Qty, 0)) as qty')
            ->groupBy('QC_status')
            ->get();

        $byStatus = [];
        foreach ($counts as $row) {
            $byStatus[(string) $row->QC_status] = $row;
        }

        $rows = [];
        foreach ($statuses as $index => $status) {
            $id = (int) ($status['id'] ?? 0);
            $row = $byStatus[(string) $id] ?? null;
            $rows[] = [
                'key' => $this->qcStatusKey($index, (string) ($status['name'] ?? '')),
                'label' => (string) ($status['name'] ?? 'Status '.$id),
                'count' => (int) ($row->count ?? 0),
                'qty' => (int) ($row->qty ?? 0),
            ];
        }

        return $rows;
    }

    private function dispatchStatus(): array
    {
        $counts = Invoice::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $labels = [
            'draft' => 'Draft',
            'complete' => 'Complete',
            'sent_to_client' => 'Sent to Client',
        ];

        $invoices = [];
        foreach ($labels as $key => $label) {
            $invoices[] = [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        $recent = Invoice::query()
            ->with('client:id,client_name')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (Invoice $invoice) use ($labels) {
                return [
                    'number' => $invoice->formattedInvoiceNumber(),
                    'client' => $invoice->client->client_name ?? '—',
                    'date' => optional($invoice->invoice_date)->format('Y-m-d'),
                    'status' => $labels[$invoice->status] ?? $invoice->status,
                ];
            })
            ->all();

        return [
            'invoices' => $invoices,
            'shipped' => (int) Package::query()->whereNotNull('shipping_date')->count(),
            'recent' => $recent,
        ];
    }

    private function qcStatusList(): array
    {
        $statuses = $this->decodeSiteSettingJson('quality_control_status');
        if (empty($statuses)) {
            return [
                ['id' => 1, 'name' => 'Pending'],
                ['id' => 2, 'name' => 'In Progress'],
                ['id' => 3, 'name' => 'Completed'],
            ];
        }

        return $statuses;
    }

    private function qcStatusKey(int $index, string $name): string
    {
        $normalized = strtolower($name);
        if (str_contains($normalized, 'progress')) {
            return 'in_progress';
        }
        if (str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
            return 'completed';
        }
        if ($index === 0 || str_contains($normalized, 'pending')) {
            return 'pending';
        }

        return 'status_'.$index;
    }

    /**
     * @return array<int, string>
     */
    private function qcReasonMap(): array
    {
        $map = [];
        foreach ($this->decodeSiteSettingJson('qc_reasons') as $reason) {
            $id = (int) ($reason['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = (string) ($reason['name'] ?? '');
            }
        }

        return $map;
    }

    private function isQcReasonAccepted(string $reasonName): bool
    {
        $normalized = mb_strtolower(trim($reasonName));
        if ($normalized === '') {
            return false;
        }

        $exact = [
            'accepted', 'qty accepted', 'qc accepted', 'passed', 'pass', 'ok',
            'approved', 'good', 'no issue', 'no defect', 'none', 'nil', 'clear',
        ];

        if (in_array($normalized, $exact, true)) {
            return true;
        }

        return (bool) preg_match('/^(qc\s+)?accepted\b/u', $normalized);
    }

    private function resolveOrdersStatusIds(): array
    {
        $statuses = $this->decodeSiteSettingJson('orders_status');

        return [
            'newlyCreated' => (int) ($statuses[0]['id'] ?? 1),
            'inProduction' => (int) ($statuses[1]['id'] ?? 2),
            'qcDone' => (int) ($statuses[2]['id'] ?? 3),
            'labels' => [
                'newlyCreated' => $statuses[0]['name'] ?? 'Newly Created',
                'inProduction' => $statuses[1]['name'] ?? 'In Production',
                'qcDone' => $statuses[2]['name'] ?? 'QC Done',
            ],
        ];
    }

    private function resolveOrderPoStatusIds(): array
    {
        $statuses = $this->decodeSiteSettingJson('orderpos_status');

        if (empty($statuses)) {
            $statuses = [
                ['id' => 1, 'name' => 'Newly Added'],
                ['id' => 2, 'name' => 'In Production'],
                ['id' => 3, 'name' => 'Produced'],
            ];
        }

        return [
            'inProduction' => (int) ($statuses[1]['id'] ?? 2),
            'qcStarted' => (int) ($statuses[2]['id'] ?? 3),
            'produced' => (int) ($statuses[3]['id'] ?? 4),
        ];
    }

    private function decodeSiteSettingJson(string $key): array
    {
        $setting = SiteSetting::where('key', $key)->first();
        if (! $setting || $setting->type !== 'json') {
            return [];
        }

        $decoded = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return [];
        }

        return array_values($decoded);
    }

    private function formatInt(int $value): string
    {
        return number_format($value);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 0);
    }
}
