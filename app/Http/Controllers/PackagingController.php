<?php

namespace App\Http\Controllers;

use App\Exports\PackagePackingListExport;
use App\Models\BuckleWidth;
use App\Models\Client;
use App\Models\ClientModel;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPO;
use App\Models\Package;
use App\Models\PackageBox;
use App\Models\PackagePallet;
use App\Models\SiteSetting;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PackagingController extends Controller
{
    /** packaging_status site_settings IDs */
    private const PACKAGING_STATUS_CREATED = 1;

    private const PACKAGING_STATUS_IN_PROCESS = 2;

    private const PACKAGING_STATUS_COMPLETED = 3;

    /**
     * Get Produced status ID from orderpos_status (QC completed = Produced)
     */
    private function getProducedStatusId(): int
    {
        $setting = SiteSetting::where('key', 'orderpos_status')->first();
        $orderPosStatuses = [];
        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $orderPosStatuses = array_values($decoded);
            }
        }

        return isset($orderPosStatuses[3]['id']) ? (int) $orderPosStatuses[3]['id'] : 4;
    }

    /**
     * Get line item desc name from ID
     */
    private function getLineItemDescName(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $setting = SiteSetting::where('key', 'line_item_desc')->first();
        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (isset($item['id']) && (int) $item['id'] === $id) {
                        return $item['name'] ?? null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Sum packed qty for an Order PO across all boxes, optionally excluding one box (e.g. while editing that box).
     */
    private function sumPackedQtyForOrderPoExcludingBox(int $orderPoId, ?int $excludePackageBoxId): int
    {
        $q = DB::table('package_box_order_po')->where('order_po_id', $orderPoId);
        if ($excludePackageBoxId !== null) {
            $q->where('package_box_id', '!=', $excludePackageBoxId);
        }

        return (int) $q->sum('qty');
    }

    /**
     * Sync total_boxes and pallets string from actual child records.
     */
    private function syncDerivedPackageCounts(Package $package): void
    {
        $package->refresh();
        $boxCount = $package->boxes()->count();
        $palletCount = $package->packagePallets()->count();

        $package->update([
            'total_boxes' => $boxCount,
            'pallets' => ($package->use_pallets && $palletCount > 0) ? (string) $palletCount : null,
        ]);
    }

    /**
     * Map a PackageBox + its order POs to the index payload shape.
     */
    private function mapBoxForIndex(PackageBox $box): array
    {
        $poRefs = [];
        foreach ($box->orderPOs as $po) {
            $order = $po->order;
            $poRefs[] = [
                'id' => $po->id,
                'refNumber' => $po->RefNumber ?? '-',
                'psNumber' => $po->PsNumber ?? '-',
                'orderNumber' => $order?->OrderNumber ?? '-',
                'gold' => $po->Gold ?? '-',
                'silver' => $po->Silver ?? '-',
                'qty' => $this->boxLineQuantity($po),
                'poTotalQty' => is_numeric($po->QtyByOrder) ? (int) $po->QtyByOrder : 0,
                'weight' => $po->pivot->weight ?? null,
                'lineItemDesc' => $this->getLineItemDescName($po->pivot->line_item_desc ?? null),
                'lineItemDescId' => $po->pivot->line_item_desc ?? null,
                'isPairs' => (bool) ($po->pivot->is_pairs ?? false),
            ];
        }

        return [
            'id' => $box->id,
            'boxNumber' => $this->displaySibBoxNumber($box->box_number),
            'packagePalletId' => $box->package_pallet_id,
            'orderPOs' => $poRefs,
        ];
    }

    /**
     * SIB mark with slashes: SIB/01/02 (pallet) or SIB/01 (flat).
     */
    private function sibBoxLabel(int $boxNo, ?int $total = null): string
    {
        if ($total !== null) {
            return sprintf('SIB/%02d/%02d', $boxNo, $total);
        }

        return sprintf('SIB/%02d', $boxNo);
    }

    /**
     * Show stored box numbers with / even if older rows used dashes.
     */
    private function displaySibBoxNumber(?string $boxNumber): string
    {
        return str_replace('-', '/', (string) $boxNumber);
    }

    /**
     * Planned box capacity for a pallet (fallback to current count).
     */
    private function palletPlannedBoxCount(PackagePallet $pallet): int
    {
        $planned = (int) ($pallet->planned_box_count ?? 0);
        $actual = $pallet->relationLoaded('boxes')
            ? $pallet->boxes->count()
            : $pallet->boxes()->count();

        return max($planned, $actual, 1);
    }

    /**
     * Next box label. With pallet: SIB/{box}/{plannedTotal} e.g. SIB/01/02. Flat: SIB/01.
     */
    private function nextBoxNumber(Package $package, ?PackagePallet $pallet = null): string
    {
        if ($pallet) {
            $existingCount = $pallet->boxes()->count();
            $nextNumber = $existingCount + 1;
            $total = max((int) ($pallet->planned_box_count ?? 0), $nextNumber, 1);

            return $this->sibBoxLabel($nextNumber, $total);
        }

        $existingCount = $package->boxes()->whereNull('package_pallet_id')->count();
        $nextNumber = $existingCount + 1;

        return $this->sibBoxLabel($nextNumber);
    }

    /**
     * Renumber pallet_number sequentially 1..N after deletes.
     */
    private function renumberPackagePallets(Package $package): void
    {
        $pallets = $package->packagePallets()->orderBy('pallet_number')->orderBy('id')->get();
        $n = 1;
        foreach ($pallets as $pallet) {
            if ((int) $pallet->pallet_number !== $n) {
                $pallet->update(['pallet_number' => $n]);
            }
            $n++;
        }
    }

    /**
     * Renumber box_number within a pallet (SIB/{box}/{plannedTotal}) or flat package boxes (SIB/NN).
     */
    private function renumberBoxes(?Package $package = null, ?PackagePallet $pallet = null): void
    {
        if ($pallet) {
            $boxes = $pallet->boxes()->orderBy('id')->get();
            $total = max((int) ($pallet->planned_box_count ?? 0), $boxes->count(), 1);
            $n = 1;
            foreach ($boxes as $box) {
                $label = $this->sibBoxLabel($n, $total);
                if ($box->box_number !== $label) {
                    $box->update(['box_number' => $label]);
                }
                $n++;
            }

            return;
        }

        if (! $package) {
            return;
        }

        $boxes = $package->boxes()->whereNull('package_pallet_id')->orderBy('id')->get();
        $n = 1;
        foreach ($boxes as $box) {
            $label = $this->sibBoxLabel($n);
            if ($box->box_number !== $label) {
                $box->update(['box_number' => $label]);
            }
            $n++;
        }
    }

    /**
     * After pallet renumber, refresh box labels that include the pallet index.
     */
    private function renumberBoxesForAllPallets(Package $package): void
    {
        foreach ($package->packagePallets()->orderBy('pallet_number')->get() as $pallet) {
            $this->renumberBoxes(null, $pallet);
        }
    }

    /**
     * Quantity shown on PDFs for a PO line in a box (pivot qty, else legacy full PO qty).
     */
    private function boxLineQuantity(OrderPO $po): int
    {
        $pivotQty = $po->pivot->qty ?? null;
        if ($pivotQty !== null && $pivotQty !== '') {
            return max(0, (int) $pivotQty);
        }

        return is_numeric($po->QtyByOrder) ? (int) $po->QtyByOrder : 0;
    }

    /**
     * Pivot / DB flags may be int|string|bool; avoid (bool) "0" === true.
     */
    private function pivotIsPairs(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * After boxes/pallets change: Created when no boxes, In Process when at least one box,
     * downgrade Completed if requirements are no longer met (≥1 box + shipping date).
     */
    private function syncPackageStatusAfterBoxesChange(Package $package): void
    {
        $this->syncDerivedPackageCounts($package);
        $package->refresh();
        $boxCount = $package->boxes()->count();
        $status = (int) ($package->status ?? self::PACKAGING_STATUS_CREATED);

        if ($boxCount === 0) {
            if ($status !== self::PACKAGING_STATUS_CREATED) {
                $package->update(['status' => self::PACKAGING_STATUS_CREATED]);
            }

            return;
        }

        if ($status === self::PACKAGING_STATUS_CREATED) {
            $package->update(['status' => self::PACKAGING_STATUS_IN_PROCESS]);

            return;
        }

        if ($status === self::PACKAGING_STATUS_COMPLETED) {
            $shippingDate = $package->shipping_date;
            if ($boxCount < 1 || empty($shippingDate)) {
                $package->update(['status' => self::PACKAGING_STATUS_IN_PROCESS]);
            }
        }
    }

    /**
     * Get packaging status map (id => name) from site_settings
     */
    private function getPackagingStatusMap(): array
    {
        $setting = SiteSetting::where('key', 'packaging_status')->first();
        $statuses = [];
        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }
        $map = [];
        foreach ($statuses as $s) {
            $id = $s['id'] ?? null;
            if ($id !== null) {
                $map[(int) $id] = $s['name'] ?? '-';
            }
        }

        return $map;
    }

    /**
     * Display the Packaging index page with packages table
     */
    public function index(Request $request): Response
    {
        $packagesPaginated = Package::with([
            'client',
            'boxes.orderPOs.order',
            'packagePallets.boxes.orderPOs.order',
            'invoices' => static fn ($q) => $q->orderByDesc('id'),
        ])
            ->orderBy('id', 'desc')
            ->paginate(15);

        $statusMap = $this->getPackagingStatusMap();

        $packages = [];
        foreach ($packagesPaginated->items() as $package) {
            $rawStatus = $package->status;
            $statusId = $rawStatus !== null && $rawStatus !== '' && is_numeric($rawStatus) ? (int) $rawStatus : null;
            // Legacy: 'Pending' or '1' -> Created (id 1)
            if ($rawStatus === 'Pending' || $rawStatus === '1') {
                $statusId = 1;
            }
            $statusText = $statusId !== null && isset($statusMap[$statusId]) ? $statusMap[$statusId] : ($rawStatus ?? '-');

            $usePallets = (bool) $package->use_pallets;
            $boxes = [];
            $pallets = [];

            if ($usePallets) {
                foreach ($package->packagePallets as $pallet) {
                    $palletBoxes = [];
                    foreach ($pallet->boxes as $box) {
                        $palletBoxes[] = $this->mapBoxForIndex($box);
                    }
                    $pallets[] = [
                        'id' => $pallet->id,
                        'palletNumber' => (int) $pallet->pallet_number,
                        'name' => trim((string) ($pallet->name ?? '')) !== ''
                            ? trim((string) $pallet->name)
                            : sprintf('Pallet %02d', (int) $pallet->pallet_number),
                        'plannedBoxCount' => (int) ($pallet->planned_box_count ?? max(count($palletBoxes), 1)),
                        'boxes' => $palletBoxes,
                    ];
                }
            } else {
                foreach ($package->boxes as $box) {
                    $boxes[] = $this->mapBoxForIndex($box);
                }
            }

            $boxCount = $usePallets
                ? collect($pallets)->sum(fn ($p) => count($p['boxes']))
                : count($boxes);
            $palletCount = count($pallets);

            $invoiceDisplay = $this->resolvePackageInvoiceDisplay($package);

            $packages[] = [
                'id' => $package->id,
                'clientId' => $package->client_id,
                'clientName' => $package->client?->client_name ?? '-',
                'usePallets' => $usePallets,
                'pallets' => $usePallets ? $palletCount : null,
                'palletCount' => $palletCount,
                'packagePallets' => $pallets,
                'totalBoxes' => $boxCount,
                'status' => $statusText,
                'statusId' => $statusId !== null ? (int) $statusId : self::PACKAGING_STATUS_CREATED,
                'termsOfDelivery' => $package->terms_of_delivery ?: Invoice::DEFAULT_TERMS_OF_DELIVERY,
                'termsOfPayment' => $package->terms_of_payment ?: Invoice::DEFAULT_TERMS_OF_PAYMENT,
                'preCarriageBy' => $package->pre_carriage_by ?? '',
                'placeOfReceipt' => $package->place_of_receipt ?? '',
                'vesselFlightNo' => $package->vessel_flight_no ?? '',
                'portOfLoading' => $package->port_of_loading ?? '',
                'shippingDate' => $package->shipping_date ? $package->shipping_date->format('Y-m-d') : null,
                'invoiceNumber' => $invoiceDisplay['invoiceNumber'],
                'invoiceDate' => $invoiceDisplay['invoiceDateYmd'],
                'grossWeightKg' => $package->gross_weight_kg !== null ? (float) $package->gross_weight_kg : null,
                'netWeightKg' => $package->net_weight_kg !== null ? (float) $package->net_weight_kg : null,
                'dimWeightKg' => $package->dim_weight_kg !== null ? (float) $package->dim_weight_kg : null,
                'specifyDimension' => $package->specify_dimension,
                'boxes' => $boxes,
            ];
        }

        // In-production orders (Status = 2), one dropdown row per order: "{client} | {order number} | {qty}"
        $clientsWithInProductionOrders = Order::query()
            ->where('Status', 2)
            ->join('clients', 'clients.id', '=', 'orders.ClientId')
            ->orderBy('clients.client_name')
            ->orderBy('orders.OrderNumber')
            ->get([
                'orders.OrderId as orderId',
                'orders.ClientId as id',
                'orders.OrderNumber as orderNumber',
                'orders.QtyTotal as qty',
                'clients.client_name as clientName',
            ])
            ->map(function ($row) {
                $clientName = $row->clientName ?: '-';
                $orderNumber = $row->orderNumber ?: '-';
                $qty = $row->qty ?? 0;

                return [
                    'id' => (int) $row->id,
                    'orderId' => (int) $row->orderId,
                    'name' => "{$clientName} | Order: {$orderNumber} | Qty: {$qty}",
                ];
            })
            ->values();

        $packagingStatusOptions = [];
        foreach ($statusMap as $id => $name) {
            $packagingStatusOptions[] = ['id' => (int) $id, 'name' => $name];
        }
        usort($packagingStatusOptions, fn ($a, $b) => ($a['id'] ?? 0) <=> ($b['id'] ?? 0));

        // Get Line Item Desc options
        $lineItemDescOptions = [];
        $lineItemDescSetting = SiteSetting::where('key', 'line_item_desc')->first();
        if ($lineItemDescSetting && $lineItemDescSetting->type === 'json') {
            $decoded = json_decode($lineItemDescSetting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $lineItemDescOptions = $decoded;
            }
        }
        // If no data exists, initialize with default value
        if (empty($lineItemDescOptions)) {
            $lineItemDescOptions = [
                ['id' => 1, 'name' => 'LEATHER WATCH STRAPS WITH BUCKLES'],
            ];
            SiteSetting::updateOrCreate(
                ['key' => 'line_item_desc'],
                ['value' => json_encode($lineItemDescOptions), 'type' => 'json']
            );
            Cache::forget('app_settings');
        }

        return Inertia::render('Admin/OrderManagement/Packaging/Index', [
            'packages' => [
                'data' => $packages,
                'links' => $packagesPaginated->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $packagesPaginated->currentPage(),
                    'last_page' => $packagesPaginated->lastPage(),
                    'per_page' => $packagesPaginated->perPage(),
                    'total' => $packagesPaginated->total(),
                    'from' => $packagesPaginated->firstItem(),
                    'to' => $packagesPaginated->lastItem(),
                ],
            ],
            'clientsWithInProductionOrders' => $clientsWithInProductionOrders,
            'packagingStatusOptions' => $packagingStatusOptions,
            'lineItemDescOptions' => $lineItemDescOptions,
            'termsOfDeliveryOptions' => Invoice::termsOfDeliveryOptions(),
            'defaultTermsOfDelivery' => Invoice::DEFAULT_TERMS_OF_DELIVERY,
            'defaultTermsOfPayment' => Invoice::DEFAULT_TERMS_OF_PAYMENT,
        ]);
    }

    /**
     * Store a new package
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'net_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'dim_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'specify_dimension' => ['nullable', 'string', 'max:2000'],
            'terms_of_delivery' => ['nullable', 'string', Rule::in(Invoice::termsOfDeliveryOptions())],
            'terms_of_payment' => ['nullable', 'string', 'max:64'],
            'pre_carriage_by' => ['nullable', 'string', 'max:128'],
            'place_of_receipt' => ['nullable', 'string', 'max:128'],
            'vessel_flight_no' => ['nullable', 'string', 'max:128'],
            'port_of_loading' => ['nullable', 'string', 'max:128'],
        ]);

        Package::create([
            'client_id' => $validated['client_id'],
            'use_pallets' => false,
            'pallets' => null,
            'total_boxes' => 0,
            'status' => 1, // Created (id 1 from packaging_status)
            'terms_of_delivery' => $validated['terms_of_delivery'] ?? Invoice::DEFAULT_TERMS_OF_DELIVERY,
            'terms_of_payment' => $validated['terms_of_payment'] ?? Invoice::DEFAULT_TERMS_OF_PAYMENT,
            'pre_carriage_by' => $this->nullableTrimmedString($validated['pre_carriage_by'] ?? null),
            'place_of_receipt' => $this->nullableTrimmedString($validated['place_of_receipt'] ?? null),
            'vessel_flight_no' => $this->nullableTrimmedString($validated['vessel_flight_no'] ?? null),
            'port_of_loading' => $this->nullableTrimmedString($validated['port_of_loading'] ?? null),
            'gross_weight_kg' => $this->nullableFloat($validated['gross_weight_kg'] ?? null),
            'net_weight_kg' => $this->nullableFloat($validated['net_weight_kg'] ?? null),
            'dim_weight_kg' => $this->nullableFloat($validated['dim_weight_kg'] ?? null),
            'specify_dimension' => $validated['specify_dimension'] ?? null,
        ]);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Package created successfully.');
    }

    /**
     * Get Order POs with QC completed (status Produced) for a package's client.
     * Only POs with remaining unpackaged quantity. Pass exclude_package_box_id when editing a box so that box's lines are excluded from the packed sum.
     */
    public function getQcCompletedPos(Request $request, Package $package)
    {
        $package->load('client');
        $clientId = $package->client_id;
        if (! $clientId) {
            return response()->json(['data' => []]);
        }

        $excludeBoxId = $request->query('exclude_package_box_id');
        $excludeBoxId = $excludeBoxId !== null && $excludeBoxId !== '' ? (int) $excludeBoxId : null;

        $producedId = $this->getProducedStatusId();

        $pos = OrderPO::with('order')
            ->whereHas('order', fn ($q) => $q->where('ClientId', $clientId))
            ->where('Status', $producedId)
            ->orderBy('RefNumber')
            ->orderBy('PsNumber')
            ->get();

        $data = [];
        foreach ($pos as $po) {
            $order = $po->order;
            $poTotalQty = is_numeric($po->QtyByOrder) ? (int) $po->QtyByOrder : 0;
            if ($poTotalQty < 1) {
                continue;
            }
            $packedElsewhere = $this->sumPackedQtyForOrderPoExcludingBox((int) $po->id, $excludeBoxId);
            if ($packedElsewhere >= $poTotalQty) {
                continue;
            }
            $remainingQty = $poTotalQty - $packedElsewhere;

            $data[] = [
                'id' => $po->id,
                'refNumber' => $po->RefNumber ?? '-',
                'psNumber' => $po->PsNumber ?? '-',
                'orderNumber' => $order?->OrderNumber ?? '-',
                'gold' => $po->Gold ?? '-',
                'silver' => $po->Silver ?? '-',
                'poTotalQty' => $poTotalQty,
                'remainingQty' => $remainingQty,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Store a new box for a package (supports multiple POs with weight)
     */
    public function storeBox(Request $request, Package $package)
    {
        $validated = $request->validate([
            'order_po_weights' => ['required', 'array'],
            'order_po_weights.*.order_po_id' => ['required', 'integer', 'exists:ordersPO,id'],
            'order_po_weights.*.weight' => ['nullable', 'numeric', 'min:0'],
            'order_po_weights.*.line_item_desc' => ['required', 'integer'],
            'order_po_weights.*.qty' => ['required', 'integer', 'min:1'],
            'order_po_weights.*.is_pairs' => ['nullable', 'boolean'],
            'package_pallet_id' => ['nullable', 'integer', 'exists:package_pallets,id'],
        ]);

        // Validate that line_item_desc IDs exist in the line_item_desc options
        $lineItemDescSetting = SiteSetting::where('key', 'line_item_desc')->first();
        $validLineItemDescIds = [];
        if ($lineItemDescSetting && $lineItemDescSetting->type === 'json') {
            $decoded = json_decode($lineItemDescSetting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $validLineItemDescIds = array_map(function ($item) {
                    return (int) ($item['id'] ?? 0);
                }, $decoded);
            }
        }
        foreach ($validated['order_po_weights'] as $item) {
            if (! in_array((int) $item['line_item_desc'], $validLineItemDescIds, true)) {
                return redirect()->back()->withErrors([
                    'order_po_weights' => 'Invalid Line Item Desc ID selected.',
                ])->withInput();
            }
        }

        $orderPoIds = array_column($validated['order_po_weights'], 'order_po_id');
        $pos = OrderPO::with('order')->whereIn('id', $orderPoIds)->get();
        foreach ($pos as $po) {
            if ($po->order->ClientId != $package->client_id) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Selected PO does not belong to this package\'s client.');
            }
        }

        foreach ($validated['order_po_weights'] as $item) {
            $po = $pos->firstWhere('id', $item['order_po_id']);
            $poTotal = is_numeric($po->QtyByOrder) ? (int) $po->QtyByOrder : 0;
            $packedElsewhere = $this->sumPackedQtyForOrderPoExcludingBox((int) $po->id, null);
            $maxAllowed = $poTotal - $packedElsewhere;
            $qty = (int) $item['qty'];
            if ($qty < 1 || $qty > $maxAllowed) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Quantity for one or more POs exceeds the remaining unpackaged quantity.');
            }
        }

        $pallet = null;
        $palletId = $validated['package_pallet_id'] ?? null;
        if ($package->use_pallets) {
            if (! $palletId) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Select a pallet before adding boxes.');
            }
            $pallet = PackagePallet::where('package_id', $package->id)->where('id', $palletId)->first();
            if (! $pallet) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Invalid pallet for this package.');
            }
            $planned = (int) ($pallet->planned_box_count ?? 0);
            $existingBoxes = $pallet->boxes()->count();
            if ($planned > 0 && $existingBoxes >= $planned) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'This pallet already has all '.$planned.' box(es).');
            }
        } elseif ($palletId) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'This package is not in pallets mode.');
        }

        $boxNumber = $this->nextBoxNumber($package, $pallet);

        $box = PackageBox::create([
            'package_id' => $package->id,
            'package_pallet_id' => $pallet?->id,
            'box_number' => $boxNumber,
        ]);

        $syncData = [];
        foreach ($validated['order_po_weights'] as $item) {
            $w = $item['weight'] ?? null;
            $weight = ($w === null || $w === '') ? null : (float) $w;

            $syncData[$item['order_po_id']] = [
                'weight' => $weight,
                'line_item_desc' => (int) $item['line_item_desc'],
                'qty' => (int) $item['qty'],
                'is_pairs' => ! empty($item['is_pairs']),
            ];
        }
        $box->orderPOs()->attach($syncData);

        if ($pallet) {
            $this->renumberBoxes(null, $pallet);
            $box->refresh();
            $boxNumber = $box->box_number;
        }

        $this->syncPackageStatusAfterBoxesChange($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Box '.$boxNumber.' added successfully.');
    }

    /**
     * Update a package
     */
    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'use_pallets' => ['sometimes', 'boolean'],
            'status' => ['nullable', 'integer'],
            'shipping_date' => ['nullable', 'date'],
            'gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'net_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'dim_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'specify_dimension' => ['nullable', 'string', 'max:2000'],
            'terms_of_delivery' => ['nullable', 'string', Rule::in(Invoice::termsOfDeliveryOptions())],
            'terms_of_payment' => ['nullable', 'string', 'max:64'],
            'pre_carriage_by' => ['nullable', 'string', 'max:128'],
            'place_of_receipt' => ['nullable', 'string', 'max:128'],
            'vessel_flight_no' => ['nullable', 'string', 'max:128'],
            'port_of_loading' => ['nullable', 'string', 'max:128'],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'invoice_date' => ['nullable', 'date'],
        ]);

        $statusMap = $this->getPackagingStatusMap();
        $validIds = array_keys($statusMap);

        $newStatus = array_key_exists('status', $validated) && $validated['status'] !== null
            ? (int) $validated['status']
            : (int) ($package->status ?? self::PACKAGING_STATUS_CREATED);

        if (! in_array($newStatus, $validIds, true)) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Invalid packaging status.');
        }

        $boxCount = $package->boxes()->count();
        $palletCount = $package->packagePallets()->count();

        $shippingForRule = $validated['shipping_date'] ?? $package->shipping_date;
        $hasShipping = $shippingForRule !== null && $shippingForRule !== '';

        if ($newStatus === self::PACKAGING_STATUS_COMPLETED) {
            if ($boxCount < 1 || ! $hasShipping) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Completed requires at least one box and a shipping date.');
            }
        }

        if ($newStatus === self::PACKAGING_STATUS_CREATED && $boxCount > 0) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Created is only available when there are no boxes.');
        }

        if ($newStatus === self::PACKAGING_STATUS_IN_PROCESS && $boxCount === 0) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'In Process requires at least one box.');
        }

        $usePallets = array_key_exists('use_pallets', $validated)
            ? (bool) $validated['use_pallets']
            : (bool) $package->use_pallets;

        if ($usePallets !== (bool) $package->use_pallets) {
            if ($usePallets) {
                if ($boxCount > 0) {
                    return redirect()->route('admin.order-management.packaging.index')
                        ->with('error', 'Cannot enable pallets while boxes already exist. Remove boxes first.');
                }
            } else {
                if ($palletCount > 0) {
                    return redirect()->route('admin.order-management.packaging.index')
                        ->with('error', 'Cannot disable pallets while pallets exist. Delete all pallets first.');
                }
            }
        }

        $package->update([
            'client_id' => $validated['client_id'],
            'use_pallets' => $usePallets,
            'status' => $newStatus,
            'terms_of_delivery' => $validated['terms_of_delivery'] ?? Invoice::DEFAULT_TERMS_OF_DELIVERY,
            'terms_of_payment' => $validated['terms_of_payment'] ?? Invoice::DEFAULT_TERMS_OF_PAYMENT,
            'pre_carriage_by' => $this->nullableTrimmedString($validated['pre_carriage_by'] ?? null),
            'place_of_receipt' => $this->nullableTrimmedString($validated['place_of_receipt'] ?? null),
            'vessel_flight_no' => $this->nullableTrimmedString($validated['vessel_flight_no'] ?? null),
            'port_of_loading' => $this->nullableTrimmedString($validated['port_of_loading'] ?? null),
            'shipping_date' => $validated['shipping_date'] ?? null,
            'invoice_number' => $this->nullableTrimmedString($validated['invoice_number'] ?? null),
            'invoice_date' => $validated['invoice_date'] ?? null,
            'gross_weight_kg' => $this->nullableFloat($validated['gross_weight_kg'] ?? null),
            'net_weight_kg' => $this->nullableFloat($validated['net_weight_kg'] ?? null),
            'dim_weight_kg' => $this->nullableFloat($validated['dim_weight_kg'] ?? null),
            'specify_dimension' => $validated['specify_dimension'] ?? null,
        ]);

        $this->syncDerivedPackageCounts($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Package updated successfully.');
    }

    /**
     * @param  mixed  $value
     */
    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  mixed  $value
     */
    private function nullableTrimmedString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Prefer package overrides; otherwise use the latest invoice linked to the package.
     *
     * @return array{invoiceNumber: string, invoiceDateYmd: ?string, invoiceDateDisplay: string}
     */
    private function resolvePackageInvoiceDisplay(Package $package): array
    {
        $storedNumber = trim((string) ($package->invoice_number ?? ''));
        $storedDate = $package->invoice_date;

        $lastInvoice = null;
        if ($package->relationLoaded('invoices')) {
            $lastInvoice = $package->invoices->sortByDesc('id')->first();
        } else {
            $lastInvoice = $package->invoices()->orderByDesc('id')->first();
        }

        $invoiceNumber = $storedNumber !== ''
            ? $storedNumber
            : ($lastInvoice ? $lastInvoice->formattedInvoiceNumber() : '');

        $invoiceDate = $storedDate ?? ($lastInvoice?->invoice_date);

        return [
            'invoiceNumber' => $invoiceNumber,
            'invoiceDateYmd' => $invoiceDate ? $invoiceDate->format('Y-m-d') : null,
            'invoiceDateDisplay' => $invoiceDate ? $invoiceDate->format('d.m.Y') : '',
        ];
    }

    /**
     * Add a new pallet to a package (pallets mode only).
     */
    public function storePallet(Request $request, Package $package)
    {
        if (! $package->use_pallets) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Enable pallets mode before adding pallets.');
        }

        if ($package->boxes()->whereNull('package_pallet_id')->exists()) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Cannot add pallets while flat boxes exist.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'planned_box_count' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $next = ((int) $package->packagePallets()->max('pallet_number')) + 1;
        $name = trim((string) $validated['name']);
        PackagePallet::create([
            'package_id' => $package->id,
            'pallet_number' => $next,
            'name' => $name,
            'planned_box_count' => (int) $validated['planned_box_count'],
        ]);

        $this->syncDerivedPackageCounts($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Pallet "'.$name.'" added successfully.');
    }

    /**
     * Update pallet name / planned box count.
     */
    public function updatePallet(Request $request, Package $package, PackagePallet $packagePallet)
    {
        if ($packagePallet->package_id !== $package->id) {
            abort(404);
        }

        $existingBoxes = $packagePallet->boxes()->count();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'planned_box_count' => ['required', 'integer', 'min:'.max(1, $existingBoxes), 'max:999'],
        ]);

        $name = trim((string) $validated['name']);
        $planned = (int) $validated['planned_box_count'];

        $packagePallet->update([
            'name' => $name,
            'planned_box_count' => $planned,
        ]);

        $this->renumberBoxes(null, $packagePallet);
        $this->syncDerivedPackageCounts($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Pallet "'.$name.'" updated successfully.');
    }

    /**
     * Delete a single pallet (and its boxes via cascade).
     */
    public function destroyPallet(Package $package, PackagePallet $packagePallet)
    {
        if ($packagePallet->package_id !== $package->id) {
            abort(404);
        }

        $packagePallet->delete();
        $this->renumberPackagePallets($package);
        $this->renumberBoxesForAllPallets($package);
        $this->syncPackageStatusAfterBoxesChange($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Pallet deleted successfully.');
    }

    /**
     * Delete all pallets (and their boxes) for a package.
     */
    public function destroyAllPallets(Package $package)
    {
        if (! $package->use_pallets && $package->packagePallets()->count() === 0) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'No pallets to delete.');
        }

        DB::transaction(function () use ($package) {
            $package->packagePallets()->delete();
            $package->update(['use_pallets' => false]);
        });

        $this->syncPackageStatusAfterBoxesChange($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'All pallets deleted successfully.');
    }

    /**
     * Store pallets as digits only (DB column remains string). Zero/empty → null.
     *
     * @param  mixed  $value
     */
    private function nullablePalletsValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = (int) $value;

        return $n > 0 ? (string) $n : null;
    }

    /**
     * Numeric pallets count, or null when unset / zero. Supports legacy free-text values.
     *
     * @param  mixed  $value
     */
    private function palletsCount($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $n = (int) $raw;

            return $n > 0 ? $n : null;
        }

        if (preg_match('/\d+/', $raw, $matches)) {
            $n = (int) $matches[0];

            return $n > 0 ? $n : null;
        }

        return null;
    }

    /**
     * PDF label for pallets count, e.g. "Pallet - 2". Only when count > 0.
     *
     * @param  mixed  $value
     */
    private function formatPalletsForPdf($value): ?string
    {
        $count = $this->palletsCount($value);

        return $count !== null ? 'Pallet - '.$count : null;
    }

    /**
     * Delete a package only when it has no boxes defined.
     */
    public function destroy(Package $package)
    {
        if (! auth()->user()?->can('packaging.delete')) {
            abort(403);
        }

        if ($package->boxes()->exists()) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Cannot delete a package that has boxes defined. Remove all boxes first.');
        }

        $package->delete();

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Package deleted successfully.');
    }

    /**
     * Update a box (PO references with weight)
     */
    public function updateBox(Request $request, Package $package, PackageBox $packageBox)
    {
        if ($packageBox->package_id !== $package->id) {
            abort(404);
        }

        $validated = $request->validate([
            'order_po_weights' => ['required', 'array'],
            'order_po_weights.*.order_po_id' => ['required', 'integer', 'exists:ordersPO,id'],
            'order_po_weights.*.weight' => ['nullable', 'numeric', 'min:0'],
            'order_po_weights.*.line_item_desc' => ['required', 'integer'],
            'order_po_weights.*.qty' => ['required', 'integer', 'min:1'],
            'order_po_weights.*.is_pairs' => ['nullable', 'boolean'],
        ]);

        // Validate that line_item_desc IDs exist in the line_item_desc options
        $lineItemDescSetting = SiteSetting::where('key', 'line_item_desc')->first();
        $validLineItemDescIds = [];
        if ($lineItemDescSetting && $lineItemDescSetting->type === 'json') {
            $decoded = json_decode($lineItemDescSetting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $validLineItemDescIds = array_map(function ($item) {
                    return (int) ($item['id'] ?? 0);
                }, $decoded);
            }
        }
        foreach ($validated['order_po_weights'] as $item) {
            if (! in_array((int) $item['line_item_desc'], $validLineItemDescIds, true)) {
                return redirect()->back()->withErrors([
                    'order_po_weights' => 'Invalid Line Item Desc ID selected.',
                ])->withInput();
            }
        }

        $orderPoIds = array_column($validated['order_po_weights'], 'order_po_id');
        $pos = OrderPO::with('order')->whereIn('id', $orderPoIds)->get();
        foreach ($pos as $po) {
            if ($po->order->ClientId != $package->client_id) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Selected PO does not belong to this package\'s client.');
            }
        }

        foreach ($validated['order_po_weights'] as $item) {
            $po = $pos->firstWhere('id', $item['order_po_id']);
            $poTotal = is_numeric($po->QtyByOrder) ? (int) $po->QtyByOrder : 0;
            $packedElsewhere = $this->sumPackedQtyForOrderPoExcludingBox((int) $po->id, (int) $packageBox->id);
            $maxAllowed = $poTotal - $packedElsewhere;
            $qty = (int) $item['qty'];
            if ($qty < 1 || $qty > $maxAllowed) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Quantity for one or more POs exceeds the remaining unpackaged quantity.');
            }
        }

        $syncData = [];
        foreach ($validated['order_po_weights'] as $item) {
            $w = $item['weight'] ?? null;
            $weight = ($w === null || $w === '') ? null : (float) $w;

            $syncData[$item['order_po_id']] = [
                'weight' => $weight,
                'line_item_desc' => (int) $item['line_item_desc'],
                'qty' => (int) $item['qty'],
                'is_pairs' => ! empty($item['is_pairs']),
            ];
        }
        $packageBox->orderPOs()->sync($syncData);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Box updated successfully.');
    }

    /**
     * Delete a box from a package
     */
    public function destroyBox(Package $package, PackageBox $packageBox)
    {
        if ($packageBox->package_id !== $package->id) {
            abort(404);
        }
        $pallet = $packageBox->packagePallet;
        $packageBox->delete();

        if ($pallet) {
            $this->renumberBoxes(null, $pallet);
        } else {
            $this->renumberBoxes($package, null);
        }

        $this->syncPackageStatusAfterBoxesChange($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Box deleted successfully.');
    }

    /**
     * Duplicate a box: new row with the same PO lines, quantities, weights, and line item desc.
     * Requires each PO to still be QC completed (Produced) with enough remaining unpackaged quantity.
     */
    public function duplicateBox(Package $package, PackageBox $packageBox)
    {
        if ($packageBox->package_id !== $package->id) {
            abort(404);
        }

        $packageBox->load('orderPOs');
        if ($packageBox->orderPOs->isEmpty()) {
            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Cannot duplicate a box that has no purchase orders.');
        }

        $pallet = null;
        if ($package->use_pallets) {
            if (! $packageBox->package_pallet_id) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Cannot duplicate: box is not assigned to a pallet.');
            }
            $pallet = PackagePallet::where('package_id', $package->id)
                ->where('id', $packageBox->package_pallet_id)
                ->first();
            if (! $pallet) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Invalid pallet for this package.');
            }
            $planned = (int) ($pallet->planned_box_count ?? 0);
            $existingBoxes = $pallet->boxes()->count();
            if ($planned > 0 && $existingBoxes >= $planned) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'This pallet already has all '.$planned.' box(es).');
            }
        }

        $lineItemDescSetting = SiteSetting::where('key', 'line_item_desc')->first();
        $validLineItemDescIds = [];
        if ($lineItemDescSetting && $lineItemDescSetting->type === 'json') {
            $decoded = json_decode($lineItemDescSetting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $validLineItemDescIds = array_map(function ($item) {
                    return (int) ($item['id'] ?? 0);
                }, $decoded);
            }
        }

        foreach ($packageBox->orderPOs as $poFromPivot) {
            $lineId = $poFromPivot->pivot->line_item_desc ?? null;
            if ($lineId === null || ! in_array((int) $lineId, $validLineItemDescIds, true)) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Cannot duplicate: invalid line item description on the source box.');
            }
        }

        $producedId = $this->getProducedStatusId();
        $orderPoIds = $packageBox->orderPOs->pluck('id')->all();
        $pos = OrderPO::with('order')->whereIn('id', $orderPoIds)->get();

        foreach ($packageBox->orderPOs as $poFromPivot) {
            $po = $pos->firstWhere('id', $poFromPivot->id);
            if (! $po) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Cannot duplicate: a linked purchase order was not found.');
            }
            if ($po->order->ClientId != $package->client_id) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Selected PO does not belong to this package\'s client.');
            }
            if ((int) $po->Status !== $producedId) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Cannot duplicate: one or more POs are not QC completed (Produced).');
            }
            $qty = $this->boxLineQuantity($poFromPivot);
            if ($qty < 1) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Cannot duplicate: invalid quantity on one or more lines.');
            }
            $poTotal = is_numeric($po->QtyByOrder) ? (int) $po->QtyByOrder : 0;
            $packedElsewhere = $this->sumPackedQtyForOrderPoExcludingBox((int) $po->id, null);
            $maxAllowed = $poTotal - $packedElsewhere;
            if ($qty > $maxAllowed) {
                return redirect()->route('admin.order-management.packaging.index')
                    ->with('error', 'Not enough available quantity from QC completed POs to duplicate this box. Free unpackaged stock or reduce quantities first.');
            }
        }

        $boxNumber = $this->nextBoxNumber($package, $pallet);

        $newBox = PackageBox::create([
            'package_id' => $package->id,
            'package_pallet_id' => $pallet?->id,
            'box_number' => $boxNumber,
        ]);

        $syncData = [];
        foreach ($packageBox->orderPOs as $poFromPivot) {
            $w = $poFromPivot->pivot->weight ?? null;
            $weight = ($w === null || $w === '') ? null : (float) $w;

            $syncData[$poFromPivot->id] = [
                'weight' => $weight,
                'line_item_desc' => (int) $poFromPivot->pivot->line_item_desc,
                'qty' => $this->boxLineQuantity($poFromPivot),
                'is_pairs' => (bool) ($poFromPivot->pivot->is_pairs ?? false),
            ];
        }
        $newBox->orderPOs()->attach($syncData);

        if ($pallet) {
            $this->renumberBoxes(null, $pallet);
            $newBox->refresh();
            $boxNumber = $newBox->box_number;
        }

        $this->syncPackageStatusAfterBoxesChange($package);

        return redirect()->route('admin.order-management.packaging.index')
            ->with('success', 'Box '.$boxNumber.' added (duplicate).');
    }

    /**
     * Generate and view Package Box-Wise List PDF
     */
    public function viewPackagePdf(Package $package)
    {
        try {
            $package->load(['client', 'boxes.orderPOs.order']);

            $clientName = $package->client?->client_name ?? '-';

            // Year format: 25-26 (current year % 100, next year % 100)
            $currentYear = (int) date('Y');
            $nextYear = $currentYear + 1;
            $yearShort = sprintf('%02d-%02d', $currentYear % 100, $nextYear % 100);

            // Today's date in dd/mm/yyyy (for display)
            $todayDate = date('d/m/Y');
            // Date for filename in DD-MM-YYYY format
            $filenameDate = date('d-m-Y');

            $boxes = [];
            foreach ($package->boxes as $box) {
                $poRows = [];
                foreach ($box->orderPOs as $po) {
                    $order = $po->order;
                    $buckleWidthSize = '-';
                    if ($po->ClientModel) {
                        $clientModel = ClientModel::where('ModelNo', $po->ClientModel)->first();
                        if ($clientModel?->BuckleWidthId) {
                            $buckleWidth = BuckleWidth::find($clientModel->BuckleWidthId);
                            $buckleWidthSize = $buckleWidth?->BuckleWidthSize ?? '-';
                        }
                    }

                    $goldQty = is_numeric($po->Gold) ? (int) $po->Gold : 0;
                    $silverQty = is_numeric($po->Silver) ? (int) $po->Silver : 0;

                    // Always create two rows: one for GOLD, one for SILVER (as per format-1.pdf)
                    // GOLD row
                    $poRows[] = [
                        'orderNumber' => $order?->OrderNumber ?? '-',
                        'refNumber' => $po->RefNumber ?? '-',
                        'psNumber' => $po->PsNumber ?? '-',
                        'buckles' => 'GOLD',
                        'size' => $buckleWidthSize,
                        'qty' => $goldQty,
                    ];
                    // SILVER row
                    $poRows[] = [
                        'orderNumber' => $order?->OrderNumber ?? '-',
                        'refNumber' => $po->RefNumber ?? '-',
                        'psNumber' => $po->PsNumber ?? '-',
                        'buckles' => 'SILVER',
                        'size' => $buckleWidthSize,
                        'qty' => $silverQty,
                    ];
                }
                $boxes[] = [
                    'boxNumber' => $this->displaySibBoxNumber($box->box_number),
                    'orderPOs' => $poRows,
                ];
            }

            $data = [
                'clientName' => $clientName,
                'yearShort' => $yearShort,
                'todayDate' => $todayDate,
                'boxes' => $boxes,
            ];

            $html = view('pdf.package-box-wise-list', $data)->render();

            $pdf = PDF::loadHTML($html)
                ->setOption('margin-top', 10)
                ->setOption('margin-bottom', 10)
                ->setOption('margin-left', 10)
                ->setOption('margin-right', 10)
                ->setOption('encoding', 'UTF-8')
                // Prevent wkhtmltopdf from shrinking/compressing text glyphs
                ->setOption('disable-smart-shrinking', true)
                ->setOption('zoom', 1)
                ->setOption('dpi', 300)
                ->setOption('print-media-type', true)
                ->setOption('no-outline', true);

            // Format filename as BoxWise-List-{clientname}-DD-MM-YYYY
            $safeClientName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $clientName);
            $filename = 'BoxWise-List-'.$safeClientName.'-'.$filenameDate.'.pdf';

            return $pdf->inline($filename);
        } catch (\Throwable $e) {
            Log::error('Error generating Package Box-Wise PDF: '.$e->getMessage(), [
                'package_id' => $package->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    /**
     * Generate and view Package Box-Wise List PDF (Format 2)
     */
    public function viewPackagePdf2(Package $package)
    {
        try {
            $package->load(['client', 'boxes.orderPOs.order']);

            $clientName = $package->client?->client_name ?? '-';

            // Year format: 25-26 (current year % 100, next year % 100)
            $currentYear = (int) date('Y');
            $nextYear = $currentYear + 1;
            $yearShort = sprintf('%02d-%02d', $currentYear % 100, $nextYear % 100);

            // Shipping date format: dd-Mmm-yy (e.g., 10-Apr-25)
            $shippingDate = $package->shipping_date
                ? $package->shipping_date->format('d-M-y')
                : date('d-M-y');

            // Calculate total weight and total qty
            $totalWeight = 0;
            $totalQty = 0;
            $allRows = [];

            $orderNumber = 1; // Sequential order number starting from 1
            foreach ($package->boxes as $box) {
                foreach ($box->orderPOs as $po) {
                    $order = $po->order;
                    $buckleWidthSize = '-';
                    if ($po->ClientModel) {
                        $clientModel = ClientModel::where('ModelNo', $po->ClientModel)->first();
                        if ($clientModel?->BuckleWidthId) {
                            $buckleWidth = BuckleWidth::find($clientModel->BuckleWidthId);
                            $buckleWidthSize = $buckleWidth?->BuckleWidthSize ?? '-';
                        }
                    }

                    $weight = $po->pivot->weight ?? 0;
                    $qty = $this->boxLineQuantity($po);

                    $totalWeight += $weight;
                    $totalQty += $qty;

                    $allRows[] = [
                        'orderNumber' => $orderNumber++,
                        'boxCode' => $this->displaySibBoxNumber($box->box_number),
                        'netWeight' => $weight,
                        'psNumber' => $po->PsNumber ?? '-',
                        'size' => $buckleWidthSize,
                        'orderNo' => $order?->OrderNumber ?? '-',
                        'refNumber' => $po->RefNumber ?? '-',
                        'qty' => $qty,
                    ];
                }
            }

            // Format total weight as "XX.XX kgs"
            $totalWeightFormatted = number_format($totalWeight, 2, '.', '').' kgs';

            // Package reference number (5 digits with leading zeros)
            $packageRefNumber = str_pad((string) $package->id, 5, '0', STR_PAD_LEFT);

            $data = [
                'clientName' => $clientName,
                'yearShort' => $yearShort,
                'shippingDate' => $shippingDate,
                'totalWeight' => $totalWeightFormatted,
                'packageRefNumber' => $packageRefNumber,
                'totalQty' => $totalQty,
                'rows' => $allRows,
            ];

            $html = view('pdf.package-box-wise-list-2', $data)->render();

            $pdf = PDF::loadHTML($html)
                ->setOption('margin-top', 10)
                ->setOption('margin-bottom', 10)
                ->setOption('margin-left', 10)
                ->setOption('margin-right', 10)
                ->setOption('encoding', 'UTF-8')
                ->setOption('disable-smart-shrinking', true)
                ->setOption('zoom', 1)
                ->setOption('dpi', 300)
                ->setOption('print-media-type', true)
                ->setOption('viewport-size', '1280x1024')
                ->setOption('no-outline', true);

            // Format filename as BoxWise-List-2-{clientname}-DD-MM-YYYY
            $filenameDate = date('d-m-Y');
            $safeClientName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $clientName);
            $filename = 'BoxWise-List-2-'.$safeClientName.'-'.$filenameDate.'.pdf';

            return $pdf->inline($filename);
        } catch (\Throwable $e) {
            Log::error('Error generating Package Box-Wise PDF (Format 2): '.$e->getMessage(), [
                'package_id' => $package->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    /**
     * Generate and view Package Box-Wise List PDF (Format 3 - DHL Annexure)
     */
    public function viewPackagePdf3(Request $request, Package $package)
    {
        try {
            $data = $this->buildPackingListData($package);
            $clientName = $data['clientName'];
            $filenameDate = $data['filenameDate'];

            $html = view('pdf.package-box-wise-list-3', $data)->render();

            // HTML preview mode for easier layout iteration
            if ($request->boolean('preview')) {
                return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
            }

            // Format filename as BoxWise-List-3-{clientname}-DD-MM-YYYY
            $safeClientName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $clientName);
            $filename = 'BoxWise-List-3-'.$safeClientName.'-'.$filenameDate.'.pdf';

            $pdf = PDF::loadHTML($html)
                ->setOption('margin-top', 10)
                ->setOption('margin-bottom', 10)
                ->setOption('margin-left', 10)
                ->setOption('margin-right', 10)
                ->setOption('encoding', 'UTF-8')
                ->setOption('no-outline', true);

            return $pdf->inline($filename);
        } catch (\Throwable $e) {
            Log::error('Error generating Package Box-Wise PDF (Format 3): '.$e->getMessage(), [
                'package_id' => $package->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    /**
     * Download packing list as Excel (same data as Packaging PDF).
     */
    public function viewPackageExcel(Package $package)
    {
        try {
            $data = $this->buildPackingListData($package);
            $safeClientName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', (string) ($data['clientName'] ?? 'package'));
            $filename = 'Packing-List-'.$safeClientName.'-'.$data['filenameDate'].'.xlsx';

            return Excel::download(new PackagePackingListExport($data), $filename);
        } catch (\Throwable $e) {
            Log::error('Error generating Package Packing Excel: '.$e->getMessage(), [
                'package_id' => $package->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.order-management.packaging.index')
                ->with('error', 'Failed to generate Excel: '.$e->getMessage());
        }
    }

    /**
     * Shared packing-list payload for Packaging PDF (format 3) and Excel.
     *
     * @return array<string, mixed>
     */
    private function buildPackingListData(Package $package): array
    {
            $package->load(['client.buyer', 'boxes.orderPOs.order', 'packagePallets.boxes.orderPOs.order']);

            $client = $package->client;
            $clientName = $client?->client_name ?? '-';
            $buyer = $client?->buyer;

            // Get company details from site_settings
            $companySettings = SiteSetting::whereIn('key', [
                'company.name',
                'company.address',
                'company.exporter_ref',
                'company.iec_no',
                'company.pan_no',
                'company.terms_of_delivery_and_payments',
                'company.features',
            ])->pluck('value', 'key')->toArray();

            $companyName = $companySettings['company.name'] ?? 'SIBRAMA NEWAGE';
            $exporterRef = $companySettings['company.exporter_ref'] ?? 'DS - 011745';
            $iecNo = $companySettings['company.iec_no'] ?? '4101000042';
            $panNo = $companySettings['company.pan_no'] ?? 'AAEFS5453M';
            $companyAddress = $companySettings['company.address'] ?? '';
            $paymentToDetails = $companySettings['company.terms_of_delivery_and_payments'] ?? '';
            $companyFeatures = $companySettings['company.features'] ?? '';

            // Year format: 25-26 (current year % 100, next year % 100)
            $currentYear = (int) date('Y');
            $nextYear = $currentYear + 1;
            $yearShort = sprintf('%02d-%02d', $currentYear % 100, $nextYear % 100);

            $package->loadMissing(['invoices' => static fn ($q) => $q->orderByDesc('id')]);
            $invoiceDisplay = $this->resolvePackageInvoiceDisplay($package);
            $invoiceNumber = $invoiceDisplay['invoiceNumber'] !== ''
                ? $invoiceDisplay['invoiceNumber']
                : ('SN/'.$yearShort.'/'.str_pad((string) $package->id, 5, '0', STR_PAD_LEFT));
            $invoiceDateDisplay = $invoiceDisplay['invoiceDateDisplay'] !== ''
                ? $invoiceDisplay['invoiceDateDisplay']
                : date('d.m.Y');
            $filenameDate = date('d-m-Y');

            // Package reference number (5 digits with leading zeros)
            $packageRefNumber = str_pad((string) $package->id, 5, '0', STR_PAD_LEFT);

            // Collect all unique orders with their dates
            $orderNumbers = [];
            $orderDates = [];
            foreach ($package->boxes as $box) {
                foreach ($box->orderPOs as $po) {
                    $order = $po->order;
                    if ($order) {
                        $orderNo = $order->OrderNumber ?? '';
                        if ($orderNo && ! in_array($orderNo, $orderNumbers)) {
                            $orderNumbers[] = $orderNo;
                            $orderDate = $order->DeliveryDate
                                ? $order->DeliveryDate->format('d.m.y')
                                : ($order->created_at ? $order->created_at->format('d.m.y') : '');
                            if ($orderDate) {
                                $orderDates[] = $orderNo.' DT. '.$orderDate;
                            } else {
                                $orderDates[] = $orderNo;
                            }
                        }
                    }
                }
            }
            $buyersOrderNoAndDate = implode(', ', $orderDates);

            // Build table rows: flat boxes, or pallet sections (Pallet N → its boxes)
            $tableRows = [];
            $palletSections = [];
            $totalQty = 0;
            $totalQtyNos = 0;
            $totalQtyPairs = 0;
            $hasPairs = false;
            $hasNos = false;
            $lineCount = 0;
            $totalBoxes = $package->boxes->count();
            $usePallets = (bool) $package->use_pallets;

            $buildBoxGroup = function (PackageBox $box, string $mark) use (
                &$totalQty,
                &$totalQtyNos,
                &$totalQtyPairs,
                &$hasPairs,
                &$hasNos,
                &$lineCount
            ) {
                $boxRows = [];
                foreach ($box->orderPOs as $po) {
                    $lineItemDesc = $this->getLineItemDescName($po->pivot->line_item_desc ?? null) ?? 'LEATHER WATCH STRAPS WITH BUCKLES';
                    $refArticle = trim((string) ($po->ClientModel ?? '')) ?: '-';
                    $refPrefix = is_string($refArticle) && $refArticle !== '' && $refArticle !== '-'
                        ? strtoupper(substr($refArticle, 0, 3))
                        : '-';
                    $psNumber = $po->PsNumber ?? '-';
                    $qty = $this->boxLineQuantity($po);
                    $isPairs = $this->pivotIsPairs($po->pivot->is_pairs ?? null);
                    $totalQty += $qty;
                    $lineCount++;
                    if ($isPairs) {
                        $hasPairs = true;
                        $totalQtyPairs += $qty;
                    } else {
                        $hasNos = true;
                        $totalQtyNos += $qty;
                    }

                    $rawWeight = $po->pivot->weight ?? null;
                    $weightForRow = ($rawWeight !== null && $rawWeight !== '' && (float) $rawWeight > 0)
                        ? (float) $rawWeight
                        : null;

                    $boxRows[] = [
                        'description' => $lineItemDesc,
                        'refArticle' => $refArticle,
                        'refPrefix' => $refPrefix,
                        'psNumber' => $psNumber,
                        'quantity' => $qty,
                        'isPairs' => $isPairs,
                        'weight' => $weightForRow,
                    ];
                }
                if (count($boxRows) === 0) {
                    return null;
                }

                return [
                    'boxNumber' => $mark,
                    'rows' => $boxRows,
                ];
            };

            if ($usePallets) {
                foreach ($package->packagePallets as $pallet) {
                    $sectionBoxes = [];
                    $boxInPallet = 0;
                    $plannedTotal = $this->palletPlannedBoxCount($pallet);
                    foreach ($pallet->boxes as $box) {
                        $boxInPallet++;
                        $mark = $this->sibBoxLabel($boxInPallet, $plannedTotal);
                        $group = $buildBoxGroup($box, $mark);
                        if ($group !== null) {
                            $sectionBoxes[] = $group;
                        }
                    }
                    if (count($sectionBoxes) > 0) {
                        $palletLabel = trim((string) ($pallet->name ?? ''));
                        if ($palletLabel === '') {
                            $palletLabel = 'Pallet '.$pallet->pallet_number;
                        }
                        $palletSections[] = [
                            'label' => $palletLabel,
                            'boxes' => $sectionBoxes,
                        ];
                    }
                }
            } else {
                $boxIndex = 0;
                foreach ($package->boxes as $box) {
                    $boxIndex++;
                    $mark = $this->sibBoxLabel($boxIndex, $totalBoxes);
                    $group = $buildBoxGroup($box, $mark);
                    if ($group !== null) {
                        $tableRows[] = $group;
                    }
                }
            }

            // Flatten for page/filler estimates
            $items = collect();
            if ($usePallets) {
                foreach ($palletSections as $section) {
                    foreach ($section['boxes'] as $group) {
                        foreach ($group['rows'] as $row) {
                            $items->push((object) [
                                'mark' => $group['boxNumber'],
                                'package' => $section['label'],
                                'description' => $row['description'],
                                'article' => $row['refArticle'],
                                'refPrefix' => $row['refPrefix'] ?? '-',
                                'ref' => $row['psNumber'],
                                'qty' => $row['quantity'],
                                'weight' => $row['weight'] ?? null,
                            ]);
                        }
                    }
                }
            } else {
                foreach ($tableRows as $group) {
                    foreach ($group['rows'] as $row) {
                        $items->push((object) [
                            'mark' => $group['boxNumber'],
                            'package' => 'Corrugated Boxes',
                            'description' => $row['description'],
                            'article' => $row['refArticle'],
                            'refPrefix' => $row['refPrefix'] ?? '-',
                            'ref' => $row['psNumber'],
                            'qty' => $row['quantity'],
                            'weight' => $row['weight'] ?? null,
                        ]);
                    }
                }
            }

            // Estimate total PDF pages for header label (PAGE - 1/N).
            // Target fills the middle so the footer sits near the bottom of page 1
            // without spilling onto page 2 (28 was too high; 16 left a large gap).
            $palletsCount = $usePallets ? $package->packagePallets->count() : null;
            if ($palletsCount !== null && $palletsCount < 1) {
                $palletsCount = null;
            }
            $pallets = null;

            $packingPdfPages = $this->buildPackingListPdfPages($usePallets, $palletSections, $tableRows);
            $totalPages = max(1, count($packingPdfPages));
            $pageLabel = 'PAGE - 1/'.$totalPages;
            $fillerRows = (int) ($packingPdfPages[0]['fillerRows'] ?? 0);

            $totalPackages = $usePallets
                ? (int) $package->packagePallets->count()
                : count($tableRows);
            $specifyDimensionLines = array_values(array_filter(
                array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($package->specify_dimension ?? ''))),
                static fn ($line) => $line !== ''
            ));

            // Client/Buyer information
            $consigneeName = $clientName;
            $consigneeAddress = trim(implode(', ', array_filter([
                $client?->address,
                $client?->city,
                $client?->country,
            ])));
            $consigneePhone = $client?->phone ?? '';

            $buyerName = $buyer?->buyer_name ?? $clientName;
            $buyerAddress = trim(implode(', ', array_filter([
                $buyer?->address,
                $buyer?->city,
                $buyer?->country,
            ]))) ?: $consigneeAddress;

            // Hardcoded defaults for missing fields (as per plan)
            $attention = trim((string) ($client?->attention ?? ''));
            $countryOfFinalDestination = $client?->country ?? 'FRANCE';
            $portOfLoading = trim((string) ($package->port_of_loading ?? ''));
            if ($portOfLoading === '') {
                $portOfLoading = 'NEW DELHI';
            }
            $portOfDischarge = $client?->port_of_discharge ?: 'PARIS FRANCE';
            $finalDestination = $countryOfFinalDestination;
            $vesselFlightNo = trim((string) ($package->vessel_flight_no ?? ''));
            $preCarriageBy = trim((string) ($package->pre_carriage_by ?? ''));
            $placeOfReceipt = trim((string) ($package->place_of_receipt ?? ''));
            $countryOfOrigin = 'INDIA';
            $customerReference = $totalPages > 1
                ? sprintf('C/O - PAGE - %02d', $totalPages)
                : '';

            $data = [
                // Company details
                'companyName' => $companyName,
                'exporterRef' => $exporterRef,
                'iecNo' => $iecNo,
                'panNo' => $panNo,
                'companyAddress' => $companyAddress,
                'termsOfPayment' => $package->terms_of_payment ?: Invoice::DEFAULT_TERMS_OF_PAYMENT,
                'termsOfDeliveryOption' => $package->terms_of_delivery ?: Invoice::DEFAULT_TERMS_OF_DELIVERY,
                'paymentToDetails' => $paymentToDetails,
                'companyFeatures' => $companyFeatures,

                // Package/Invoice details
                'invoiceNumber' => $invoiceNumber,
                'invoiceDate' => $invoiceDateDisplay,
                'yearShort' => $yearShort,
                'packageRefNumber' => $packageRefNumber,
                'pageLabel' => $pageLabel,
                'fillerRows' => $fillerRows,
                'packingPdfPages' => $packingPdfPages,
                'totalPages' => $totalPages,

                // Buyer/Order details
                'buyersOrderNoAndDate' => $buyersOrderNoAndDate,
                'attention' => $attention,
                'consigneeName' => $consigneeName,
                'consigneeAddress' => $consigneeAddress,
                'consigneePhone' => $consigneePhone,
                'buyerName' => $buyerName,
                'buyerAddress' => $buyerAddress,

                // Shipping details
                'countryOfFinalDestination' => $countryOfFinalDestination,
                'portOfLoading' => $portOfLoading,
                'portOfDischarge' => $portOfDischarge,
                'finalDestination' => $finalDestination,
                'vesselFlightNo' => $vesselFlightNo,
                'preCarriageBy' => $preCarriageBy,
                'placeOfReceipt' => $placeOfReceipt,
                'countryOfOrigin' => $countryOfOrigin,

                // Table data
                'tableRows' => $tableRows,
                'palletSections' => $palletSections,
                'usePallets' => $usePallets,
                'items' => $items,
                'totalQty' => $totalQty,
                'totalQtyNos' => $totalQtyNos,
                'totalQtyPairs' => $totalQtyPairs,
                'hasNos' => $hasNos,
                'hasPairs' => $hasPairs,
                'allPairs' => $lineCount > 0 && $hasPairs && ! $hasNos,
                'totalPages' => $totalPages,
                'totalPackages' => $totalPackages,
                'grossWeightKg' => $package->gross_weight_kg !== null ? (float) $package->gross_weight_kg : null,
                'netWeightKg' => $package->net_weight_kg !== null ? (float) $package->net_weight_kg : null,
                'dimWeightKg' => $package->dim_weight_kg !== null ? (float) $package->dim_weight_kg : null,
                'specifyDimensionLines' => $specifyDimensionLines,
                'pallets' => $pallets,
                'palletsCount' => $palletsCount,

                // Footer details (LOP / Green Card / LUT block from Company Features in settings)
                'customerReference' => $customerReference,
                'clientName' => $clientName,
                'filenameDate' => $filenameDate,
            ];

        return $data;
    }

    /**
     * Split packing-list boxes into PDF pages with running qty/weight and carry-forward.
     *
     * @param  list<array{label: string, boxes: list<array<string, mixed>>}>  $palletSections
     * @param  list<array<string, mixed>>  $tableRows
     * @return list<array<string, mixed>>
     */
    private function buildPackingListPdfPages(bool $usePallets, array $palletSections, array $tableRows): array
    {
        $firstPageCap = 21;
        $nextPageCap = 23;

        $queue = [];
        if ($usePallets) {
            foreach ($palletSections as $section) {
                $isFirstBox = true;
                foreach ($section['boxes'] as $group) {
                    $stats = $this->packingBoxGroupStats($group);
                    $queue[] = [
                        'palletLabel' => $section['label'],
                        'showPalletHeader' => $isFirstBox,
                        'group' => $group,
                        'qty' => $stats['qty'],
                        'weight' => $stats['weight'],
                        'lineRows' => $stats['rowCount'],
                    ];
                    $isFirstBox = false;
                }
            }
        } else {
            foreach ($tableRows as $i => $group) {
                $stats = $this->packingBoxGroupStats($group);
                $queue[] = [
                    'palletLabel' => null,
                    'showPalletHeader' => $i === 0,
                    'group' => $group,
                    'qty' => $stats['qty'],
                    'weight' => $stats['weight'],
                    'lineRows' => $stats['rowCount'],
                ];
            }
        }

        $buckets = [];
        $bucket = [];
        $rowsUsed = 0;
        $cap = $firstPageCap;
        foreach ($queue as $item) {
            $need = $item['lineRows'] + (! empty($item['showPalletHeader']) ? 1 : 0);
            if ($bucket !== [] && ($rowsUsed + $need) > $cap) {
                $buckets[] = ['items' => $bucket, 'contentRowCount' => $rowsUsed];
                $bucket = [];
                $rowsUsed = 0;
                $cap = $nextPageCap;
                $item['showPalletHeader'] = true;
                $need = $item['lineRows'] + 1;
            }
            $bucket[] = $item;
            $rowsUsed += $need;
        }
        if ($bucket !== []) {
            $buckets[] = ['items' => $bucket, 'contentRowCount' => $rowsUsed];
        }
        if ($buckets === []) {
            $buckets[] = ['items' => [], 'contentRowCount' => 0];
        }

        $pages = [];
        $pageCount = count($buckets);
        $runQty = 0;
        $runWeight = 0.0;
        foreach ($buckets as $i => $bucketData) {
            $hasBf = $i > 0;
            $broughtForward = $hasBf ? ['qty' => $runQty, 'weight' => $runWeight] : null;
            foreach ($bucketData['items'] as $item) {
                $runQty += (int) $item['qty'];
                $runWeight = round($runWeight + (float) $item['weight'], 3);
            }
            $content = (int) $bucketData['contentRowCount'] + ($hasBf ? 1 : 0) + 1; // +1 subtotal/total row
            $capNow = $i === 0 ? $firstPageCap : $nextPageCap;
            $page = [
                'usePallets' => $usePallets,
                'tableRows' => [],
                'palletSections' => [],
                'showCorrugatedHeader' => ! $usePallets,
                'broughtForward' => $broughtForward,
                'pageSubtotal' => ['qty' => $runQty, 'weight' => $runWeight],
                'isLast' => $i === $pageCount - 1,
                'pageBreakBefore' => $i > 0,
                'pageLabel' => sprintf('PAGE - %d/%d', $i + 1, $pageCount),
                'fillerRows' => max(0, $capNow - $content),
            ];
            if ($usePallets) {
                $sections = [];
                $current = null;
                foreach ($bucketData['items'] as $item) {
                    $label = (string) ($item['palletLabel'] ?? '');
                    if ($current === null || $current['label'] !== $label) {
                        if ($current !== null) {
                            $sections[] = $current;
                        }
                        $current = [
                            'label' => $label,
                            'boxes' => [],
                            'showLabel' => ! empty($item['showPalletHeader']),
                        ];
                    } elseif (! empty($item['showPalletHeader'])) {
                        $current['showLabel'] = true;
                    }
                    $current['boxes'][] = $item['group'];
                }
                if ($current !== null) {
                    $sections[] = $current;
                }
                $page['palletSections'] = $sections;
            } else {
                $page['tableRows'] = array_map(static fn ($item) => $item['group'], $bucketData['items']);
            }
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param  array{rows?: list<array<string, mixed>>}  $group
     * @return array{qty: int, weight: float, rowCount: int}
     */
    private function packingBoxGroupStats(array $group): array
    {
        $qty = 0;
        $weight = 0.0;
        $rows = $group['rows'] ?? [];
        foreach ($rows as $row) {
            $qty += (int) ($row['quantity'] ?? 0);
            if (($row['weight'] ?? null) !== null && $row['weight'] !== '') {
                $weight += (float) $row['weight'];
            }
        }

        return [
            'qty' => $qty,
            'weight' => round($weight, 3),
            'rowCount' => count($rows),
        ];
    }
}
