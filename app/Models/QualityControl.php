<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityControl extends Model
{
	use HasFactory;

	protected $table = 'quality_control';

	protected $fillable = [
		'orderspo_id',
		'Qty',
		'Gold',
		'Silver',
		'QC_status',
		'QC_creation_date',
		'QC_completion_date',
	];

	protected $casts = [
		'QC_creation_date' => 'date',
		'QC_completion_date' => 'date',
	];

	public function orderPO()
	{
		return $this->belongsTo(OrderPO::class, 'orderspo_id', 'id');
	}

	public function qcRegisters()
	{
		return $this->hasMany(QcRegister::class, 'quality_control_id', 'id');
	}
}
