<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leather extends Model
{
	use HasFactory;

	protected $table = 'leather';

	protected $primaryKey = 'LeatherId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'Name',
		'Supplier',
	];
}











