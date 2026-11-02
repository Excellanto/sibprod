<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceFocItem extends Model
{
    protected $fillable = [
        'invoice_foc_group_id',
        'code',
        'description',
        'be_no',
        'quantity',
        'units',
        'rate',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:4',
        'sort_order' => 'integer',
    ];

    public function focGroup(): BelongsTo
    {
        return $this->belongsTo(InvoiceFocGroup::class, 'invoice_foc_group_id');
    }
}
