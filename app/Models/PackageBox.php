<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageBox extends Model
{
	use HasFactory;

	protected $table = 'package_boxes';

	protected $fillable = [
		'package_id',
		'package_pallet_id',
		'box_number',
	];

	public function package()
	{
		return $this->belongsTo(Package::class);
	}

	public function packagePallet()
	{
		return $this->belongsTo(PackagePallet::class, 'package_pallet_id');
	}

	public function orderPOs()
	{
		return $this->belongsToMany(OrderPO::class, 'package_box_order_po', 'package_box_id', 'order_po_id')
			->withPivot('weight', 'line_item_desc', 'qty', 'is_pairs')
			->withTimestamps();
	}
}
