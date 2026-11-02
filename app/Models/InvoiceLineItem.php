<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    protected $fillable = [
        'invoice_line_group_id',
        'orders_po_id',
        'po_tag',
        'design_name',
        'quantity',
        'is_pairs',
        'rate',
        'discount_percent',
    ];

    protected $casts = [
        'is_pairs' => 'boolean',
        'rate' => 'decimal:4',
        'discount_percent' => 'decimal:3',
    ];

    public function invoiceLineGroup(): BelongsTo
    {
        return $this->belongsTo(InvoiceLineGroup::class);
    }

    public function orderPO(): BelongsTo
    {
        return $this->belongsTo(OrderPO::class, 'orders_po_id', 'id');
    }
}
