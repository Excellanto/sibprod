<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignName extends Model
{
	use HasFactory;

	protected $table = 'designname';

	protected $primaryKey = 'DesignNameId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Name',
	];
}
