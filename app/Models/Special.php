<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Special extends Model
{
	use HasFactory;

	protected $table = 'special';

	protected $primaryKey = 'SpecialId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Description',
	];
}











