<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reinforce extends Model
{
	use HasFactory;

	protected $table = 'reinforce';

	protected $primaryKey = 'ReinforceId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'ReinforceType',
	];
}











