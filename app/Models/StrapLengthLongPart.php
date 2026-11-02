<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrapLengthLongPart extends Model
{
	use HasFactory;

	protected $table = 'straplengthlongpart';

	protected $primaryKey = 'StrapLengthLongPartId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'LongPart',
	];
}





