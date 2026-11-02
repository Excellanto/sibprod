<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $table = 'packages';

    protected $fillable = [
        'client_id',
        'use_pallets',
        'pallets',
        'total_boxes',
        'status',
        'terms_of_delivery',
        'terms_of_payment',
        'pre_carriage_by',
        'place_of_receipt',
        'vessel_flight_no',
        'port_of_loading',
        'shipping_date',
        'invoice_number',
        'invoice_date',
        'gross_weight_kg',
        'net_weight_kg',
        'dim_weight_kg',
        'specify_dimension',
    ];

    protected $casts = [
        'use_pallets' => 'boolean',
        'total_boxes' => 'integer',
        'shipping_date' => 'date',
        'invoice_date' => 'date',
        'gross_weight_kg' => 'decimal:2',
        'net_weight_kg' => 'decimal:2',
        'dim_weight_kg' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function packagePallets()
    {
        return $this->hasMany(PackagePallet::class)->orderBy('pallet_number');
    }

    public function boxes()
    {
        return $this->hasMany(PackageBox::class)->orderBy('box_number');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
