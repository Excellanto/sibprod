<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
	use HasFactory;

	protected $table = 'tools';

	protected $primaryKey = 'ToolId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Name',
	];
}





