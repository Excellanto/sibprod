<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampingShort extends Model
{
	use HasFactory;

	protected $table = 'stampingshort';

	protected $primaryKey = 'StampingShortId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Description',
	];
}











