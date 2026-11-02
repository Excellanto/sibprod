<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buckle extends Model
{
	use HasFactory;

	protected $table = 'buckle';

	protected $primaryKey = 'BuckleId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'BuckleType',
	];
}

