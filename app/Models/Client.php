<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'client_name',
        'currency',
        'taxrule',
        'postalcode',
        'attention',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'port_of_discharge',
    ];

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'ClientId', 'id');
    }
}
