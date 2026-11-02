<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColourObject extends Model
{
	use HasFactory;

	protected $table = 'colourobject';

	protected $primaryKey = 'ColourObjectId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'ColourObject',
	];
}











