<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const DEFAULT_TERMS_OF_PAYMENT = 'EX-WORKS';

    public const DEFAULT_TERMS_OF_DELIVERY = '30 Days';

    public const DEFAULT_AGREEMENT_CODE = '"W" 9113';

    public const DEFAULT_AGREEMENT_CODE_1 = 'GSTP';

    public const DEFAULT_TYPE_OF_INVOICE = 'Export Invoice';

    public const DEFAULT_PORT_OF_LOADING = 'New Delhi';

    /** @return list<string> */
    public static function typeOfInvoiceOptions(): array
    {
        return [
            'Export Invoice',
            'Local Invoice',
            'Sample Invoice',
        ];
    }

    /** @return list<string> */
    public static function termsOfDeliveryOptions(): array
    {
        return [
            '15 Days',
            '30 Days',
            '45 Days',
            '90 Days',
            '120 Days',
            '150 Days',
            '180 Days',
        ];
    }

    protected $fillable = [
        'client_id',
        'type_of_invoice',
        'package_id',
        'total_groupings',
        'status',
        'invoice_date',
        'supply_date',
        'has_free_of_cost_items',
        'freight_charges',
        'insurance_charges',
        'packing_charges',
        'gst_rate',
        'terms_of_delivery',
        'terms_of_payment',
        'pre_carriage_by',
        'place_of_receipt',
        'vessel_flight_no',
        'port_of_loading',
        'agreement_code_1',
        'agreement_code',
        'include_bottom_statement',
        'fiscal_year_label',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'supply_date' => 'date',
        'has_free_of_cost_items' => 'boolean',
        'include_bottom_statement' => 'boolean',
        'total_groupings' => 'integer',
        'freight_charges' => 'decimal:2',
        'insurance_charges' => 'decimal:2',
        'packing_charges' => 'decimal:2',
        'gst_rate' => 'decimal:3',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function lineGroups(): HasMany
    {
        return $this->hasMany(InvoiceLineGroup::class)->orderBy('id');
    }

    public function focGroups(): HasMany
    {
        return $this->hasMany(InvoiceFocGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Indian FY (April–March): e.g. Apr 2025 → Mar 2026 → "2025-26"
     */
    public static function fiscalYearLabelFromDate(CarbonInterface $date): string
    {
        $year = (int) $date->year;
        $month = (int) $date->month;
        $startYear = $month >= 4 ? $year : $year - 1;
        $endShort = substr((string) ($startYear + 1), -2);

        return sprintf('%d-%s', $startYear, $endShort);
    }

    public function formattedInvoiceNumber(): string
    {
        return sprintf(
            'SN/ %s / %s',
            $this->fiscal_year_label,
            str_pad((string) $this->id, 5, '0', STR_PAD_LEFT)
        );
    }
}
