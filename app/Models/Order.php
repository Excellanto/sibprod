<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
	use HasFactory;

	protected $table = 'orders';

	protected $primaryKey = 'OrderId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'OrderNumber',
		'ClientId',
		'QtyTotal',
		'DeliveryDate',
		'Status',
	];

	protected $casts = [
		'DeliveryDate' => 'date',
	];

	public function client()
	{
		return $this->belongsTo(Client::class, 'ClientId', 'id');
	}

	public function orderPOs()
	{
		return $this->hasMany(OrderPO::class, 'OrderId', 'OrderId');
	}
}
