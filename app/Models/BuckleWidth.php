<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuckleWidth extends Model
{
	use HasFactory;

	protected $table = 'bucklewidth';

	protected $primaryKey = 'BuckleWidthId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'BuckleWidthSize',
	];
}











