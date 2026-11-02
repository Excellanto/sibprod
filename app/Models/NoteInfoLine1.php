<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteInfoLine1 extends Model
{
	use HasFactory;

	protected $table = 'noteinfoline1';

	protected $primaryKey = 'NoteInfoLine1Id';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Line1',
	];
}





