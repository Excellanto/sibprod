<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrapLengthShortPart extends Model
{
	use HasFactory;

	protected $table = 'straplengthshortpart';

	protected $primaryKey = 'StrapLengthShortPartId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'ShortPart',
	];
}





