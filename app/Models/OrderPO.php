<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPO extends Model
{
    use HasFactory;

    protected $table = 'ordersPO';

    protected $fillable = [
        'OrderId',
        'PsNumber',
        'ClientModel',
        'RefNumber',
        'QtyByOrder',
        'Rate',
        'Gold',
        'Silver',
        'DateInProduction',
        'DateOfDelivery',
        'NoOfPrintOut',
        'Status',
    ];

    protected $casts = [
        'DateInProduction' => 'date',
        'DateOfDelivery' => 'date',
        'Rate' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'OrderId', 'OrderId');
    }

    public function qualityControls()
    {
        return $this->hasMany(QualityControl::class, 'orderspo_id', 'id');
    }

    public function invoiceLineItems()
    {
        return $this->hasMany(InvoiceLineItem::class, 'orders_po_id', 'id');
    }
}
