<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagePallet extends Model
{
    use HasFactory;

    protected $table = 'package_pallets';

    protected $fillable = [
        'package_id',
        'pallet_number',
        'name',
        'planned_box_count',
    ];

    protected $casts = [
        'pallet_number' => 'integer',
        'planned_box_count' => 'integer',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function boxes()
    {
        return $this->hasMany(PackageBox::class, 'package_pallet_id')->orderBy('box_number');
    }
}
