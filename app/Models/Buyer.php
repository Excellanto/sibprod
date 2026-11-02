<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
	use HasFactory;

	protected $fillable = [
		'buyer_name',
		'email',
		'phone',
		'address',
		'city',
		'country',
	];

	public function clients()
	{
		return $this->hasMany(Client::class);
	}
}
