<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrapPicture extends Model
{
	use HasFactory;

	protected $table = 'strappictures';

	protected $primaryKey = 'StrapPictureId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Name',
	];
}











