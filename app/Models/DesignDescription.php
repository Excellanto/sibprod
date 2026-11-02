<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignDescription extends Model
{
	use HasFactory;

	protected $table = 'designdescription';

	protected $primaryKey = 'DesignDescriptionId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Description',
	];
}
