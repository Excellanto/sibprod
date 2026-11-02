<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndWidth extends Model
{
	use HasFactory;

	protected $table = 'endwidth';

	protected $primaryKey = 'EndWidthId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'EndWidthSize',
	];
}











