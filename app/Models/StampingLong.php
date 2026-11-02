<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampingLong extends Model
{
	use HasFactory;

	protected $table = 'stampinglong';

	protected $primaryKey = 'StampingLongId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Description',
	];
}











