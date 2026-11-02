<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientModel;
use App\Models\DesignDescription;
use App\Models\Invoice;
use App\Models\InvoiceFocGroup;
use App\Models\InvoiceFocItem;
use App\Models\InvoiceLineGroup;
use App\Models\InvoiceLineItem;
use App\Models\Leather;
use App\Models\Order;
use App\Models\OrderPO;
use App\Models\Package;
use App\Models\SiteSetting;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceManagementController extends Controller
{
    private const STATUS_VALUES = ['draft', 'complete', 'sent_to_client'];

    /** Matches Packaging: packaging_status id for Completed. */
    private const PACKAGING_STATUS_COMPLETED = 3;

    /** Max group + line + highlighter rows per invoice PDF page before page break. */
    private const INVOICE_EXPORT_PDF_ROWS_PER_PAGE = 30;

    /**
     * Minimum line-item tbody rows per page (content + blank fillers) so the middle section fills page height.
     * Continuation pages include the PDF footer (package summary left blank); last page uses a lower
     * target so totals + PDF footer stay on one sheet.
     */
    private const INVOICE_EXPORT_PDF_MIN_LINE_BODY_ROWS_PER_PAGE = 26;

    /** Minimum line rows on the last page (before middle-footer + invoice footer); lower to avoid splitting footer. */
    private const INVOICE_EXPORT_PDF_MIN_LINE_BODY_ROWS_LAST_PAGE = 22;

    /**
     * Max FOC content units on a continuation FOC page (items + table chrome + footers).
     * Multiple FOC tables may share a page when their combined unit cost fits.
     */
    private const INVOICE_EXPORT_PDF_FOC_PAGE_UNIT_BUDGET = 28;

    /**
     * Max FOC content units on the last FOC page (package summary / full footer with declaration).
     * Kept lower so statement + EOU/signature + declaration stay on the same sheet.
     */
    private const INVOICE_EXPORT_PDF_FOC_PAGE_UNIT_BUDGET_LAST = 18;

    /** Title + column header rows counted as units per FOC table embed. */
    private const INVOICE_EXPORT_PDF_FOC_TABLE_CHROME_UNITS = 2;

    /** Total + customs disclaimer rows on every FOC table. */
    private const INVOICE_EXPORT_PDF_FOC_TABLE_FOOTER_UNITS = 2;

    /** Yellow highlighter row on the finishing slice of a FOC group. */
    private const INVOICE_EXPORT_PDF_FOC_TABLE_YELLOW_UNITS = 1;

    /** Gap between stacked FOC tables on the same page. */
    private const INVOICE_EXPORT_PDF_FOC_TABLE_GAP_UNITS = 1;

    /** Minimal blank outer-table rows above the first FOC embed. */
    private const INVOICE_EXPORT_PDF_FOC_PAGE_TOP_BLANK_ROWS = 0;

    /** Minimal blank outer-table rows between stacked FOC embeds. */
    private const INVOICE_EXPORT_PDF_FOC_PAGE_GAP_BLANK_ROWS = 1;

    /**
     * Package IDs already selected on an invoice. When editing, ignore that invoice so its package stays selectable.
     *
     * @return array<int, int>
     */
    private function packageIdsReservedByInvoices(?int $exceptInvoiceId = null): array
    {
        $q = Invoice::query()->whereNotNull('package_id');
        if ($exceptInvoiceId !== null) {
            $q->where('id', '!=', $exceptInvoiceId);
        }

        return $q->pluck('package_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'complete' => 'Complete',
            'sent_to_client' => 'Sent to Client',
            default => $status,
        };
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getLineItemDescOptions(): array
    {
        $lineItemDescOptions = [];
        $lineItemDescSetting = SiteSetting::where('key', 'line_item_desc')->first();
        if ($lineItemDescSetting && $lineItemDescSetting->type === 'json') {
            $decoded = json_decode($lineItemDescSetting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $lineItemDescOptions = $decoded;
            }
        }
        if ($lineItemDescOptions === []) {
            $lineItemDescOptions = [
                ['id' => 1, 'name' => 'LEATHER WATCH STRAPS WITH BUCKLES'],
            ];
            SiteSetting::updateOrCreate(
                ['key' => 'line_item_desc'],
                ['value' => json_encode($lineItemDescOptions), 'type' => 'json']
            );
            Cache::forget('app_settings');
        }

        return $lineItemDescOptions;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getPoTagOptions(): array
    {
        $items = [];
        $setting = SiteSetting::where('key', 'po_tags')->first();
        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            }
        }

        return $items;
    }

    /**
     * Description of goods = Leather name from the PO's client model (same as production).
     */
    private function goodsDescriptionForOrderPo(?OrderPO $po, ?Order $order): string
    {
        if ($po === null || $order === null) {
            return '';
        }
        $modelNo = trim((string) ($po->ClientModel ?? ''));
        if ($modelNo === '') {
            return '';
        }
        $cm = ClientModel::where('ModelNo', $modelNo)
            ->where('ClientId', $order->ClientId)
            ->first();
        if ($cm === null) {
            return '';
        }
        if ($cm->LeatherId) {
            $leather = Leather::find($cm->LeatherId);
            if ($leather && trim((string) ($leather->Name ?? '')) !== '') {
                return trim((string) $leather->Name);
            }
        }

        return '';
    }

    /**
     * Design description from the PO's client model (production master).
     */
    private function designDescriptionForOrderPo(?OrderPO $po, ?Order $order): string
    {
        if ($po === null || $order === null) {
            return '';
        }
        $modelNo = trim((string) ($po->ClientModel ?? ''));
        if ($modelNo === '') {
            return '';
        }
        $cm = ClientModel::where('ModelNo', $modelNo)
            ->where('ClientId', $order->ClientId)
            ->first();
        if ($cm === null || ! $cm->DesignDescriptionId) {
            return '';
        }
        $dd = DesignDescription::find($cm->DesignDescriptionId);
        if ($dd === null) {
            return '';
        }
        $text = trim((string) ($dd->Description ?? ''));

        return $text !== '' ? $text : '';
    }

    /**
     * Resolve the PO's model template by ModelNo or RefNumber.
     */
    private function findClientModelForOrderPo(?OrderPO $po, ?Order $order): ?ClientModel
    {
        if ($po === null) {
            return null;
        }

        $modelNo = trim((string) ($po->ClientModel ?? ''));
        $ref = trim((string) ($po->RefNumber ?? ''));
        $clientId = $order?->ClientId;

        $try = static function (string $column, string $value) use ($clientId): ?ClientModel {
            if ($value === '' || $value === '-') {
                return null;
            }
            $query = ClientModel::query()->where($column, $value);
            if ($clientId) {
                $forClient = (clone $query)->where('ClientId', $clientId)->first();
                if ($forClient) {
                    return $forClient;
                }
            }

            return $query->first();
        };

        return $try('ModelNo', $modelNo)
            ?? $try('RefNumber', $ref)
            ?? $try('RefNumber', $modelNo)
            ?? $try('ModelNo', $ref);
    }

    /**
     * Description on Invoice from the PO's client model (model template).
     */
    private function invoiceDescriptionForOrderPo(?OrderPO $po, ?Order $order): string
    {
        $cm = $this->findClientModelForOrderPo($po, $order);
        if ($cm === null) {
            return '';
        }
        $fromInvoiceField = trim((string) ($cm->DescriptionOnInvoice ?? ''));
        if ($fromInvoiceField !== '') {
            return $fromInvoiceField;
        }

        return $this->designDescriptionForOrderPo($po, $order);
    }

    /**
     * Description of goods for invoice PDF/HTML and listings: editable line text
     * (prefilled from Model Template "Description on Invoice"), else line group label.
     *
     * @param  string  $fallbackIfEmpty  Line group label when design_name is empty.
     */
    private function invoiceLineGoodsDescriptionLine(
        InvoiceLineItem $li,
        ?OrderPO $po,
        ?Order $order,
        string $fallbackIfEmpty
    ): string {
        $stored = $li->design_name !== null && trim((string) $li->design_name) !== ''
            ? trim((string) $li->design_name)
            : '';

        if ($stored !== '') {
            return $stored;
        }

        return $fallbackIfEmpty !== '' ? $fallbackIfEmpty : '—';
    }

    private function orderPoIsInInvoicePackage(OrderPO $po, Invoice $invoice): bool
    {
        if (! $invoice->package_id) {
            return false;
        }

        return DB::table('package_boxes')
            ->join('package_box_order_po', 'package_boxes.id', '=', 'package_box_order_po.package_box_id')
            ->where('package_boxes.package_id', $invoice->package_id)
            ->where('package_box_order_po.order_po_id', $po->id)
            ->exists();
    }

    private function packageQtyForOrderPoInPackage(int $packageId, int $orderPoId): int
    {
        $sum = DB::table('package_box_order_po')
            ->join('package_boxes', 'package_boxes.id', '=', 'package_box_order_po.package_box_id')
            ->where('package_boxes.package_id', $packageId)
            ->where('package_box_order_po.order_po_id', $orderPoId)
            ->sum('package_box_order_po.qty');

        return (int) $sum;
    }

    /**
     * Total invoiced quantity per PO across all line groups on an invoice.
     *
     * @param  list<int>  $orderPoIds
     * @return array<int, int>
     */
    private function invoicedQtyByPoForInvoice(Invoice $invoice, array $orderPoIds, ?int $excludeLineItemId = null): array
    {
        if ($orderPoIds === []) {
            return [];
        }

        $query = InvoiceLineItem::query()
            ->whereHas('invoiceLineGroup', fn ($q) => $q->where('invoice_id', $invoice->id))
            ->whereIn('orders_po_id', $orderPoIds);
        if ($excludeLineItemId !== null) {
            $query->where('id', '!=', $excludeLineItemId);
        }

        return $query
            ->selectRaw('orders_po_id, COALESCE(SUM(quantity), 0) as t')
            ->groupBy('orders_po_id')
            ->pluck('t', 'orders_po_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function invoicedQtyForPoOnInvoice(Invoice $invoice, int $orderPoId, ?int $excludeLineItemId = null): int
    {
        $map = $this->invoicedQtyByPoForInvoice($invoice, [$orderPoId], $excludeLineItemId);

        return $map[$orderPoId] ?? 0;
    }

    /**
     * Label for "Add QC completed PO" autocomplete: legacy row (leather, ref, leather id, packaged qty)
     * plus PS number and quantity still available to invoice on this invoice.
     */
    private function buildInvoiceQcPoAutocompleteLabel(?OrderPO $po, ?Order $order, int $packageQty, int $remainingQty): string
    {
        if ($po === null || $order === null) {
            return '—';
        }

        $leatherName = $this->goodsDescriptionForOrderPo($po, $order);
        if ($leatherName === '') {
            $leatherName = '—';
        }
        $ref = $po->RefNumber ?? '-';
        $cm = null;
        $modelNo = trim((string) ($po->ClientModel ?? ''));
        if ($modelNo !== '') {
            $cm = ClientModel::where('ModelNo', $modelNo)
                ->where('ClientId', $order->ClientId)
                ->first();
        }
        $leatherIdPart = $cm && $cm->LeatherId ? (string) $cm->LeatherId : '—';

        $base = sprintf(
            '%s | %s | Leather : %s | Qty %s',
            $leatherName,
            $ref,
            $leatherIdPart,
            $packageQty
        );

        $ps = trim((string) ($po->PsNumber ?? ''));
        if ($ps === '') {
            $ps = '—';
        }

        return sprintf('%s | PS %s | Remaining %d', $base, $ps, max(0, $remainingQty));
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $options
     * @return array<int, string>
     */
    private function lineItemDescIdToNameMap(array $options): array
    {
        $map = [];
        foreach ($options as $item) {
            if (isset($item['id'], $item['name'])) {
                $map[(int) $item['id']] = (string) $item['name'];
            }
        }

        return $map;
    }

    /**
     * One line item in the same shape as the invoice index `lineItems` entries.
     *
     * @return array<string, mixed>
     */
    private function lineItemToFrontendArray(InvoiceLineItem $li, Invoice $invoice, int $lineItemDescId): array
    {
        $li->loadMissing(['orderPO.order']);
        $po = $li->orderPO;
        $order = $po?->order;
        $orderNo = $order?->OrderNumber ?? '-';
        $qtyFromPo = max(0, (int) ($po->QtyByOrder ?? 0));
        $qty = $li->quantity !== null ? max(0, (int) $li->quantity) : $qtyFromPo;
        $rate = (float) $li->rate;
        $amount = round($qty * $rate, 2);
        $discPct = (float) $li->discount_percent;
        $lessDiscount = round($amount * ($discPct / 100), 2);
        $taxable = round($amount - $lessDiscount, 2);
        $gstRate = $invoice->gst_rate !== null ? (float) $invoice->gst_rate : null;
        $igstAmount = $gstRate !== null ? round($taxable * ($gstRate / 100), 2) : null;
        $finalAmount = $gstRate !== null ? round($taxable + $igstAmount, 2) : $taxable;

        $options = $this->getLineItemDescOptions();
        $nameById = $this->lineItemDescIdToNameMap($options);

        $leatherNameOnly = $this->goodsDescriptionForOrderPo($po, $order);
        $leatherNameDisplay = $leatherNameOnly !== '' ? $leatherNameOnly : '—';
        $pkgQtyForLabel = 0;
        if ($invoice->package_id && $po) {
            $pkgQtyForLabel = $this->packageQtyForOrderPoInPackage((int) $invoice->package_id, (int) $po->id);
        }
        $usedOnInvoice = 0;
        if ($po) {
            $usedOnInvoice = $this->invoicedQtyForPoOnInvoice($invoice, (int) $po->id);
        }
        $remainingOnInvoice = max(0, $pkgQtyForLabel - $usedOnInvoice);
        $poDropdownLabel = $po && $order
            ? $this->buildInvoiceQcPoAutocompleteLabel($po, $order, $pkgQtyForLabel, $remainingOnInvoice)
            : '—';
        $poDescriptionOnInvoice = $this->invoiceDescriptionForOrderPo($po, $order);

        $goodsDesc = $this->invoiceLineGoodsDescriptionLine(
            $li,
            $po,
            $order,
            $nameById[$lineItemDescId] ?? '—'
        );

        return [
            'id' => $li->id,
            'ordersPoId' => $li->orders_po_id,
            'leatherName' => $leatherNameDisplay,
            'poDropdownLabel' => $poDropdownLabel,
            'poDescriptionOnInvoice' => $poDescriptionOnInvoice,
            'packageQty' => $pkgQtyForLabel,
            'poLabel' => $po
                ? sprintf(
                    'PS %s · Ref %s · Order %s',
                    $po->PsNumber ?? '-',
                    $po->RefNumber ?? '-',
                    $orderNo
                )
                : '—',
            'psNumber' => $po ? (trim((string) ($po->PsNumber ?? '')) !== '' ? trim((string) $po->PsNumber) : '—') : '—',
            'orderNumber' => $orderNo !== '-' ? $orderNo : '',
            'poTag' => $li->po_tag,
            'designDescription' => $li->design_name !== null && trim((string) $li->design_name) !== ''
                ? trim((string) $li->design_name)
                : '',
            'rate' => $rate,
            'discountPercent' => $discPct,
            'qty' => $qty,
            'isPairs' => (bool) ($li->is_pairs ?? false),
            'refArticle' => $po ? (trim((string) ($po->ClientModel ?? '')) ?: '—') : '—',
            'goodsDescription' => $goodsDesc,
            'amount' => $amount,
            'lessDiscountAmount' => $lessDiscount,
            'taxableValue' => $taxable,
            'igstRatePercent' => $gstRate,
            'igstAmount' => $igstAmount,
            'finalAmount' => $finalAmount,
        ];
    }

    /**
     * Produced (orderpos_status index 3) = QC completed in production workflow.
     */
    private function getProducedOrderPoStatusId(): int
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

    private function orderPoIsEligibleForInvoice(OrderPO $orderPO): bool
    {
        $producedId = (string) $this->getProducedOrderPoStatusId();
        if (trim((string) ($orderPO->Status ?? '')) === $producedId) {
            return true;
        }

        return $orderPO->qualityControls()->whereNotNull('QC_completion_date')->exists();
    }

    private function normalizeAgreementCode(?string $value): string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw !== '' ? $raw : Invoice::DEFAULT_AGREEMENT_CODE;
    }

    private function normalizeAgreementCode1(?string $value): string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw !== '' ? $raw : Invoice::DEFAULT_AGREEMENT_CODE_1;
    }

    private function normalizePortOfLoading(?string $value): string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw !== '' ? $raw : Invoice::DEFAULT_PORT_OF_LOADING;
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

    private function validateInvoicePayload(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'package_id' => [
                'nullable',
                'integer',
                Rule::exists('packages', 'id')->where(fn ($q) => $q->where('client_id', (int) $request->input('client_id'))),
            ],
            'invoice_date' => ['required', 'date'],
            'supply_date' => ['required', 'date'],
            'has_free_of_cost_items' => ['sometimes', 'boolean'],
            'freight_charges' => ['nullable', 'numeric', 'min:0'],
            'insurance_charges' => ['nullable', 'numeric', 'min:0'],
            'packing_charges' => ['nullable', 'numeric', 'min:0'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms_of_delivery' => ['nullable', 'string', Rule::in(Invoice::termsOfDeliveryOptions())],
            'terms_of_payment' => ['nullable', 'string', 'max:64'],
            'pre_carriage_by' => ['nullable', 'string', 'max:128'],
            'place_of_receipt' => ['nullable', 'string', 'max:128'],
            'vessel_flight_no' => ['nullable', 'string', 'max:128'],
            'port_of_loading' => ['nullable', 'string', 'max:128'],
            'type_of_invoice' => ['nullable', 'string', Rule::in(Invoice::typeOfInvoiceOptions())],
            'agreement_code_1' => ['nullable', 'string', 'max:64'],
            'agreement_code' => ['nullable', 'string', 'max:64'],
            'include_bottom_statement' => ['sometimes', 'boolean'],
        ]);
    }

    public function index(Request $request): Response
    {
        $paginated = Invoice::with(['client', 'package', 'lineGroups.lineItems.orderPO.order', 'focGroups.items'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        $lineItemDescOptions = $this->getLineItemDescOptions();
        $poTagOptions = $this->getPoTagOptions();
        $nameById = $this->lineItemDescIdToNameMap($lineItemDescOptions);
        $hsnByDescId = [];
        foreach ($lineItemDescOptions as $item) {
            if (isset($item['id'])) {
                $hsnByDescId[(int) $item['id']] = isset($item['hsn']) && $item['hsn'] !== ''
                    ? (string) $item['hsn']
                    : null;
            }
        }

        $companyHsn = SiteSetting::where('key', 'company.hsn')->value('value') ?? '9113 90 90';

        $rows = [];
        foreach ($paginated->items() as $invoice) {
            $lineGroups = [];
            foreach ($invoice->lineGroups as $lg) {
                $descId = $lg->line_item_desc_id;
                $lineItems = [];
                foreach ($lg->lineItems as $li) {
                    $lineItems[] = $this->lineItemToFrontendArray($li, $invoice, $descId);
                }
                $groupHsn = $lg->hsn !== null && trim((string) $lg->hsn) !== ''
                    ? trim((string) $lg->hsn)
                    : null;
                $lineGroups[] = [
                    'id' => $lg->id,
                    'lineItemDescId' => $descId,
                    'name' => $nameById[$descId] ?? '—',
                    'hsn' => $groupHsn ?? ($hsnByDescId[$descId] ?? null),
                    'highlighter' => $lg->highlighter !== null && $lg->highlighter !== '' ? (string) $lg->highlighter : '',
                    'lineItems' => $lineItems,
                ];
            }
            $rows[] = [
                'id' => $invoice->id,
                'invoiceNo' => $invoice->formattedInvoiceNumber(),
                'totalGroupings' => $invoice->lineGroups->count(),
                'status' => $this->statusLabel($invoice->status),
                'statusValue' => $invoice->status,
                'invoiceDate' => $invoice->invoice_date?->format('Y-m-d'),
                'supplyDate' => $invoice->supply_date?->format('Y-m-d'),
                'clientId' => $invoice->client_id,
                'typeOfInvoice' => $invoice->type_of_invoice ?: Invoice::DEFAULT_TYPE_OF_INVOICE,
                'clientName' => $invoice->client?->client_name ?? '-',
                'clientCountry' => $invoice->client?->country,
                'packageId' => $invoice->package_id,
                'packageTotalBoxes' => $invoice->package !== null
                    ? (int) ($invoice->package->total_boxes ?? 0)
                    : null,
                'hasFreeOfCostItems' => $invoice->has_free_of_cost_items,
                'focGroups' => $invoice->focGroups->map(fn (InvoiceFocGroup $fg) => [
                    'id' => $fg->id,
                    'highlighter' => $fg->highlighter !== null && $fg->highlighter !== ''
                        ? (string) $fg->highlighter
                        : '',
                    'itemCount' => $fg->items->count(),
                    'items' => $fg->items->map(fn (InvoiceFocItem $fi) => [
                        'id' => $fi->id,
                        'code' => $fi->code ?? '',
                        'description' => $fi->description ?? '',
                        'beNo' => $fi->be_no ?? '',
                        'quantity' => (float) $fi->quantity,
                        'units' => $fi->units ?? '',
                        'rate' => (float) $fi->rate,
                    ])->values()->all(),
                ])->values()->all(),
                'freightCharges' => $invoice->freight_charges !== null ? (float) $invoice->freight_charges : null,
                'insuranceCharges' => $invoice->insurance_charges !== null ? (float) $invoice->insurance_charges : null,
                'packingCharges' => $invoice->packing_charges !== null ? (float) $invoice->packing_charges : null,
                'gstRate' => $invoice->gst_rate !== null ? (float) $invoice->gst_rate : null,
                'termsOfDelivery' => $invoice->terms_of_delivery ?: Invoice::DEFAULT_TERMS_OF_DELIVERY,
                'termsOfPayment' => $invoice->terms_of_payment ?: Invoice::DEFAULT_TERMS_OF_PAYMENT,
                'preCarriageBy' => $invoice->pre_carriage_by ?? '',
                'placeOfReceipt' => $invoice->place_of_receipt ?? '',
                'vesselFlightNo' => $invoice->vessel_flight_no ?? '',
                'portOfLoading' => $invoice->port_of_loading ?: Invoice::DEFAULT_PORT_OF_LOADING,
                'agreementCode1' => $invoice->agreement_code_1 ?: Invoice::DEFAULT_AGREEMENT_CODE_1,
                'agreementCode' => $invoice->agreement_code ?: Invoice::DEFAULT_AGREEMENT_CODE,
                'includeBottomStatement' => (bool) ($invoice->include_bottom_statement ?? true),
                'currencyCode' => $invoice->client?->currency ?? 'EUR',
                'currencySymbol' => currency_symbol($invoice->client?->currency ?? 'EUR'),
                'lineGroups' => $lineGroups,
            ];
        }

        return Inertia::render('Admin/InvoiceManagement/Index', [
            'invoices' => [
                'data' => $rows,
                'links' => $paginated->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
            ],
            'statusOptions' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'complete', 'label' => 'Complete'],
                ['value' => 'sent_to_client', 'label' => 'Sent to Client'],
            ],
            'lineItemDescOptions' => $lineItemDescOptions,
            'poTagOptions' => $poTagOptions,
            'companyHsn' => $companyHsn,
            'termsOfDeliveryOptions' => Invoice::termsOfDeliveryOptions(),
            'defaultTermsOfDelivery' => Invoice::DEFAULT_TERMS_OF_DELIVERY,
            'defaultTermsOfPayment' => Invoice::DEFAULT_TERMS_OF_PAYMENT,
            'defaultAgreementCode' => Invoice::DEFAULT_AGREEMENT_CODE,
            'defaultAgreementCode1' => Invoice::DEFAULT_AGREEMENT_CODE_1,
            'typeOfInvoiceOptions' => Invoice::typeOfInvoiceOptions(),
            'defaultTypeOfInvoice' => Invoice::DEFAULT_TYPE_OF_INVOICE,
            'defaultPortOfLoading' => Invoice::DEFAULT_PORT_OF_LOADING,
        ]);
    }

    public function storeLineItemDesc(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'line_item_desc')->first();
        $items = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            }
        }

        $name = trim($validated['name']);
        if (json_setting_items_name_is_duplicate($items, $name, null)) {
            return response()->json([
                'message' => 'This name already exists.',
                'errors' => ['name' => ['This name already exists.']],
            ], 422);
        }

        $maxId = 0;
        foreach ($items as $item) {
            if (isset($item['id']) && $item['id'] > $maxId) {
                $maxId = $item['id'];
            }
        }
        $newId = $maxId + 1;

        $items[] = [
            'id' => $newId,
            'name' => $name,
        ];

        SiteSetting::updateOrCreate(
            ['key' => 'line_item_desc'],
            ['value' => json_encode($items), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return response()->json([
            'id' => $newId,
            'name' => $name,
        ]);
    }

    public function qcCompletedPosForLineGroup(Request $request, InvoiceLineGroup $invoice_line_group): JsonResponse
    {
        $invoice_line_group->loadMissing('invoice');
        $invoice = $invoice_line_group->invoice;
        if (! $invoice) {
            return response()->json(['message' => 'Invalid line group.'], 404);
        }

        if (! $invoice->package_id) {
            return response()->json(['requiresPackage' => true, 'items' => []]);
        }

        $package = Package::with(['boxes.orderPOs'])->find($invoice->package_id);
        if (! $package) {
            return response()->json(['requiresPackage' => true, 'items' => []]);
        }

        $poQtyInPackage = [];
        foreach ($package->boxes as $box) {
            foreach ($box->orderPOs as $po) {
                $pid = (int) $po->id;
                $q = (int) ($po->pivot->qty ?? 0);
                if (! isset($poQtyInPackage[$pid])) {
                    $poQtyInPackage[$pid] = 0;
                }
                $poQtyInPackage[$pid] += $q;
            }
        }

        $packagePoIds = array_keys($poQtyInPackage);
        if ($packagePoIds === []) {
            return response()->json(['packageEmpty' => true, 'items' => []]);
        }

        $excludeLineItemId = $request->query('exclude_invoice_line_item_id');
        $excludeLineItemId = $excludeLineItemId !== null && $excludeLineItemId !== '' ? (int) $excludeLineItemId : null;

        $invoicedByPo = $this->invoicedQtyByPoForInvoice($invoice, $packagePoIds, $excludeLineItemId);

        $clientId = (int) $invoice->client_id;
        $producedId = (string) $this->getProducedOrderPoStatusId();

        $query = OrderPO::query()
            ->with(['order:OrderId,ClientId,OrderNumber'])
            ->whereIn('id', $packagePoIds)
            ->whereHas('order', fn ($q) => $q->where('ClientId', $clientId))
            ->where(function ($q) use ($producedId) {
                $q->where('Status', $producedId)
                    ->orWhereHas('qualityControls', fn ($qc) => $qc->whereNotNull('QC_completion_date'));
            });

        $pos = $query->orderByDesc('id')->limit(200)->get();

        $list = [];
        foreach ($pos as $po) {
            $pkgQty = $poQtyInPackage[(int) $po->id] ?? 0;
            $used = $invoicedByPo[(int) $po->id] ?? 0;
            $remaining = $pkgQty - $used;
            if ($remaining < 1) {
                continue;
            }
            $order = $po->order;
            $descriptionOnInvoice = $this->invoiceDescriptionForOrderPo($po, $order);
            $designDescription = $this->designDescriptionForOrderPo($po, $order);
            $list[] = [
                'id' => $po->id,
                'label' => $this->buildInvoiceQcPoAutocompleteLabel($po, $order, $pkgQty, $remaining),
                'descriptionOnInvoice' => $descriptionOnInvoice !== '' ? $descriptionOnInvoice : $designDescription,
                'designDescription' => $designDescription,
                'orderNumber' => trim((string) ($order?->OrderNumber ?? '')),
                'packageQty' => $pkgQty,
                'remainingQty' => $remaining,
                'psNumber' => $po->PsNumber ?? '—',
                'rate' => $po->Rate !== null ? (float) $po->Rate : null,
            ];
        }

        return response()->json([
            'items' => array_values($list),
            'requiresPackage' => false,
        ]);
    }

    public function storeLineItem(Request $request, InvoiceLineGroup $invoice_line_group): JsonResponse
    {
        $validated = $request->validate([
            'orders_po_id' => ['required', 'integer', 'exists:ordersPO,id'],
            'po_tag' => ['nullable', 'string', 'max:255'],
            'design_name' => ['nullable', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1'],
            'is_pairs' => ['sometimes', 'boolean'],
            'rate' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $invoice_line_group->load('invoice');
        $invoice = $invoice_line_group->invoice;
        if (! $invoice) {
            return response()->json(['message' => 'Invalid line group.'], 404);
        }

        if (! $invoice->package_id) {
            return response()->json(['message' => 'Invoice must have a package selected to add line items.'], 422);
        }

        $po = OrderPO::with('order')->find($validated['orders_po_id']);
        if (! $po || ! $po->order || (int) $po->order->ClientId !== (int) $invoice->client_id) {
            return response()->json(['message' => 'This PO does not belong to the invoice client.'], 422);
        }

        if (! $this->orderPoIsEligibleForInvoice($po)) {
            return response()->json(['message' => 'Only QC-completed POs can be added.'], 422);
        }

        if (! $this->orderPoIsInInvoicePackage($po, $invoice)) {
            return response()->json(['message' => 'This PO is not part of the invoice package.'], 422);
        }

        $pkgQty = $this->packageQtyForOrderPoInPackage((int) $invoice->package_id, (int) $po->id);
        $alreadyInvoiced = $this->invoicedQtyForPoOnInvoice($invoice, (int) $po->id);
        $newQty = (int) $validated['quantity'];
        if ($alreadyInvoiced + $newQty > $pkgQty) {
            return response()->json([
                'message' => 'Total invoiced quantity for this PO cannot exceed the quantity packed for this package.',
                'errors' => [
                    'quantity' => ['Quantity exceeds remaining packaged amount for this PO on this invoice.'],
                ],
            ], 422);
        }

        $designRaw = isset($validated['design_name']) ? trim((string) $validated['design_name']) : '';

        $li = InvoiceLineItem::create([
            'invoice_line_group_id' => $invoice_line_group->id,
            'orders_po_id' => $po->id,
            'po_tag' => $validated['po_tag'] ?? null,
            'design_name' => $designRaw !== '' ? $designRaw : null,
            'quantity' => (int) $validated['quantity'],
            'is_pairs' => $request->boolean('is_pairs'),
            'rate' => $validated['rate'],
            'discount_percent' => $validated['discount_percent'] ?? 0,
        ]);
        $li->load(['orderPO.order']);

        return response()->json([
            'success' => true,
            'lineItem' => $this->lineItemToFrontendArray($li, $invoice, (int) $invoice_line_group->line_item_desc_id),
        ]);
    }

    public function updateLineItem(Request $request, InvoiceLineItem $invoice_line_item): JsonResponse
    {
        $validated = $request->validate([
            'design_name' => ['nullable', 'string', 'max:500'],
            'po_tag' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'is_pairs' => ['sometimes', 'boolean'],
            'rate' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $invoice_line_item->load(['invoiceLineGroup.invoice', 'orderPO']);
        $group = $invoice_line_item->invoiceLineGroup;
        if (! $group) {
            return response()->json(['message' => 'Invalid line item.'], 404);
        }

        $inv = $group->invoice;
        $po = $invoice_line_item->orderPO;
        if ($inv && $inv->package_id && $po) {
            $pkgQty = $this->packageQtyForOrderPoInPackage((int) $inv->package_id, (int) $po->id);
            $othersInvoiced = $this->invoicedQtyForPoOnInvoice($inv, (int) $po->id, (int) $invoice_line_item->id);
            $newQty = (int) $validated['quantity'];
            if ($othersInvoiced + $newQty > $pkgQty) {
                return response()->json([
                    'message' => 'Total invoiced quantity for this PO cannot exceed the quantity packed for this package.',
                    'errors' => [
                        'quantity' => ['Quantity exceeds remaining packaged amount for this PO on this invoice.'],
                    ],
                ], 422);
            }
        }

        $designRaw = isset($validated['design_name']) ? trim((string) $validated['design_name']) : '';

        $invoice_line_item->update([
            'design_name' => $designRaw !== '' ? $designRaw : null,
            'po_tag' => $validated['po_tag'] ?? null,
            'quantity' => (int) $validated['quantity'],
            'is_pairs' => $request->boolean('is_pairs'),
            'rate' => $validated['rate'],
            'discount_percent' => $validated['discount_percent'] ?? 0,
        ]);

        $invoice_line_item->refresh();
        $invoice_line_item->load(['orderPO.order']);
        $inv = $group->invoice;
        if (! $inv) {
            return response()->json(['success' => true]);
        }

        return response()->json([
            'success' => true,
            'lineItem' => $this->lineItemToFrontendArray(
                $invoice_line_item,
                $inv,
                (int) $group->line_item_desc_id
            ),
        ]);
    }

    public function destroyLineItem(InvoiceLineItem $invoice_line_item): JsonResponse
    {
        $invoice_line_item->delete();

        return response()->json(['success' => true]);
    }

    public function storeLineGroup(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'line_item_desc_id' => ['required', 'integer'],
            'hsn' => ['nullable', 'string', 'max:64'],
        ]);

        $options = $this->getLineItemDescOptions();
        $validIds = array_map(fn ($o) => (int) $o['id'], $options);
        if (! in_array((int) $validated['line_item_desc_id'], $validIds, true)) {
            return response()->json(['message' => 'Invalid line item description.'], 422);
        }

        $descId = (int) $validated['line_item_desc_id'];
        $hsn = isset($validated['hsn']) ? trim((string) $validated['hsn']) : '';

        $invoice->lineGroups()->create([
            'line_item_desc_id' => $descId,
            'hsn' => $hsn === '' ? null : $hsn,
        ]);
        $invoice->update(['total_groupings' => $invoice->lineGroups()->count()]);

        return response()->json(['success' => true]);
    }

    public function updateLineGroup(Request $request, InvoiceLineGroup $invoice_line_group): JsonResponse
    {
        $validated = $request->validate([
            'line_item_desc_id' => ['required', 'integer'],
            'hsn' => ['nullable', 'string', 'max:64'],
        ]);

        $options = $this->getLineItemDescOptions();
        $validIds = array_map(fn ($o) => (int) $o['id'], $options);
        if (! in_array((int) $validated['line_item_desc_id'], $validIds, true)) {
            return response()->json(['message' => 'Invalid line item description.'], 422);
        }

        $descId = (int) $validated['line_item_desc_id'];
        $hsn = isset($validated['hsn']) ? trim((string) $validated['hsn']) : '';

        $invoice_line_group->load('invoice');
        $invoice = $invoice_line_group->invoice;
        if (! $invoice) {
            return response()->json(['message' => 'Invalid line group.'], 404);
        }

        $invoice_line_group->update([
            'line_item_desc_id' => $descId,
            'hsn' => $hsn === '' ? null : $hsn,
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyLineGroup(InvoiceLineGroup $invoice_line_group): JsonResponse
    {
        $invoice = $invoice_line_group->invoice;
        $invoice_line_group->delete();
        if ($invoice) {
            $invoice->update(['total_groupings' => $invoice->lineGroups()->count()]);
        }

        return response()->json(['success' => true]);
    }

    public function updateLineGroupHighlighter(Request $request, InvoiceLineGroup $invoice_line_group): JsonResponse
    {
        $validated = $request->validate([
            'highlighter' => ['nullable', 'string', 'max:100'],
        ]);

        $raw = isset($validated['highlighter']) ? trim((string) $validated['highlighter']) : '';
        $invoice_line_group->update([
            'highlighter' => $raw === '' ? null : mb_substr($raw, 0, 100),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{code: ?string, description: ?string, be_no: ?string, quantity: float, units: ?string, rate: float}>
     */
    private function normalizeFocItemsFromRequest(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['code'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $beNo = trim((string) ($row['be_no'] ?? ''));
            $units = trim((string) ($row['units'] ?? ''));
            $qtyRaw = $row['quantity'] ?? null;
            $rateRaw = $row['rate'] ?? null;
            if ($code === '' && $description === '' && $beNo === '' && $units === ''
                && ($qtyRaw === null || $qtyRaw === '')
                && ($rateRaw === null || $rateRaw === '')) {
                continue;
            }
            $out[] = [
                'code' => $code !== '' ? $code : null,
                'description' => $description !== '' ? $description : null,
                'be_no' => $beNo !== '' ? $beNo : null,
                'quantity' => $qtyRaw === null || $qtyRaw === '' ? 0.0 : max(0, (float) $qtyRaw),
                'units' => $units !== '' ? $units : null,
                'rate' => $rateRaw === null || $rateRaw === '' ? 0.0 : (float) $rateRaw,
            ];
        }

        return $out;
    }

    public function storeFocGroup(Invoice $invoice): JsonResponse
    {
        if (! $invoice->has_free_of_cost_items) {
            return response()->json([
                'message' => 'This invoice is not marked as having free of cost items.',
            ], 422);
        }

        $maxSort = (int) $invoice->focGroups()->max('sort_order');
        $group = InvoiceFocGroup::create([
            'invoice_id' => $invoice->id,
            'highlighter' => null,
            'sort_order' => $maxSort + 1,
        ]);

        return response()->json([
            'id' => $group->id,
            'highlighter' => '',
            'itemCount' => 0,
            'items' => [],
        ], 201);
    }

    public function destroyFocGroup(InvoiceFocGroup $invoice_foc_group): JsonResponse
    {
        $invoice_foc_group->delete();

        return response()->json(['success' => true]);
    }

    public function syncFocGroupItems(Request $request, InvoiceFocGroup $invoice_foc_group): JsonResponse
    {
        $invoice_foc_group->loadMissing('invoice');
        $invoice = $invoice_foc_group->invoice;
        if (! $invoice || ! $invoice->has_free_of_cost_items) {
            return response()->json([
                'message' => 'This invoice is not marked as having free of cost items.',
            ], 422);
        }

        $hlRaw = trim((string) ($request->input('foc_highlighter', '')));
        $focHighlighter = $hlRaw === '' ? null : mb_substr($hlRaw, 0, 100);

        $normalized = $this->normalizeFocItemsFromRequest($request->input('items', []));
        $request->merge(['items' => $normalized]);

        $validated = $request->validate([
            'items' => ['array'],
            'items.*.code' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.be_no' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.units' => ['nullable', 'string', 'max:255'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($invoice_foc_group, $validated, $focHighlighter): void {
            $invoice_foc_group->update(['highlighter' => $focHighlighter]);
            $invoice_foc_group->items()->delete();
            foreach ($validated['items'] as $i => $item) {
                InvoiceFocItem::create([
                    'invoice_foc_group_id' => $invoice_foc_group->id,
                    'code' => $item['code'] ?? null,
                    'description' => $item['description'] ?? null,
                    'be_no' => $item['be_no'] ?? null,
                    'quantity' => (float) $item['quantity'],
                    'units' => $item['units'] ?? null,
                    'rate' => $item['rate'],
                    'sort_order' => $i,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function searchClients(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $completedStatuses = [self::PACKAGING_STATUS_COMPLETED, (string) self::PACKAGING_STATUS_COMPLETED];
        $reservedPackageIds = $this->packageIdsReservedByInvoices(null);

        $eligiblePackages = Package::query()
            ->whereIn('status', $completedStatuses);
        if ($reservedPackageIds !== []) {
            $eligiblePackages->whereNotIn('id', $reservedPackageIds);
        }

        $eligibleClientIds = $eligiblePackages->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($eligibleClientIds === []) {
            return response()->json([]);
        }

        $clientsQuery = Client::query()
            ->whereIn('id', $eligibleClientIds)
            ->orderBy('client_name');

        if ($q !== '') {
            $clientsQuery->where('client_name', 'like', '%'.$q.'%');
        }

        $limit = $q !== '' ? 100 : 1000;
        $clients = $clientsQuery->limit($limit)->get(['id', 'client_name', 'currency']);

        if ($clients->isEmpty()) {
            return response()->json([]);
        }

        $clientIds = $clients->pluck('id')->all();
        $pkgRows = Package::query()
            ->whereIn('client_id', $clientIds)
            ->whereIn('status', $completedStatuses);
        if ($reservedPackageIds !== []) {
            $pkgRows->whereNotIn('id', $reservedPackageIds);
        }
        $pkgRows = $pkgRows->orderByDesc('id')
            ->get(['client_id', 'total_boxes']);
        $boxesByClient = [];
        foreach ($pkgRows as $p) {
            $cid = (int) $p->client_id;
            if (! isset($boxesByClient[$cid])) {
                $boxesByClient[$cid] = (int) ($p->total_boxes ?? 0);
            }
        }

        $list = $clients->map(function ($c) use ($boxesByClient) {
            $cid = (int) $c->id;
            $boxes = $boxesByClient[$cid] ?? 0;

            return [
                'id' => $c->id,
                'name' => sprintf('%s | Package with %d Boxes', $c->client_name, $boxes),
                'currency' => $c->currency,
                'currencySymbol' => currency_symbol($c->currency),
            ];
        })->values();

        return response()->json($list);
    }

    public function packagesForClient(Request $request): JsonResponse
    {
        $clientId = $request->integer('client_id');
        if (! $clientId) {
            return response()->json([]);
        }

        $completedStatuses = [self::PACKAGING_STATUS_COMPLETED, (string) self::PACKAGING_STATUS_COMPLETED];
        $reservedPackageIds = $this->packageIdsReservedByInvoices(
            $request->filled('invoice_id') ? $request->integer('invoice_id') : null
        );

        $packages = Package::where('client_id', $clientId)
            ->whereIn('status', $completedStatuses);
        if ($reservedPackageIds !== []) {
            $packages->whereNotIn('id', $reservedPackageIds);
        }
        $packages = $packages->orderBy('id', 'desc')
            ->get(['id', 'total_boxes'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => 'Package #'.$p->id.' — '.($p->total_boxes ?? 0).' boxes',
            ]);

        return response()->json($packages);
    }

    public function store(Request $request)
    {
        $validated = $this->validateInvoicePayload($request);

        $invoiceDate = \Carbon\Carbon::parse($validated['invoice_date']);
        $fiscalYearLabel = Invoice::fiscalYearLabelFromDate($invoiceDate);

        $invoice = Invoice::create([
            'client_id' => (int) $validated['client_id'],
            'type_of_invoice' => $validated['type_of_invoice'] ?? Invoice::DEFAULT_TYPE_OF_INVOICE,
            'package_id' => $validated['package_id'] ?? null,
            'total_groupings' => 0,
            'status' => 'draft',
            'invoice_date' => $validated['invoice_date'],
            'supply_date' => $validated['supply_date'],
            'has_free_of_cost_items' => $request->boolean('has_free_of_cost_items'),
            'freight_charges' => $validated['freight_charges'] ?? null,
            'insurance_charges' => $validated['insurance_charges'] ?? null,
            'packing_charges' => $validated['packing_charges'] ?? null,
            'gst_rate' => $validated['gst_rate'] ?? null,
            'terms_of_delivery' => $validated['terms_of_delivery'] ?? Invoice::DEFAULT_TERMS_OF_DELIVERY,
            'terms_of_payment' => $validated['terms_of_payment'] ?? Invoice::DEFAULT_TERMS_OF_PAYMENT,
            'pre_carriage_by' => $this->nullableTrimmedString($validated['pre_carriage_by'] ?? null),
            'place_of_receipt' => $this->nullableTrimmedString($validated['place_of_receipt'] ?? null),
            'vessel_flight_no' => $this->nullableTrimmedString($validated['vessel_flight_no'] ?? null),
            'port_of_loading' => $this->normalizePortOfLoading($validated['port_of_loading'] ?? null),
            'agreement_code_1' => $this->normalizeAgreementCode1($validated['agreement_code_1'] ?? null),
            'agreement_code' => $this->normalizeAgreementCode($validated['agreement_code'] ?? null),
            'include_bottom_statement' => $request->boolean('include_bottom_statement', true),
            'fiscal_year_label' => $fiscalYearLabel,
        ]);

        return redirect()
            ->route('admin.invoice-management.index')
            ->with('success', 'Invoice created successfully.');
    }

    public function updateStatus(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::STATUS_VALUES)],
        ]);

        $invoice->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'status' => $invoice->status,
            'statusLabel' => $this->statusLabel($invoice->status),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $this->validateInvoicePayload($request);

        $invoiceDate = \Carbon\Carbon::parse($validated['invoice_date']);
        $fiscalYearLabel = Invoice::fiscalYearLabelFromDate($invoiceDate);

        $invoice->update([
            'client_id' => (int) $validated['client_id'],
            'type_of_invoice' => $validated['type_of_invoice'] ?? Invoice::DEFAULT_TYPE_OF_INVOICE,
            'package_id' => $validated['package_id'] ?? null,
            'invoice_date' => $validated['invoice_date'],
            'supply_date' => $validated['supply_date'],
            'has_free_of_cost_items' => $request->boolean('has_free_of_cost_items'),
            'freight_charges' => $validated['freight_charges'] ?? null,
            'insurance_charges' => $validated['insurance_charges'] ?? null,
            'packing_charges' => $validated['packing_charges'] ?? null,
            'gst_rate' => $validated['gst_rate'] ?? null,
            'terms_of_delivery' => $validated['terms_of_delivery'] ?? Invoice::DEFAULT_TERMS_OF_DELIVERY,
            'terms_of_payment' => $validated['terms_of_payment'] ?? Invoice::DEFAULT_TERMS_OF_PAYMENT,
            'pre_carriage_by' => $this->nullableTrimmedString($validated['pre_carriage_by'] ?? null),
            'place_of_receipt' => $this->nullableTrimmedString($validated['place_of_receipt'] ?? null),
            'vessel_flight_no' => $this->nullableTrimmedString($validated['vessel_flight_no'] ?? null),
            'port_of_loading' => $this->normalizePortOfLoading($validated['port_of_loading'] ?? null),
            'agreement_code_1' => $this->normalizeAgreementCode1($validated['agreement_code_1'] ?? null),
            'agreement_code' => $this->normalizeAgreementCode($validated['agreement_code'] ?? null),
            'include_bottom_statement' => $request->boolean('include_bottom_statement', true),
            'fiscal_year_label' => $fiscalYearLabel,
        ]);

        return redirect()
            ->route('admin.invoice-management.index')
            ->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return redirect()
            ->route('admin.invoice-management.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Export invoice as PDF (main export invoice page). Use ?preview=1 for HTML preview.
     */
    public function pdf(Request $request, Invoice $invoice)
    {
        try {
            $data = $this->buildExportInvoicePdfData($invoice);
            $html = view('pdf.invoice-export', $data)->render();

            if ($request->boolean('preview')) {
                return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
            }

            $clientName = $invoice->client?->client_name ?? 'invoice';
            $safeClientName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $clientName);
            $filename = 'Export-Invoice-'.$safeClientName.'-'.$invoice->id.'.pdf';

            $pdf = PDF::loadHTML($html)
                ->setOption('margin-top', 10)
                ->setOption('margin-bottom', 10)
                ->setOption('margin-left', 10)
                ->setOption('margin-right', 10)
                ->setOption('encoding', 'UTF-8')
                ->setOption('no-outline', true);

            return $pdf->inline($filename);
        } catch (\Throwable $e) {
            Log::error('Error generating export invoice PDF: '.$e->getMessage(), [
                'invoice_id' => $invoice->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('admin.invoice-management.index')
                ->with('error', 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExportInvoicePdfData(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'client.buyer',
            'lineGroups.lineItems.orderPO.order',
            'package.boxes.orderPOs',
            'package.packagePallets',
            'focGroups.items',
        ]);

        $client = $invoice->client;
        $buyer = $client?->buyer;

        $lineItemDescOptions = $this->getLineItemDescOptions();
        $nameById = $this->lineItemDescIdToNameMap($lineItemDescOptions);
        $hsnByDescId = [];
        foreach ($lineItemDescOptions as $item) {
            if (isset($item['id'])) {
                $hsnByDescId[(int) $item['id']] = isset($item['hsn']) && $item['hsn'] !== ''
                    ? (string) $item['hsn']
                    : null;
            }
        }

        $companyHsn = SiteSetting::where('key', 'company.hsn')->value('value') ?? '9113 90 90';

        $companySettings = SiteSetting::whereIn('key', [
            'company.name',
            'company.address',
            'company.exporter_ref',
            'company.iec_no',
            'company.pan_no',
            'company.terms_of_delivery_and_payments',
            'company.features',
            'company.export_statement',
        ])->pluck('value', 'key')->toArray();

        $companyName = $companySettings['company.name'] ?? 'SIBRAMA NEWAGE';
        $exporterRef = $companySettings['company.exporter_ref'] ?? 'DS - 011745';
        $iecNo = $companySettings['company.iec_no'] ?? '4101000042';
        $panNo = $companySettings['company.pan_no'] ?? 'AAEFS5453M';
        $companyAddress = $companySettings['company.address'] ?? '';
        $paymentToDetails = $companySettings['company.terms_of_delivery_and_payments'] ?? '';
        $companyFeatures = $companySettings['company.features'] ?? '';
        $exportStatement = $companySettings['company.export_statement'] ?? '';

        $clientName = $client?->client_name ?? '-';
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

        $attention = trim((string) ($client?->attention ?? ''));
        $countryOfFinalDestination = $client?->country ?? '';
        $portOfLoading = trim((string) ($invoice->port_of_loading ?? ''));
        if ($portOfLoading === '') {
            $portOfLoading = Invoice::DEFAULT_PORT_OF_LOADING;
        }
        $portOfDischarge = $client?->port_of_discharge ?: 'PARIS FRANCE';
        $finalDestination = $countryOfFinalDestination;
        $vesselFlightNo = trim((string) ($invoice->vessel_flight_no ?? ''));
        $preCarriageBy = trim((string) ($invoice->pre_carriage_by ?? ''));
        $placeOfReceipt = trim((string) ($invoice->place_of_receipt ?? ''));
        $countryOfOrigin = 'INDIA';

        $currencyCode = $client?->currency ?? 'EUR';
        $currencySymbol = currency_symbol($currencyCode);
        $currencySubheader = $currencyCode.' ('.$currencySymbol.')';

        $invoiceNumber = $invoice->formattedInvoiceNumber();
        $invoiceDateDisplay = $invoice->invoice_date?->format('d.m.Y') ?? '';
        $supplyDateDisplay = $invoice->supply_date?->format('d.m.Y') ?? '';

        $orderSnippets = [];
        $seenOrderIds = [];
        foreach ($invoice->lineGroups as $lg) {
            foreach ($lg->lineItems as $li) {
                $po = $li->orderPO;
                $order = $po?->order;
                if (! $order) {
                    continue;
                }
                $oid = (int) $order->OrderId;
                if (isset($seenOrderIds[$oid])) {
                    continue;
                }
                $seenOrderIds[$oid] = true;
                $orderNo = $order->OrderNumber ?? '';
                $orderDate = $order->DeliveryDate
                    ? $order->DeliveryDate->format('d.m.y')
                    : ($order->created_at ? $order->created_at->format('d.m.y') : '');
                if ($orderNo !== '') {
                    $orderSnippets[] = $orderDate !== '' ? $orderNo.' DT. '.$orderDate : $orderNo;
                }
            }
        }
        $buyersOrderNoAndDate = implode(', ', $orderSnippets);

        $printRows = [];
        $groupSr = 0;
        $sumAmount = 0.0;
        $sumLessDiscount = 0.0;
        $sumTaxable = 0.0;
        $sumIgst = 0.0;
        $sumFinal = 0.0;
        $sumQty = 0;
        $sumQtyNos = 0;
        $sumQtyPairs = 0;
        $hasQtyPairs = false;
        $hasQtyNos = false;
        $lineItemCount = 0;

        foreach ($invoice->lineGroups as $lg) {
            $descId = $lg->line_item_desc_id;
            $groupName = $nameById[$descId] ?? '—';
            $groupHsn = $lg->hsn !== null && trim((string) $lg->hsn) !== ''
                ? trim((string) $lg->hsn)
                : null;
            $hsn = $groupHsn ?? ($hsnByDescId[$descId] ?? null);
            $hsnDisplay = ($hsn !== null && trim($hsn) !== '') ? trim($hsn) : trim((string) $companyHsn);

            $groupSr++;
            $printRows[] = [
                'type' => 'group',
                'groupSrNo' => $groupSr,
                'hsn' => $hsnDisplay,
                'title' => $groupName,
            ];

            foreach ($lg->lineItems as $li) {
                $po = $li->orderPO;
                $order = $po?->order;
                $qtyFromPo = max(0, (int) ($po->QtyByOrder ?? 0));
                $qty = $li->quantity !== null ? max(0, (int) $li->quantity) : $qtyFromPo;
                $rate = (float) $li->rate;
                $amount = round($qty * $rate, 2);
                $discPct = (float) $li->discount_percent;
                $lessDiscount = round($amount * ($discPct / 100), 2);
                $taxable = round($amount - $lessDiscount, 2);
                $gstRate = $invoice->gst_rate !== null ? (float) $invoice->gst_rate : null;
                $igstAmount = $gstRate !== null ? round($taxable * ($gstRate / 100), 2) : null;
                $finalAmount = $gstRate !== null ? round($taxable + $igstAmount, 2) : $taxable;

                $goodsDesc = $this->invoiceLineGoodsDescriptionLine($li, $po, $order, $groupName);
                $poTag = trim((string) ($li->po_tag ?? ''));

                $refArticle = $po ? (trim((string) ($po->ClientModel ?? '')) ?: '—') : '—';

                $sumAmount += $amount;
                $sumLessDiscount += $lessDiscount;
                $sumTaxable += $taxable;
                if ($igstAmount !== null) {
                    $sumIgst += $igstAmount;
                }
                $sumFinal += $finalAmount;
                $sumQty += $qty;
                $lineItemCount++;
                $isPairs = (bool) ($li->is_pairs ?? false);
                if ($isPairs) {
                    $hasQtyPairs = true;
                    $sumQtyPairs += $qty;
                } else {
                    $hasQtyNos = true;
                    $sumQtyNos += $qty;
                }

                $printRows[] = [
                    'type' => 'line',
                    'description' => $goodsDesc,
                    'poTag' => $poTag !== '' ? $poTag : null,
                    'refArticle' => $refArticle,
                    'qty' => $qty,
                    'isPairs' => $isPairs,
                    'rate' => $rate,
                    'amount' => $amount,
                    'lessDiscount' => $lessDiscount,
                    'taxableValue' => $taxable,
                    'igstRatePercent' => $gstRate,
                    'igstAmount' => $igstAmount,
                    'finalAmount' => $finalAmount,
                ];
            }

            $highlighterText = trim((string) ($lg->highlighter ?? ''));
            if ($highlighterText !== '') {
                $printRows[] = [
                    'type' => 'highlighter',
                    'text' => $highlighterText,
                ];
            }
        }

        $willShowFocPage = $invoice->has_free_of_cost_items
            && $invoice->focGroups->contains(fn (InvoiceFocGroup $g) => $g->items->isNotEmpty());

        $middleSectionContentRows = count($printRows);
        $rowsPerPage = self::INVOICE_EXPORT_PDF_ROWS_PER_PAGE;
        $printRowChunks = $middleSectionContentRows === 0
            ? [[]]
            : array_values(array_chunk($printRows, $rowsPerPage));
        $printRowChunks = $this->mergeInvoicePrintChunksWithoutLineItems($printRowChunks);
        $invoicePdfPageCount = count($printRowChunks);
        $minLineBodyMiddle = self::INVOICE_EXPORT_PDF_MIN_LINE_BODY_ROWS_PER_PAGE;
        $minLineBodyLast = self::INVOICE_EXPORT_PDF_MIN_LINE_BODY_ROWS_LAST_PAGE;
        $invoicePdfChunks = [];
        $totalPaddedLineRows = 0;
        $runningTotals = $this->emptyInvoicePageTotals();
        foreach ($printRowChunks as $i => $chunkRows) {
            $pageNum = $i + 1;
            $isLastChunk = $i === $invoicePdfPageCount - 1;
            $isDocumentLastPage = $isLastChunk && ! $willShowFocPage;
            $hasBroughtForward = $i > 0;
            $targetMin = $isDocumentLastPage ? $minLineBodyLast : $minLineBodyMiddle;
            // When invoice spans multiple pages (excluding the separate FOC page),
            // add extra padding to all pages except the last one to avoid bottom whitespace.
            if (! $isLastChunk) {
                $targetMin += 10;
            }
            if ($hasBroughtForward) {
                $targetMin = max(0, $targetMin - 1);
            }
            $paddedRows = array_values($chunkRows);
            while (count($paddedRows) < $targetMin) {
                $paddedRows[] = ['type' => 'blank'];
            }
            $broughtForward = $hasBroughtForward ? array_merge($runningTotals) : null;
            foreach ($paddedRows as $row) {
                if (($row['type'] ?? '') === 'line') {
                    $this->addInvoiceLineToPageTotals($runningTotals, $row);
                }
            }
            $totalPaddedLineRows += count($paddedRows) + ($hasBroughtForward ? 1 : 0);
            $invoicePdfChunks[] = [
                'rows' => $paddedRows,
                'isLast' => $isLastChunk,
                'isDocumentLast' => $isDocumentLastPage,
                'pageBreakBefore' => $i > 0,
                'pageLabel' => sprintf('PAGE - %d/%d', $pageNum, $invoicePdfPageCount),
                'broughtForward' => $broughtForward,
                'pageSubtotal' => array_merge($runningTotals),
                'showMiddleFooter' => $isDocumentLastPage,
                'showPageSubtotal' => ! $isDocumentLastPage,
            ];
        }

        $freight = $invoice->freight_charges !== null ? (float) $invoice->freight_charges : null;
        $insurance = $invoice->insurance_charges !== null ? (float) $invoice->insurance_charges : null;
        $packing = $invoice->packing_charges !== null ? (float) $invoice->packing_charges : null;

        $chargesSum = ($freight ?? 0) + ($insurance ?? 0) + ($packing ?? 0);
        $grandTotal = round($sumFinal + $chargesSum, 2);

        $marksAndNosText = '—';
        $packageKindLine = '—';
        $pkg = $invoice->package;
        if ($pkg) {
            $pkg->loadMissing(['boxes', 'packagePallets']);
            $usePallets = (bool) ($pkg->use_pallets ?? false);
            $palletCount = $pkg->packagePallets->count();

            if ($usePallets && $palletCount > 0) {
                // Pallet serial: first pallet → last pallet (SIB/01/NN TO SIB/NN/NN).
                $marksAndNosText = sprintf(
                    'SIB/%02d/%02d TO SIB/%02d/%02d',
                    1,
                    $palletCount,
                    $palletCount,
                    $palletCount
                );
                $packageKindLine = sprintf('%02d Pallets', $palletCount);
            } else {
                $boxCount = $pkg->boxes->count();
                if ($boxCount === 0 && (int) ($pkg->total_boxes ?? 0) > 0) {
                    $boxCount = (int) $pkg->total_boxes;
                }
                if ($boxCount > 0) {
                    // Box serial: first box → last box (SIB/01/NN TO SIB/NN/NN).
                    $marksAndNosText = sprintf(
                        'SIB/%02d/%02d TO SIB/%02d/%02d',
                        1,
                        $boxCount,
                        $boxCount,
                        $boxCount
                    );
                    $packageKindLine = sprintf('%02d Corrugated Boxes', $boxCount);
                }
            }
        }

        $agreementCode = $invoice->agreement_code_1 ?: Invoice::DEFAULT_AGREEMENT_CODE_1;

        $invoicePackageSummary = $this->buildInvoicePackageSummaryForPdf($invoice);

        $defaultFocYellow = '(Code HS/NDP France '.(preg_replace('/\D/', '', (string) $companyHsn) ?: '91139090').') Assembled';
        $focGroupPdfSpecs = [];
        if ($invoice->has_free_of_cost_items) {
            foreach ($invoice->focGroups as $focGroup) {
                if ($focGroup->items->isEmpty()) {
                    continue;
                }
                $groupRows = [];
                $groupTotalQty = 0.0;
                $groupTotalAmount = 0.0;
                foreach ($focGroup->items as $fi) {
                    $qty = max(0, (float) $fi->quantity);
                    $rate = (float) $fi->rate;
                    $amount = round($qty * $rate, 2);
                    $groupTotalQty += $qty;
                    $groupTotalAmount += $amount;
                    $groupRows[] = [
                        'code' => trim((string) ($fi->code ?? '')) !== '' ? trim((string) $fi->code) : '—',
                        'description' => trim((string) ($fi->description ?? '')) !== '' ? trim((string) $fi->description) : '—',
                        'beNo' => trim((string) ($fi->be_no ?? '')) !== '' ? trim((string) $fi->be_no) : '—',
                        'qty' => $qty,
                        'units' => trim((string) ($fi->units ?? '')) !== '' ? trim((string) $fi->units) : '—',
                        'rate' => $rate,
                        'amount' => $amount,
                    ];
                }
                $yellow = trim((string) ($focGroup->highlighter ?? ''));
                if ($yellow === '') {
                    $yellow = $defaultFocYellow;
                }
                $focGroupPdfSpecs[] = [
                    'rows' => $groupRows,
                    'totalQty' => $groupTotalQty,
                    'totalAmount' => round($groupTotalAmount, 2),
                    'yellowRowText' => $yellow,
                ];
            }
        }

        $showFocSection = $focGroupPdfSpecs !== [];

        $lastLineChunk = $invoicePdfChunks !== []
            ? $invoicePdfChunks[array_key_last($invoicePdfChunks)]
            : null;
        $focBroughtForward = $lastLineChunk['pageSubtotal'] ?? null;
        $focPageSubtotal = $focBroughtForward;

        $packedFocPages = $showFocSection
            ? $this->packFocGroupsIntoPdfPages($focGroupPdfSpecs)
            : [];
        $focPageCount = count($packedFocPages);
        $totalPdfPages = $invoicePdfPageCount + $focPageCount;
        foreach ($invoicePdfChunks as $i => &$chunk) {
            $pageNum = $i + 1;
            $chunk['pageLabel'] = sprintf('PAGE - %d/%d', $pageNum, $totalPdfPages);
            $chunk['customerReference'] = $pageNum < $totalPdfPages
                ? sprintf('C/O - PAGE - %02d', $pageNum + 1)
                : '';
            $chunk['broughtForwardLabel'] = $pageNum > 1
                ? sprintf('Brought Forward from Page - %02d', $pageNum - 1)
                : null;
        }
        unset($chunk);

        $focPdfChunks = [];
        foreach ($packedFocPages as $pi => $packedPage) {
            $isDocumentLast = $pi === $focPageCount - 1;
            $pageNum = $invoicePdfPageCount + $pi + 1;
            $tables = $packedPage['tables'];
            foreach ($tables as &$table) {
                if (! empty($table['focTableBroughtForward'])) {
                    $table['focTableBroughtForwardLabel'] = sprintf(
                        'Brought Forward from Page - %02d',
                        $pageNum - 1
                    );
                }
            }
            unset($table);

            $focPdfChunks[] = [
                'printRows' => $this->buildPackedFocPagePrintRows($tables),
                'focTables' => $tables,
                'isDocumentLast' => $isDocumentLast,
                'showMiddleFooter' => $isDocumentLast,
                'showPageSubtotal' => ! $isDocumentLast,
                'pageLabel' => sprintf('PAGE - %d/%d', $pageNum, $totalPdfPages),
                'customerReference' => $pageNum < $totalPdfPages
                    ? sprintf('C/O - PAGE - %02d', $pageNum + 1)
                    : '',
                'broughtForward' => $focBroughtForward,
                'broughtForwardLabel' => $pageNum > 1
                    ? sprintf('Brought Forward from Page - %02d', $pageNum - 1)
                    : null,
                'pageSubtotal' => $focPageSubtotal,
            ];
        }

        $focTotalQty = 0.0;
        $focTotalAmount = 0.0;
        $focYellowRowText = $defaultFocYellow;
        $focPdfRows = [];
        if ($focGroupPdfSpecs !== []) {
            $lastSpec = $focGroupPdfSpecs[array_key_last($focGroupPdfSpecs)];
            $focYellowRowText = $lastSpec['yellowRowText'];
            foreach ($focGroupPdfSpecs as $spec) {
                $focTotalQty += $spec['totalQty'];
                $focTotalAmount = round($focTotalAmount + $spec['totalAmount'], 2);
                foreach ($spec['rows'] as $r) {
                    $focPdfRows[] = $r;
                }
            }
        }


        return [
            'companyName' => $companyName,
            'exporterRef' => $exporterRef,
            'iecNo' => $iecNo,
            'panNo' => $panNo,
            'companyAddress' => $companyAddress,
            'termsOfPayment' => $invoice->terms_of_payment ?: Invoice::DEFAULT_TERMS_OF_PAYMENT,
            'termsOfDeliveryOption' => $invoice->terms_of_delivery ?: Invoice::DEFAULT_TERMS_OF_DELIVERY,
            'paymentToDetails' => $paymentToDetails,
            'companyFeatures' => $companyFeatures,
            'exportStatement' => $exportStatement,
            'invoiceAgreementCode' => $invoice->agreement_code ?: Invoice::DEFAULT_AGREEMENT_CODE,
            'includeBottomStatement' => (bool) ($invoice->include_bottom_statement ?? true),

            'invoiceNumber' => $invoiceNumber,
            'invoiceDate' => $invoiceDateDisplay,
            'supplyDate' => $supplyDateDisplay,
            // Customer reference is set per page from `invoicePdfChunks[$i]` and passed into the footer.
            'customerReference' => '',
            'typeOfInvoice' => $invoice->type_of_invoice ?: Invoice::DEFAULT_TYPE_OF_INVOICE,
            'invoiceHeading' => strtoupper($invoice->type_of_invoice ?: Invoice::DEFAULT_TYPE_OF_INVOICE),

            'buyersOrderNoAndDate' => $buyersOrderNoAndDate,
            'sumQty' => $sumQty,
            'sumQtyNos' => $sumQtyNos,
            'sumQtyPairs' => $sumQtyPairs,
            'hasQtyPairs' => $hasQtyPairs,
            'hasQtyNos' => $hasQtyNos,
            'allQtyPairs' => $lineItemCount > 0 && $hasQtyPairs && ! $hasQtyNos,
            'qtyUnitHeader' => ($lineItemCount > 0 && $hasQtyPairs && ! $hasQtyNos)
                ? 'Pairs'
                : (($hasQtyPairs && $hasQtyNos) ? 'Nos. / Pairs' : 'Nos.'),
            'marksAndNosText' => $marksAndNosText,
            'packageKindLine' => $packageKindLine,
            'agreementCode' => $agreementCode,
            'invoiceRemarks' => '',
            'attention' => $attention,
            'consigneeName' => $clientName,
            'consigneeAddress' => $consigneeAddress,
            'consigneePhone' => $consigneePhone,
            'buyerName' => $buyerName,
            'buyerAddress' => $buyerAddress,

            'countryOfFinalDestination' => $countryOfFinalDestination,
            'portOfLoading' => $portOfLoading,
            'portOfDischarge' => $portOfDischarge,
            'finalDestination' => $finalDestination,
            'vesselFlightNo' => $vesselFlightNo,
            'preCarriageBy' => $preCarriageBy,
            'placeOfReceipt' => $placeOfReceipt,
            'countryOfOrigin' => $countryOfOrigin,

            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'currencySubheader' => $currencySubheader,

            'invoicePdfChunks' => $invoicePdfChunks,
            'invoicePackageSummary' => $invoicePackageSummary,
            'middleSectionRowStats' => [
                'total' => $totalPaddedLineRows,
                'content' => $middleSectionContentRows,
                'remaining' => max(0, $totalPaddedLineRows - $middleSectionContentRows),
                'minCapacity' => $minLineBodyMiddle,
            ],
            'totals' => [
                'totalAmount' => round($sumAmount, 2),
                'totalLessDiscount' => round($sumLessDiscount, 2),
                'totalTaxable' => round($sumTaxable, 2),
                'totalIgst' => round($sumIgst, 2),
                'totalFinalLines' => round($sumFinal, 2),
                'freight' => $freight,
                'insurance' => $insurance,
                'packing' => $packing,
                'grandTotal' => $grandTotal,
                'hasIgst' => $invoice->gst_rate !== null,
            ],

            'showFocSection' => $showFocSection,
            'focPdfChunks' => $focPdfChunks,
            'focPdfRows' => $focPdfRows,
            'focTotalQty' => $focTotalQty,
            'focTotalAmount' => $focTotalAmount,
            'focYellowRowText' => $focYellowRowText,
        ];
    }

    /**
     * Quantity for a PO line on a package box (pivot qty, else full PO qty) — matches Packaging PDFs.
     */
    private function invoiceBoxLineQuantity(OrderPO $po): int
    {
        $pivotQty = $po->pivot->qty ?? null;
        if ($pivotQty !== null && $pivotQty !== '') {
            return max(0, (int) $pivotQty);
        }

        return is_numeric($po->QtyByOrder) ? (int) $po->QtyByOrder : 0;
    }

    /**
     * @return array{
     *     totalQty: int,
     *     totalQtyNos: int,
     *     totalQtyPairs: int,
     *     hasPairs: bool,
     *     allPairs: bool,
     *     totalPackages: int,
     *     grossWeightKg: float|null,
     *     netWeightKg: float|null,
     *     dimWeightKg: float|null,
     *     specifyDimensionLines: array<int, string>
     * }
     */
    private function buildInvoicePackageSummaryForPdf(Invoice $invoice): array
    {
        $pkg = $invoice->package;
        if (! $pkg) {
            return [
                'totalQty' => 0,
                'totalQtyNos' => 0,
                'totalQtyPairs' => 0,
                'hasNos' => false,
                'hasPairs' => false,
                'allPairs' => false,
                'totalPackages' => 0,
                'usePallets' => false,
                'grossWeightKg' => null,
                'netWeightKg' => null,
                'dimWeightKg' => null,
                'specifyDimensionLines' => [],
            ];
        }

        $pkg->loadMissing(['boxes.orderPOs', 'packagePallets']);

        $totalQty = 0;
        $totalQtyNos = 0;
        $totalQtyPairs = 0;
        $hasPairs = false;
        $hasNos = false;
        $lineCount = 0;
        foreach ($pkg->boxes as $box) {
            foreach ($box->orderPOs as $po) {
                $qty = $this->invoiceBoxLineQuantity($po);
                $totalQty += $qty;
                $lineCount++;
                $isPairs = $this->pivotIsPairs($po->pivot->is_pairs ?? null);
                if ($isPairs) {
                    $hasPairs = true;
                    $totalQtyPairs += $qty;
                } else {
                    $hasNos = true;
                    $totalQtyNos += $qty;
                }
            }
        }

        $usePallets = (bool) ($pkg->use_pallets ?? false);
        $palletCount = $pkg->packagePallets->count();
        if ($usePallets) {
            $totalPackages = $palletCount;
        } else {
            $boxCount = $pkg->boxes->count();
            $totalPackages = $boxCount > 0 ? $boxCount : (int) ($pkg->total_boxes ?? 0);
        }

        $specifyDimensionLines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($pkg->specify_dimension ?? ''))),
            static fn ($line) => $line !== ''
        ));

        return [
            'totalQty' => $totalQty,
            'totalQtyNos' => $totalQtyNos,
            'totalQtyPairs' => $totalQtyPairs,
            'hasNos' => $hasNos,
            'hasPairs' => $hasPairs,
            'allPairs' => $lineCount > 0 && $hasPairs && ! $hasNos,
            'totalPackages' => $totalPackages,
            'usePallets' => $usePallets,
            'palletsCount' => $usePallets ? $palletCount : null,
            'grossWeightKg' => $pkg->gross_weight_kg !== null ? (float) $pkg->gross_weight_kg : null,
            'netWeightKg' => $pkg->net_weight_kg !== null ? (float) $pkg->net_weight_kg : null,
            'dimWeightKg' => $pkg->dim_weight_kg !== null ? (float) $pkg->dim_weight_kg : null,
            'specifyDimensionLines' => $specifyDimensionLines,
        ];
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
     * Fold trailing highlighter/group-only sheets into the previous page so we never emit a
     * PDF page that is only Brought Forward + blank rows.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $chunks
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function mergeInvoicePrintChunksWithoutLineItems(array $chunks): array
    {
        $chunks = array_values($chunks);
        while (count($chunks) > 1) {
            $last = $chunks[array_key_last($chunks)];
            if ($this->invoicePrintChunkHasLineItems($last)) {
                break;
            }
            $prevIndex = count($chunks) - 2;
            $chunks[$prevIndex] = array_merge($chunks[$prevIndex], $last);
            array_pop($chunks);
        }

        return array_values($chunks);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function invoicePrintChunkHasLineItems(array $rows): bool
    {
        foreach ($rows as $row) {
            if (($row['type'] ?? '') === 'line') {
                return true;
            }
        }

        return false;
    }

    /**
     * Pack FOC groups onto PDF pages: multiple FOC tables share a page when unit budget allows.
     * Within a group, leftover rows continue on later pages with Brought Forward totals.
     *
     * @param  array<int, array{rows: array<int, array<string, mixed>>, yellowRowText: string}>  $focGroupPdfSpecs
     * @return array<int, array{tables: array<int, array<string, mixed>>}>
     */
    private function packFocGroupsIntoPdfPages(array $focGroupPdfSpecs): array
    {
        $pages = [];
        $currentTables = [];
        $usedUnits = 0;
        $budget = self::INVOICE_EXPORT_PDF_FOC_PAGE_UNIT_BUDGET;

        $flushPage = static function () use (&$pages, &$currentTables, &$usedUnits): void {
            if ($currentTables === []) {
                return;
            }
            $pages[] = [
                'tables' => $currentTables,
                'usedUnits' => $usedUnits,
            ];
            $currentTables = [];
            $usedUnits = 0;
        };

        $sliceCost = function (int $itemCount, bool $hasBf, bool $hasYellow, bool $isAdditionalOnPage): int {
            $cost = self::INVOICE_EXPORT_PDF_FOC_TABLE_CHROME_UNITS
                + self::INVOICE_EXPORT_PDF_FOC_TABLE_FOOTER_UNITS
                + $itemCount;
            if ($hasBf) {
                $cost += 1;
            }
            if ($hasYellow) {
                $cost += self::INVOICE_EXPORT_PDF_FOC_TABLE_YELLOW_UNITS;
            }
            if ($isAdditionalOnPage) {
                $cost += self::INVOICE_EXPORT_PDF_FOC_TABLE_GAP_UNITS;
            }

            return $cost;
        };

        foreach ($focGroupPdfSpecs as $spec) {
            $remaining = array_values($spec['rows']);
            $runningQty = 0.0;
            $runningAmount = 0.0;
            $sliceIndex = 0;

            while ($remaining !== []) {
                $isAdditional = $currentTables !== [];
                $hasBf = $sliceIndex > 0;
                $overheadNoYellow = $sliceCost(0, $hasBf, false, $isAdditional);
                $overheadWithYellow = $sliceCost(0, $hasBf, true, $isAdditional);

                $room = $budget - $usedUnits;
                $maxItemsNoYellow = $room - $overheadNoYellow;
                $maxItemsWithYellow = $room - $overheadWithYellow;

                if ($maxItemsNoYellow < 1) {
                    $flushPage();
                    $isAdditional = false;
                    $overheadNoYellow = $sliceCost(0, $hasBf, false, false);
                    $overheadWithYellow = $sliceCost(0, $hasBf, true, false);
                    $room = $budget;
                    $maxItemsNoYellow = $room - $overheadNoYellow;
                    $maxItemsWithYellow = $room - $overheadWithYellow;
                }

                $remainingCount = count($remaining);
                $finishesGroup = false;
                $take = 0;

                if ($remainingCount <= $maxItemsWithYellow) {
                    $take = $remainingCount;
                    $finishesGroup = true;
                } elseif ($maxItemsNoYellow >= 1) {
                    // Leave at least one item for a later page when we cannot finish the group.
                    $take = min($maxItemsNoYellow, max(1, $remainingCount - 1));
                    if ($take >= $remainingCount) {
                        // Fits without yellow budget — finish without yellow only if yellow also fits.
                        if ($remainingCount <= $maxItemsWithYellow) {
                            $take = $remainingCount;
                            $finishesGroup = true;
                        } else {
                            $take = max(1, $remainingCount - 1);
                            $finishesGroup = false;
                        }
                    }
                } else {
                    // Only yellow budget leftover — start a fresh page.
                    $flushPage();
                    continue;
                }

                $sliceRows = array_splice($remaining, 0, $take);
                $bf = $hasBf
                    ? ['qty' => $runningQty, 'amount' => $runningAmount]
                    : null;

                foreach ($sliceRows as $focRow) {
                    $runningQty += (float) ($focRow['qty'] ?? 0);
                    $runningAmount = round($runningAmount + (float) ($focRow['amount'] ?? 0), 2);
                }

                $cost = $sliceCost(count($sliceRows), $hasBf, $finishesGroup, $isAdditional);
                $currentTables[] = [
                    'focPdfRows' => array_values($sliceRows),
                    'showFocYellowRow' => $finishesGroup,
                    'focYellowRowText' => $spec['yellowRowText'],
                    'focTableBroughtForward' => $bf,
                    'focTableBroughtForwardLabel' => $hasBf ? 'Brought Forward' : null,
                    'focPageTotalQty' => $runningQty,
                    'focPageTotalAmount' => $runningAmount,
                ];
                $usedUnits += $cost;
                $sliceIndex++;
            }
        }

        $flushPage();

        // Re-pack last page with the tighter last-page budget when it would overflow.
        if (count($pages) >= 1) {
            $pages = $this->rebalanceLastFocPackedPage($pages);
        }

        return $pages;
    }

    /**
     * If the final packed page exceeds the last-page unit budget, move overflow tables
     * onto a new last page (keeps package-summary footer from colliding with FOC content).
     *
     * @param  array<int, array{tables: array<int, array<string, mixed>>}>  $pages
     * @return array<int, array{tables: array<int, array<string, mixed>>}>
     */
    private function rebalanceLastFocPackedPage(array $pages): array
    {
        $lastBudget = self::INVOICE_EXPORT_PDF_FOC_PAGE_UNIT_BUDGET_LAST;
        $last = $pages[array_key_last($pages)];
        $tables = $last['tables'];
        $used = 0;
        $kept = [];
        $overflow = [];

        foreach ($tables as $i => $table) {
            $itemCount = count($table['focPdfRows'] ?? []);
            $hasBf = ! empty($table['focTableBroughtForward']);
            $hasYellow = ! empty($table['showFocYellowRow']);
            $isAdditional = $kept !== [];
            $cost = self::INVOICE_EXPORT_PDF_FOC_TABLE_CHROME_UNITS
                + self::INVOICE_EXPORT_PDF_FOC_TABLE_FOOTER_UNITS
                + $itemCount
                + ($hasBf ? 1 : 0)
                + ($hasYellow ? self::INVOICE_EXPORT_PDF_FOC_TABLE_YELLOW_UNITS : 0)
                + ($isAdditional ? self::INVOICE_EXPORT_PDF_FOC_TABLE_GAP_UNITS : 0);

            if ($overflow === [] && ($used + $cost) <= $lastBudget) {
                $kept[] = $table;
                $used += $cost;
            } else {
                $overflow[] = $table;
            }
        }

        if ($overflow === []) {
            return $pages;
        }

        if ($kept === []) {
            // Single oversized table: keep it on last page anyway.
            return $pages;
        }

        $pages[array_key_last($pages)] = [
            'tables' => $kept,
            'usedUnits' => $used,
        ];
        $overflowUsed = 0;
        foreach ($overflow as $oi => $table) {
            $itemCount = count($table['focPdfRows'] ?? []);
            $hasBf = ! empty($table['focTableBroughtForward']);
            $hasYellow = ! empty($table['showFocYellowRow']);
            $isAdditional = $oi > 0;
            $overflowUsed += self::INVOICE_EXPORT_PDF_FOC_TABLE_CHROME_UNITS
                + self::INVOICE_EXPORT_PDF_FOC_TABLE_FOOTER_UNITS
                + $itemCount
                + ($hasBf ? 1 : 0)
                + ($hasYellow ? self::INVOICE_EXPORT_PDF_FOC_TABLE_YELLOW_UNITS : 0)
                + ($isAdditional ? self::INVOICE_EXPORT_PDF_FOC_TABLE_GAP_UNITS : 0);
        }
        $pages[] = [
            'tables' => $overflow,
            'usedUnits' => $overflowUsed,
        ];

        return $pages;
    }

    /**
     * Outer-table rows for a packed FOC page: FOC embeds (+ small gaps between stacked tables only).
     * No fixed trailing blank padding — height follows FOC content so the PDF footer stays on-page.
     *
     * @param  array<int, array<string, mixed>>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function buildPackedFocPagePrintRows(array $tables): array
    {
        $rows = [];
        for ($b = 0; $b < self::INVOICE_EXPORT_PDF_FOC_PAGE_TOP_BLANK_ROWS; $b++) {
            $rows[] = ['type' => 'blank'];
        }

        foreach ($tables as $i => $table) {
            if ($i > 0) {
                for ($g = 0; $g < self::INVOICE_EXPORT_PDF_FOC_PAGE_GAP_BLANK_ROWS; $g++) {
                    $rows[] = ['type' => 'blank'];
                }
            }
            $rows[] = [
                'type' => 'foc',
                'table' => $table,
            ];
        }

        return $rows;
    }

    /**
     * @return array{qty: int, amount: float, lessDiscount: float, taxable: float, igst: float, final: float}
     */
    private function emptyInvoicePageTotals(): array
    {
        return [
            'qty' => 0,
            'amount' => 0.0,
            'lessDiscount' => 0.0,
            'taxable' => 0.0,
            'igst' => 0.0,
            'final' => 0.0,
        ];
    }

    /**
     * @param  array{qty: int, amount: float, lessDiscount: float, taxable: float, igst: float, final: float}  $totals
     * @param  array<string, mixed>  $row
     */
    private function addInvoiceLineToPageTotals(array &$totals, array $row): void
    {
        $totals['qty'] += (int) ($row['qty'] ?? 0);
        $totals['amount'] = round($totals['amount'] + (float) ($row['amount'] ?? 0), 2);
        $totals['lessDiscount'] = round($totals['lessDiscount'] + (float) ($row['lessDiscount'] ?? 0), 2);
        $totals['taxable'] = round($totals['taxable'] + (float) ($row['taxableValue'] ?? 0), 2);
        $totals['igst'] = round($totals['igst'] + (float) ($row['igstAmount'] ?? 0), 2);
        $totals['final'] = round($totals['final'] + (float) ($row['finalAmount'] ?? 0), 2);
    }
}
