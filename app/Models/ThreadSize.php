<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThreadSize extends Model
{
	use HasFactory;

	protected $table = 'threadsize';

	protected $primaryKey = 'ThreadSizeId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'ThreadSize',
	];
}











