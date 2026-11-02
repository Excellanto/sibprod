<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcRegister extends Model
{
	use HasFactory;

	protected $table = 'qc_register';

	protected $fillable = [
		'quality_control_id',
		'qty',
		'qc_reason_id',
	];

	public function qualityControl()
	{
		return $this->belongsTo(QualityControl::class, 'quality_control_id', 'id');
	}
}
