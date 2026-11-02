<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoopBand extends Model
{
	use HasFactory;

	protected $table = 'loopband';

	protected $primaryKey = 'LoopBandId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'LoopBandSize',
	];
}











