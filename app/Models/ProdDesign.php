<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdDesign extends Model
{
	use HasFactory;

	protected $table = 'proddesign';

	protected $primaryKey = 'id';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Name',
	];
}











