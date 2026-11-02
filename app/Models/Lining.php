<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lining extends Model
{
	use HasFactory;

	protected $primaryKey = 'LiningId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Name',
	];
}
