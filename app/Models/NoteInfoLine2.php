<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteInfoLine2 extends Model
{
	use HasFactory;

	protected $table = 'noteinfoline2';

	protected $primaryKey = 'NoteInfoLine2Id';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Line2',
	];
}





