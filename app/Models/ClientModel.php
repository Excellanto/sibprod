<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientModel extends Model
{
	use HasFactory;

	protected $table = 'clientmodel';

	protected $primaryKey = 'clientmodelId';
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'ClientId',
		'ModelNo',
		'RefNumber',
		'DescriptionOnInvoice',
		'OemReference',
		'LeatherId',
		'LeatherColourId',
		'LiningId',
		'SpecialId1',
		'SpecialId2',
		'LoopBandId',
		'EndWidthId',
		'BuckleId',
		'BuckleWidthId',
		'DesignNameId',
		'DesignDescriptionId',
		'VarnishColourId',
		'TopThreadColourId',
		'BottomThreadColourId',
		'TopThreadSizeId',
		'BottomThreadSizeId',
		'StampingShortId',
		'StampingLongId',
		'StrapPictureId',
		'NoteInfoLine1Id',
		'NoteInfoLine2Id',
		'StrapLengthLongPartId',
		'StrapLengthShortPartId',
		'FirstCuttingToolId',
		'InsideCuttingToolId',
		'LoopCuttingToolId',
		'LoopFoldingToolId',
		'ReinforceType',
		'BigLoop',
		'SmallLoop',
		'BigLoopQty',
		'SmallLoopQty',
		'status',
	];

	public function client()
	{
		return $this->belongsTo(Client::class, 'ClientId', 'id');
	}
}
