<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceLineGroup extends Model
{
    protected $fillable = [
        'invoice_id',
        'line_item_desc_id',
        'hsn',
        'highlighter',
    ];

    protected $casts = [
        'line_item_desc_id' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('id');
    }
}
