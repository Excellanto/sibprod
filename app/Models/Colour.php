<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Colour extends Model
{
	use HasFactory;

	protected $table = 'colour';

	protected $primaryKey = 'ColourId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'ColourName',
		'ColourObjectId',
	];

	public function colourObject()
	{
		return $this->belongsTo(ColourObject::class, 'ColourObjectId', 'ColourObjectId');
	}
}











