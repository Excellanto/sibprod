<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Client;
use App\Models\ClientModel;
use App\Models\Leather;
use App\Models\Colour;
use App\Models\Lining;
use App\Models\DesignName;
use App\Models\DesignDescription;
use App\Models\Special;
use App\Models\LoopBand;
use App\Models\EndWidth;
use App\Models\Buckle;
use App\Models\BuckleWidth;
use App\Models\StrapLengthShortPart;
use App\Models\StrapLengthLongPart;
use App\Models\ThreadSize;
use App\Models\StampingShort;
use App\Models\StampingLong;
use App\Models\NoteInfoLine1;
use App\Models\NoteInfoLine2;
use App\Models\Tool;
use App\Models\StrapPicture;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModelTemplateController extends Controller
{
	public function index(Request $request): Response
	{
		$query = ClientModel::query()
			->select([
				'clientmodel.clientmodelId',
				'clientmodel.ClientId',
				'clientmodel.ModelNo',
				'clientmodel.RefNumber',
				'clientmodel.status',
				'clientmodel.updated_at',
				'clients.client_name'
			])
			->leftJoin('clients', 'clientmodel.ClientId', '=', 'clients.id');

		// Filter by Client (by ID or by name search)
		if ($request->filled('client_id')) {
			$query->where('clientmodel.ClientId', $request->client_id);
		} elseif ($request->filled('client_name') && strlen($request->client_name) >= 2) {
			$query->where('clients.client_name', 'like', '%' . $request->client_name . '%');
		}

		// Filter by Model No (autocomplete after 3 characters)
		if ($request->filled('model_no') && strlen($request->model_no) >= 3) {
			$query->where('clientmodel.ModelNo', 'like', '%' . $request->model_no . '%');
		}

		// Filter by Ref Number (autocomplete after 2 characters)
		if ($request->filled('ref_number') && strlen($request->ref_number) >= 2) {
			$query->where('clientmodel.RefNumber', 'like', '%' . $request->ref_number . '%');
		}

		$models = $query->orderByDesc('clientmodel.updated_at')
			->orderByDesc('clientmodel.clientmodelId')
			->paginate(10)
			->withQueryString();

		// Get all clients for the filter dropdown
		$allClients = Client::query()
			->select(['id', 'client_name'])
			->orderBy('client_name')
			->get()
			->map(function ($client) {
				return [
					'id' => $client->id,
					'name' => $client->client_name,
				];
			});

		// Get Model Status values
		$setting = SiteSetting::where('key', 'model_status')->first();
		$modelStatuses = [];
		
		if ($setting && $setting->type === 'json') {
			$decoded = json_decode($setting->value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$modelStatuses = $decoded;
			}
		}
		
		// If no data exists, initialize with default values
		if (empty($modelStatuses)) {
			$modelStatuses = [
				['id' => 1, 'name' => 'New Added'],
				['id' => 2, 'name' => 'Matched'],
			];
		}

		return Inertia::render('Admin/OrderManagement/ModelTemplate', [
			'models' => $models,
			'clients' => $allClients,
			'modelStatuses' => $modelStatuses,
			'filters' => [
				'client_id' => $request->client_id,
				'client_name' => $request->client_name,
				'model_no' => $request->model_no,
				'ref_number' => $request->ref_number,
			],
		]);
	}

	public function create(): Response
	{
		return Inertia::render('Admin/OrderManagement/CreateClientModel');
	}

	public function getBuyers(Request $request)
	{
		$query = $request->input('query', '');
		
		$buyers = Buyer::query()
			->select(['id', 'buyer_name'])
			->when($query, function ($q) use ($query) {
				$q->where('buyer_name', 'like', "%{$query}%");
			})
			->orderBy('buyer_name')
			->limit(50)
			->get()
			->map(function ($buyer) {
				return [
					'id' => $buyer->id,
					'name' => $buyer->buyer_name,
				];
			});

		return response()->json($buyers);
	}

	public function getClients(Request $request)
	{
		$query = $request->input('query', '');
		$buyerId = $request->input('buyer_id');

		if (!$buyerId) {
			return response()->json([]);
		}

		$clients = Client::query()
			->select(['id', 'client_name'])
			->where('buyer_id', $buyerId)
			->when($query, function ($q) use ($query) {
				$q->where('client_name', 'like', "%{$query}%");
			})
			->orderBy('client_name')
			->limit(50)
			->get()
			->map(function ($client) {
				return [
					'id' => $client->id,
					'name' => $client->client_name,
				];
			});

		return response()->json($clients);
	}

	public function getAllClients(Request $request)
	{
		$query = $request->input('query', '');

		$clients = Client::query()
			->select(['id', 'client_name'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('client_name', 'like', "%{$query}%");
			})
			->orderBy('client_name')
			->limit(50)
			->get()
			->map(function ($client) {
				return [
					'id' => $client->id,
					'name' => $client->client_name,
				];
			});

		return response()->json($clients);
	}

	public function getLeathers(Request $request)
	{
		$query = $request->input('query', '');

		$leathers = Leather::query()
			->select(['LeatherId', 'Name'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Name', 'like', "%{$query}%");
			})
			->orderBy('Name')
			->limit(50)
			->get()
			->map(function ($leather) {
				return [
					'id' => $leather->LeatherId,
					'name' => $leather->Name,
				];
			});

		return response()->json($leathers);
	}

	public function getLeatherColours(Request $request)
	{
		$query = $request->input('query', '');

		$colours = Colour::query()
			->select(['ColourId', 'ColourName'])
			->where('ColourObjectId', 1)
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('ColourName', 'like', "%{$query}%");
			})
			->orderBy('ColourName')
			->limit(50)
			->get()
			->map(function ($colour) {
				return [
					'id' => $colour->ColourId,
					'name' => $colour->ColourName,
				];
			});

		return response()->json($colours);
	}

	public function getLinings(Request $request)
	{
		$query = $request->input('query', '');

		$linings = Lining::query()
			->select(['LiningId', 'Name'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Name', 'like', "%{$query}%");
			})
			->orderBy('Name')
			->limit(50)
			->get()
			->map(function ($lining) {
				return [
					'id' => $lining->LiningId,
					'name' => $lining->Name,
				];
			});

		return response()->json($linings);
	}

	public function getVarnishColours(Request $request)
	{
		$query = $request->input('query', '');

		$colours = Colour::query()
			->select(['ColourId', 'ColourName'])
			->where('ColourObjectId', 4)
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('ColourName', 'like', "%{$query}%");
			})
			->orderBy('ColourName')
			->limit(50)
			->get()
			->map(function ($colour) {
				return [
					'id' => $colour->ColourId,
					'name' => $colour->ColourName,
				];
			});

		return response()->json($colours);
	}

	public function getDesignNames(Request $request)
	{
		$query = $request->input('query', '');

		$designNames = DesignName::query()
			->select(['DesignNameId', 'Name'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Name', 'like', "%{$query}%");
			})
			->orderBy('Name')
			->limit(50)
			->get()
			->map(function ($designName) {
				return [
					'id' => $designName->DesignNameId,
					'name' => $designName->Name,
				];
			});

		return response()->json($designNames);
	}

	public function getDesignDescriptions(Request $request)
	{
		$query = $request->input('query', '');

		$designDescriptions = DesignDescription::query()
			->select(['DesignDescriptionId', 'Description'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Description', 'like', "%{$query}%");
			})
			->orderBy('Description')
			->limit(50)
			->get()
			->map(function ($designDescription) {
				return [
					'id' => $designDescription->DesignDescriptionId,
					'name' => $designDescription->Description,
				];
			});

		return response()->json($designDescriptions);
	}

	public function getSpecials(Request $request)
	{
		$query = $request->input('query', '');

		$specials = Special::query()
			->select(['SpecialId', 'Description'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Description', 'like', "%{$query}%");
			})
			->orderBy('Description')
			->limit(50)
			->get()
			->map(function ($special) {
				return [
					'id' => $special->SpecialId,
					'name' => $special->Description,
				];
			});

		return response()->json($specials);
	}

	public function createLeather(Request $request)
	{
		$validated = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
			'Supplier' => ['nullable', 'string', 'max:255'],
		]);

		$leather = Leather::create([
			'Name' => $validated['Name'],
			'Supplier' => $validated['Supplier'] ?? '',
		]);

		return response()->json([
			'id' => $leather->LeatherId,
			'name' => $leather->Name,
		]);
	}

	public function createColour(Request $request)
	{
		$validated = $request->validate([
			'ColourName' => ['required', 'string', 'max:255'],
			'ColourObjectId' => ['required', 'integer', 'exists:colourobject,ColourObjectId'],
		]);

		$colour = Colour::create([
			'ColourName' => $validated['ColourName'],
			'ColourObjectId' => $validated['ColourObjectId'],
		]);

		return response()->json([
			'id' => $colour->ColourId,
			'name' => $colour->ColourName,
		]);
	}

	public function createLining(Request $request)
	{
		$validated = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		$lining = Lining::create([
			'Name' => $validated['Name'],
		]);

		return response()->json([
			'id' => $lining->LiningId,
			'name' => $lining->Name,
		]);
	}

	public function createDesignName(Request $request)
	{
		$validated = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		$designName = DesignName::create([
			'Name' => $validated['Name'],
		]);

		return response()->json([
			'id' => $designName->DesignNameId,
			'name' => $designName->Name,
		]);
	}

	public function createDesignDescription(Request $request)
	{
		$validated = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$designDescription = DesignDescription::create([
			'Description' => $validated['Description'],
		]);

		return response()->json([
			'id' => $designDescription->DesignDescriptionId,
			'name' => $designDescription->Description,
		]);
	}

	public function createSpecial(Request $request)
	{
		$validated = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$special = Special::create([
			'Description' => $validated['Description'],
		]);

		return response()->json([
			'id' => $special->SpecialId,
			'name' => $special->Description,
		]);
	}

	public function getLoopBands(Request $request)
	{
		$query = $request->input('query', '');

		$loopBands = LoopBand::query()
			->select(['LoopBandId', 'LoopBandSize'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('LoopBandSize', 'like', "%{$query}%");
			})
			->orderBy('LoopBandSize')
			->limit(50)
			->get()
			->map(function ($loopBand) {
				return [
					'id' => $loopBand->LoopBandId,
					'name' => $loopBand->LoopBandSize,
				];
			});

		return response()->json($loopBands);
	}

	public function getEndWidths(Request $request)
	{
		$query = $request->input('query', '');

		$endWidths = EndWidth::query()
			->select(['EndWidthId', 'EndWidthSize'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('EndWidthSize', 'like', "%{$query}%");
			})
			->orderBy('EndWidthSize')
			->limit(50)
			->get()
			->map(function ($endWidth) {
				return [
					'id' => $endWidth->EndWidthId,
					'name' => $endWidth->EndWidthSize,
				];
			});

		return response()->json($endWidths);
	}

	public function getBuckles(Request $request)
	{
		$query = $request->input('query', '');

		$buckles = Buckle::query()
			->select(['BuckleId', 'BuckleType'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('BuckleType', 'like', "%{$query}%");
			})
			->orderBy('BuckleType')
			->limit(50)
			->get()
			->map(function ($buckle) {
				return [
					'id' => $buckle->BuckleId,
					'name' => $buckle->BuckleType,
				];
			});

		return response()->json($buckles);
	}

	public function getBuckleWidths(Request $request)
	{
		$query = $request->input('query', '');

		$buckleWidths = BuckleWidth::query()
			->select(['BuckleWidthId', 'BuckleWidthSize'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('BuckleWidthSize', 'like', "%{$query}%");
			})
			->orderBy('BuckleWidthSize')
			->limit(50)
			->get()
			->map(function ($buckleWidth) {
				return [
					'id' => $buckleWidth->BuckleWidthId,
					'name' => $buckleWidth->BuckleWidthSize,
				];
			});

		return response()->json($buckleWidths);
	}

	public function createLoopBand(Request $request)
	{
		$validated = $request->validate([
			'LoopBandSize' => ['required', 'string', 'max:255'],
		]);

		$loopBand = LoopBand::create([
			'LoopBandSize' => $validated['LoopBandSize'],
		]);

		return response()->json([
			'id' => $loopBand->LoopBandId,
			'name' => $loopBand->LoopBandSize,
		]);
	}

	public function createEndWidth(Request $request)
	{
		$validated = $request->validate([
			'EndWidthSize' => ['required', 'string', 'max:255'],
		]);

		$endWidth = EndWidth::create([
			'EndWidthSize' => $validated['EndWidthSize'],
		]);

		return response()->json([
			'id' => $endWidth->EndWidthId,
			'name' => $endWidth->EndWidthSize,
		]);
	}

	public function createBuckle(Request $request)
	{
		$validated = $request->validate([
			'BuckleType' => ['required', 'string', 'max:255'],
		]);

		$buckle = Buckle::create([
			'BuckleType' => $validated['BuckleType'],
		]);

		return response()->json([
			'id' => $buckle->BuckleId,
			'name' => $buckle->BuckleType,
		]);
	}

	public function createBuckleWidth(Request $request)
	{
		$validated = $request->validate([
			'BuckleWidthSize' => ['required', 'string', 'max:255'],
		]);

		$buckleWidth = BuckleWidth::create([
			'BuckleWidthSize' => $validated['BuckleWidthSize'],
		]);

		return response()->json([
			'id' => $buckleWidth->BuckleWidthId,
			'name' => $buckleWidth->BuckleWidthSize,
		]);
	}

	public function getStrapLengthShortParts(Request $request)
	{
		$query = $request->input('query', '');

		$strapLengthShortParts = StrapLengthShortPart::query()
			->select(['StrapLengthShortPartId', 'ShortPart'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('ShortPart', 'like', "%{$query}%");
			})
			->orderBy('ShortPart')
			->limit(50)
			->get()
			->map(function ($strapLengthShortPart) {
				return [
					'id' => $strapLengthShortPart->StrapLengthShortPartId,
					'name' => $strapLengthShortPart->ShortPart,
				];
			});

		return response()->json($strapLengthShortParts);
	}

	public function getStrapLengthLongParts(Request $request)
	{
		$query = $request->input('query', '');

		$strapLengthLongParts = StrapLengthLongPart::query()
			->select(['StrapLengthLongPartId', 'LongPart'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('LongPart', 'like', "%{$query}%");
			})
			->orderBy('LongPart')
			->limit(50)
			->get()
			->map(function ($strapLengthLongPart) {
				return [
					'id' => $strapLengthLongPart->StrapLengthLongPartId,
					'name' => $strapLengthLongPart->LongPart,
				];
			});

		return response()->json($strapLengthLongParts);
	}

	public function getTopThreadColours(Request $request)
	{
		$query = $request->input('query', '');

		$colours = Colour::query()
			->select(['ColourId', 'ColourName'])
			->where('ColourObjectId', 3)
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('ColourName', 'like', "%{$query}%");
			})
			->orderBy('ColourName')
			->limit(50)
			->get()
			->map(function ($colour) {
				return [
					'id' => $colour->ColourId,
					'name' => $colour->ColourName,
				];
			});

		return response()->json($colours);
	}

	public function getBottomThreadColours(Request $request)
	{
		$query = $request->input('query', '');

		$colours = Colour::query()
			->select(['ColourId', 'ColourName'])
			->where('ColourObjectId', 3)
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('ColourName', 'like', "%{$query}%");
			})
			->orderBy('ColourName')
			->limit(50)
			->get()
			->map(function ($colour) {
				return [
					'id' => $colour->ColourId,
					'name' => $colour->ColourName,
				];
			});

		return response()->json($colours);
	}

	public function getTopThreadSizes(Request $request)
	{
		$query = $request->input('query', '');

		$threadSizes = ThreadSize::query()
			->select(['ThreadSizeId', 'ThreadSize'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('ThreadSize', 'like', "%{$query}%");
			})
			->orderBy('ThreadSize')
			->limit(50)
			->get()
			->map(function ($threadSize) {
				return [
					'id' => $threadSize->ThreadSizeId,
					'name' => $threadSize->ThreadSize,
				];
			});

		return response()->json($threadSizes);
	}

	public function getBottomThreadSizes(Request $request)
	{
		$query = $request->input('query', '');

		$threadSizes = ThreadSize::query()
			->select(['ThreadSizeId', 'ThreadSize'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('ThreadSize', 'like', "%{$query}%");
			})
			->orderBy('ThreadSize')
			->limit(50)
			->get()
			->map(function ($threadSize) {
				return [
					'id' => $threadSize->ThreadSizeId,
					'name' => $threadSize->ThreadSize,
				];
			});

		return response()->json($threadSizes);
	}

	public function getStampingShorts(Request $request)
	{
		$query = $request->input('query', '');

		$stampingShorts = StampingShort::query()
			->select(['StampingShortId', 'Description'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Description', 'like', "%{$query}%");
			})
			->orderBy('Description')
			->limit(50)
			->get()
			->map(function ($stampingShort) {
				return [
					'id' => $stampingShort->StampingShortId,
					'name' => $stampingShort->Description,
				];
			});

		return response()->json($stampingShorts);
	}

	public function getStampingLongs(Request $request)
	{
		$query = $request->input('query', '');

		$stampingLongs = StampingLong::query()
			->select(['StampingLongId', 'Description'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Description', 'like', "%{$query}%");
			})
			->orderBy('Description')
			->limit(50)
			->get()
			->map(function ($stampingLong) {
				return [
					'id' => $stampingLong->StampingLongId,
					'name' => $stampingLong->Description,
				];
			});

		return response()->json($stampingLongs);
	}

	public function createStrapLengthShortPart(Request $request)
	{
		$validated = $request->validate([
			'ShortPart' => ['required', 'string', 'max:255'],
		]);

		$strapLengthShortPart = StrapLengthShortPart::create([
			'ShortPart' => $validated['ShortPart'],
		]);

		return response()->json([
			'id' => $strapLengthShortPart->StrapLengthShortPartId,
			'name' => $strapLengthShortPart->ShortPart,
		]);
	}

	public function createStrapLengthLongPart(Request $request)
	{
		$validated = $request->validate([
			'LongPart' => ['required', 'string', 'max:255'],
		]);

		$strapLengthLongPart = StrapLengthLongPart::create([
			'LongPart' => $validated['LongPart'],
		]);

		return response()->json([
			'id' => $strapLengthLongPart->StrapLengthLongPartId,
			'name' => $strapLengthLongPart->LongPart,
		]);
	}

	public function createThreadSize(Request $request)
	{
		$validated = $request->validate([
			'ThreadSize' => ['required', 'string', 'max:255'],
		]);

		$threadSize = ThreadSize::create([
			'ThreadSize' => $validated['ThreadSize'],
		]);

		return response()->json([
			'id' => $threadSize->ThreadSizeId,
			'name' => $threadSize->ThreadSize,
		]);
	}

	public function createStampingShort(Request $request)
	{
		$validated = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$stampingShort = StampingShort::create([
			'Description' => $validated['Description'],
		]);

		return response()->json([
			'id' => $stampingShort->StampingShortId,
			'name' => $stampingShort->Description,
		]);
	}

	public function createStampingLong(Request $request)
	{
		$validated = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$stampingLong = StampingLong::create([
			'Description' => $validated['Description'],
		]);

		return response()->json([
			'id' => $stampingLong->StampingLongId,
			'name' => $stampingLong->Description,
		]);
	}

	public function getNoteInfoLine1s(Request $request)
	{
		$query = $request->input('query', '');

		$noteInfoLine1s = NoteInfoLine1::query()
			->select(['NoteInfoLine1Id', 'Line1'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Line1', 'like', "%{$query}%");
			})
			->orderBy('Line1')
			->limit(50)
			->get()
			->map(function ($noteInfoLine1) {
				return [
					'id' => $noteInfoLine1->NoteInfoLine1Id,
					'name' => $noteInfoLine1->Line1,
				];
			});

		return response()->json($noteInfoLine1s);
	}

	public function getNoteInfoLine2s(Request $request)
	{
		$query = $request->input('query', '');

		$noteInfoLine2s = NoteInfoLine2::query()
			->select(['NoteInfoLine2Id', 'Line2'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Line2', 'like', "%{$query}%");
			})
			->orderBy('Line2')
			->limit(50)
			->get()
			->map(function ($noteInfoLine2) {
				return [
					'id' => $noteInfoLine2->NoteInfoLine2Id,
					'name' => $noteInfoLine2->Line2,
				];
			});

		return response()->json($noteInfoLine2s);
	}

	public function getTools(Request $request)
	{
		$query = $request->input('query', '');

		$tools = Tool::query()
			->select(['ToolId', 'Name'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Name', 'like', "%{$query}%");
			})
			->orderBy('Name')
			->limit(50)
			->get()
			->map(function ($tool) {
				return [
					'id' => $tool->ToolId,
					'name' => $tool->Name,
				];
			});

		return response()->json($tools);
	}

	public function getStrapPictures(Request $request)
	{
		$query = $request->input('query', '');

		$strapPictures = StrapPicture::query()
			->select(['StrapPictureId', 'Name'])
			->when($query && strlen($query) >= 2, function ($q) use ($query) {
				$q->where('Name', 'like', "%{$query}%");
			})
			->orderBy('Name')
			->limit(50)
			->get()
			->map(function ($strapPicture) {
				return [
					'id' => $strapPicture->StrapPictureId,
					'name' => $strapPicture->Name,
				];
			});

		return response()->json($strapPictures);
	}

	public function createNoteInfoLine1(Request $request)
	{
		$validated = $request->validate([
			'Line1' => ['required', 'string', 'max:255'],
		]);

		$noteInfoLine1 = NoteInfoLine1::create([
			'Line1' => $validated['Line1'],
		]);

		return response()->json([
			'id' => $noteInfoLine1->NoteInfoLine1Id,
			'name' => $noteInfoLine1->Line1,
		]);
	}

	public function createNoteInfoLine2(Request $request)
	{
		$validated = $request->validate([
			'Line2' => ['required', 'string', 'max:255'],
		]);

		$noteInfoLine2 = NoteInfoLine2::create([
			'Line2' => $validated['Line2'],
		]);

		return response()->json([
			'id' => $noteInfoLine2->NoteInfoLine2Id,
			'name' => $noteInfoLine2->Line2,
		]);
	}

	public function createTool(Request $request)
	{
		$validated = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		$tool = Tool::create([
			'Name' => $validated['Name'],
		]);

		return response()->json([
			'id' => $tool->ToolId,
			'name' => $tool->Name,
		]);
	}

	public function uploadStrapPicture(Request $request)
	{
		$validated = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
			// Align with CreateClientModel client-side limit (10MB); max is in kilobytes
			'picture' => ['required', 'image', 'max:10240'],
		]);

		// Handle file upload
		if ($request->hasFile('picture')) {
			$file = $request->file('picture');
			$filename = $validated['Name'] . '.' . $file->getClientOriginalExtension();
			$file->storeAs('private/strappictures', $filename);
			$validated['Name'] = $filename;
		}

		$strapPicture = StrapPicture::create([
			'Name' => $validated['Name'],
		]);

		return response()->json([
			'id' => $strapPicture->StrapPictureId,
			'name' => $strapPicture->Name,
		]);
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'ClientId' => ['required', 'integer', 'exists:clients,id'],
			'ModelNo' => ['required', 'string', 'max:255', 'unique:clientmodel,ModelNo'],
			'RefNumber' => ['required', 'string', 'max:255'],
			'DescriptionOnInvoice' => ['nullable', 'string', 'max:500'],
			'OemReference' => ['nullable', 'string', 'max:255'],
			'LeatherId' => ['nullable', 'integer'],
			'LeatherColourId' => ['nullable', 'integer'],
			'LiningId' => ['nullable', 'integer'],
			'SpecialId1' => ['nullable', 'integer'],
			'SpecialId2' => ['nullable', 'integer'],
			'LoopBandId' => ['nullable', 'integer'],
			'EndWidthId' => ['nullable', 'integer'],
			'BuckleId' => ['nullable', 'integer'],
			'BuckleWidthId' => ['nullable', 'integer'],
			'DesignNameId' => ['nullable', 'integer'],
			'DesignDescriptionId' => ['nullable', 'integer'],
			'VarnishColourId' => ['nullable', 'integer'],
			'TopThreadColourId' => ['nullable', 'integer'],
			'BottomThreadColourId' => ['nullable', 'integer'],
			'TopThreadSizeId' => ['nullable', 'integer'],
			'BottomThreadSizeId' => ['nullable', 'integer'],
			'StampingShortId' => ['nullable', 'integer'],
			'StampingLongId' => ['nullable', 'integer'],
			'StrapPictureId' => ['nullable', 'integer'],
			'NoteInfoLine1Id' => ['nullable', 'integer'],
			'NoteInfoLine2Id' => ['nullable', 'integer'],
			'StrapLengthLongPartId' => ['nullable', 'integer'],
			'StrapLengthShortPartId' => ['nullable', 'integer'],
			'FirstCuttingToolId' => ['nullable', 'integer'],
			'InsideCuttingToolId' => ['nullable', 'integer'],
			'LoopCuttingToolId' => ['nullable', 'integer'],
			'LoopFoldingToolId' => ['nullable', 'integer'],
			'ReinforceType' => ['nullable', 'integer', 'in:0,1'],
			'BigLoop' => ['nullable', 'integer'],
			'SmallLoop' => ['nullable', 'integer'],
			'BigLoopQty' => ['nullable', 'integer'],
			'SmallLoopQty' => ['nullable', 'integer'],
			'status' => ['nullable', 'integer', 'in:1,2,3'], // 1=Draft, 2=Finalized/In Production, 3=Completed
		]);

		$clientModel = ClientModel::create($validated);

		return redirect()
			->route('admin.order-management.model-template')
			->with('success', 'Client Model created successfully.');
	}

	public function storeDraft(Request $request)
	{
		// Minimal validation - only check data types, not required fields
		$validated = $request->validate([
			'ClientId' => ['nullable', 'integer'],
			'ModelNo' => ['nullable', 'string', 'max:255'],
			'RefNumber' => ['nullable', 'string', 'max:255'],
			'DescriptionOnInvoice' => ['nullable', 'string', 'max:500'],
			'OemReference' => ['nullable', 'string', 'max:255'],
			'LeatherId' => ['nullable', 'integer'],
			'LeatherColourId' => ['nullable', 'integer'],
			'LiningId' => ['nullable', 'integer'],
			'SpecialId1' => ['nullable', 'integer'],
			'SpecialId2' => ['nullable', 'integer'],
			'LoopBandId' => ['nullable', 'integer'],
			'EndWidthId' => ['nullable', 'integer'],
			'BuckleId' => ['nullable', 'integer'],
			'BuckleWidthId' => ['nullable', 'integer'],
			'DesignNameId' => ['nullable', 'integer'],
			'DesignDescriptionId' => ['nullable', 'integer'],
			'VarnishColourId' => ['nullable', 'integer'],
			'TopThreadColourId' => ['nullable', 'integer'],
			'BottomThreadColourId' => ['nullable', 'integer'],
			'TopThreadSizeId' => ['nullable', 'integer'],
			'BottomThreadSizeId' => ['nullable', 'integer'],
			'StampingShortId' => ['nullable', 'integer'],
			'StampingLongId' => ['nullable', 'integer'],
			'StrapPictureId' => ['nullable', 'integer'],
			'NoteInfoLine1Id' => ['nullable', 'integer'],
			'NoteInfoLine2Id' => ['nullable', 'integer'],
			'StrapLengthLongPartId' => ['nullable', 'integer'],
			'StrapLengthShortPartId' => ['nullable', 'integer'],
			'FirstCuttingToolId' => ['nullable', 'integer'],
			'InsideCuttingToolId' => ['nullable', 'integer'],
			'LoopCuttingToolId' => ['nullable', 'integer'],
			'LoopFoldingToolId' => ['nullable', 'integer'],
			'ReinforceType' => ['nullable', 'integer', 'in:0,1'],
			'BigLoop' => ['nullable', 'integer'],
			'SmallLoop' => ['nullable', 'integer'],
			'BigLoopQty' => ['nullable', 'integer'],
			'SmallLoopQty' => ['nullable', 'integer'],
			'status' => ['nullable', 'integer', 'in:1,2,3'], // 1=Draft, 2=Finalized/In Production, 3=Completed
		]);

		$clientModel = ClientModel::create($validated);

		return back()->with('success', 'Draft saved successfully.');
	}

	public function show(ClientModel $clientmodel)
	{
		// Load client with buyer relationship
		$clientmodel->load('client.buyer');
		$client = $clientmodel->client;
		$buyer = $client ? $client->buyer : null;

		// Prepare related entities data
		$data = [
			'clientmodel' => [
				'clientmodelId' => $clientmodel->clientmodelId,
				'ModelNo' => $clientmodel->ModelNo,
				'RefNumber' => $clientmodel->RefNumber,
				'ReinforceType' => $clientmodel->ReinforceType,
				'BigLoop' => $clientmodel->BigLoop,
				'SmallLoop' => $clientmodel->SmallLoop,
				'BigLoopQty' => $clientmodel->BigLoopQty,
				'SmallLoopQty' => $clientmodel->SmallLoopQty,
			],
			'client' => $client ? ['id' => $client->id, 'name' => $client->client_name] : null,
			'buyer' => $buyer ? ['id' => $buyer->id, 'name' => $buyer->buyer_name] : null,
		];

		// Leather
		if ($clientmodel->LeatherId) {
			$leather = Leather::find($clientmodel->LeatherId);
			$data['leather'] = $leather ? ['id' => $leather->LeatherId, 'name' => $leather->Name] : null;
		} else {
			$data['leather'] = null;
		}

		// Leather Colour
		if ($clientmodel->LeatherColourId) {
			$leatherColour = Colour::find($clientmodel->LeatherColourId);
			$data['leatherColour'] = $leatherColour ? ['id' => $leatherColour->ColourId, 'name' => $leatherColour->ColourName] : null;
		} else {
			$data['leatherColour'] = null;
		}

		// Lining
		if ($clientmodel->LiningId) {
			$lining = Lining::find($clientmodel->LiningId);
			$data['lining'] = $lining ? ['id' => $lining->LiningId, 'name' => $lining->Name] : null;
		} else {
			$data['lining'] = null;
		}

		// Varnish Colour
		if ($clientmodel->VarnishColourId) {
			$varnishColour = Colour::find($clientmodel->VarnishColourId);
			$data['varnishColour'] = $varnishColour ? ['id' => $varnishColour->ColourId, 'name' => $varnishColour->ColourName] : null;
		} else {
			$data['varnishColour'] = null;
		}

		// Design Name
		if ($clientmodel->DesignNameId) {
			$designName = DesignName::find($clientmodel->DesignNameId);
			$data['designName'] = $designName ? ['id' => $designName->DesignNameId, 'name' => $designName->Name] : null;
		} else {
			$data['designName'] = null;
		}

		// Design Description
		if ($clientmodel->DesignDescriptionId) {
			$designDescription = DesignDescription::find($clientmodel->DesignDescriptionId);
			$data['designDescription'] = $designDescription ? ['id' => $designDescription->DesignDescriptionId, 'name' => $designDescription->Description] : null;
		} else {
			$data['designDescription'] = null;
		}

		// Special 1
		if ($clientmodel->SpecialId1) {
			$special1 = Special::find($clientmodel->SpecialId1);
			$data['special1'] = $special1 ? ['id' => $special1->SpecialId, 'name' => $special1->Description] : null;
		} else {
			$data['special1'] = null;
		}

		// Special 2
		if ($clientmodel->SpecialId2) {
			$special2 = Special::find($clientmodel->SpecialId2);
			$data['special2'] = $special2 ? ['id' => $special2->SpecialId, 'name' => $special2->Description] : null;
		} else {
			$data['special2'] = null;
		}

		// Loop Band
		if ($clientmodel->LoopBandId) {
			$loopBand = LoopBand::find($clientmodel->LoopBandId);
			$data['loopBand'] = $loopBand ? ['id' => $loopBand->LoopBandId, 'name' => $loopBand->LoopBandSize] : null;
		} else {
			$data['loopBand'] = null;
		}

		// End Width
		if ($clientmodel->EndWidthId) {
			$endWidth = EndWidth::find($clientmodel->EndWidthId);
			$data['endWidth'] = $endWidth ? ['id' => $endWidth->EndWidthId, 'name' => $endWidth->EndWidthSize] : null;
		} else {
			$data['endWidth'] = null;
		}

		// Buckle
		if ($clientmodel->BuckleId) {
			$buckle = Buckle::find($clientmodel->BuckleId);
			$data['buckle'] = $buckle ? ['id' => $buckle->BuckleId, 'name' => $buckle->BuckleType] : null;
		} else {
			$data['buckle'] = null;
		}

		// Buckle Width
		if ($clientmodel->BuckleWidthId) {
			$buckleWidth = BuckleWidth::find($clientmodel->BuckleWidthId);
			$data['buckleWidth'] = $buckleWidth ? ['id' => $buckleWidth->BuckleWidthId, 'name' => $buckleWidth->BuckleWidthSize] : null;
		} else {
			$data['buckleWidth'] = null;
		}

		// Strap Length Short Part
		if ($clientmodel->StrapLengthShortPartId) {
			$strapLengthShortPart = StrapLengthShortPart::find($clientmodel->StrapLengthShortPartId);
			$data['strapLengthShortPart'] = $strapLengthShortPart ? ['id' => $strapLengthShortPart->StrapLengthShortPartId, 'name' => $strapLengthShortPart->ShortPart] : null;
		} else {
			$data['strapLengthShortPart'] = null;
		}

		// Strap Length Long Part
		if ($clientmodel->StrapLengthLongPartId) {
			$strapLengthLongPart = StrapLengthLongPart::find($clientmodel->StrapLengthLongPartId);
			$data['strapLengthLongPart'] = $strapLengthLongPart ? ['id' => $strapLengthLongPart->StrapLengthLongPartId, 'name' => $strapLengthLongPart->LongPart] : null;
		} else {
			$data['strapLengthLongPart'] = null;
		}

		// Top Thread Colour
		if ($clientmodel->TopThreadColourId) {
			$topThreadColour = Colour::find($clientmodel->TopThreadColourId);
			$data['topThreadColour'] = $topThreadColour ? ['id' => $topThreadColour->ColourId, 'name' => $topThreadColour->ColourName] : null;
		} else {
			$data['topThreadColour'] = null;
		}

		// Bottom Thread Colour
		if ($clientmodel->BottomThreadColourId) {
			$bottomThreadColour = Colour::find($clientmodel->BottomThreadColourId);
			$data['bottomThreadColour'] = $bottomThreadColour ? ['id' => $bottomThreadColour->ColourId, 'name' => $bottomThreadColour->ColourName] : null;
		} else {
			$data['bottomThreadColour'] = null;
		}

		// Top Thread Size
		if ($clientmodel->TopThreadSizeId) {
			$topThreadSize = ThreadSize::find($clientmodel->TopThreadSizeId);
			$data['topThreadSize'] = $topThreadSize ? ['id' => $topThreadSize->ThreadSizeId, 'name' => $topThreadSize->ThreadSize] : null;
		} else {
			$data['topThreadSize'] = null;
		}

		// Bottom Thread Size
		if ($clientmodel->BottomThreadSizeId) {
			$bottomThreadSize = ThreadSize::find($clientmodel->BottomThreadSizeId);
			$data['bottomThreadSize'] = $bottomThreadSize ? ['id' => $bottomThreadSize->ThreadSizeId, 'name' => $bottomThreadSize->ThreadSize] : null;
		} else {
			$data['bottomThreadSize'] = null;
		}

		// Stamping Short
		if ($clientmodel->StampingShortId) {
			$stampingShort = StampingShort::find($clientmodel->StampingShortId);
			$data['stampingShort'] = $stampingShort ? ['id' => $stampingShort->StampingShortId, 'name' => $stampingShort->Description] : null;
		} else {
			$data['stampingShort'] = null;
		}

		// Stamping Long
		if ($clientmodel->StampingLongId) {
			$stampingLong = StampingLong::find($clientmodel->StampingLongId);
			$data['stampingLong'] = $stampingLong ? ['id' => $stampingLong->StampingLongId, 'name' => $stampingLong->Description] : null;
		} else {
			$data['stampingLong'] = null;
		}

		// Strap Picture
		if ($clientmodel->StrapPictureId) {
			$strapPicture = StrapPicture::find($clientmodel->StrapPictureId);
			$data['strapPicture'] = $strapPicture ? ['id' => $strapPicture->StrapPictureId, 'name' => $strapPicture->Name] : null;
		} else {
			$data['strapPicture'] = null;
		}

		// Note Info Line 1
		if ($clientmodel->NoteInfoLine1Id) {
			$noteInfoLine1 = NoteInfoLine1::find($clientmodel->NoteInfoLine1Id);
			$data['noteInfoLine1'] = $noteInfoLine1 ? ['id' => $noteInfoLine1->NoteInfoLine1Id, 'name' => $noteInfoLine1->Line1] : null;
		} else {
			$data['noteInfoLine1'] = null;
		}

		// Note Info Line 2
		if ($clientmodel->NoteInfoLine2Id) {
			$noteInfoLine2 = NoteInfoLine2::find($clientmodel->NoteInfoLine2Id);
			$data['noteInfoLine2'] = $noteInfoLine2 ? ['id' => $noteInfoLine2->NoteInfoLine2Id, 'name' => $noteInfoLine2->Line2] : null;
		} else {
			$data['noteInfoLine2'] = null;
		}

		// First Cutting Tool
		if ($clientmodel->FirstCuttingToolId) {
			$firstCuttingTool = Tool::find($clientmodel->FirstCuttingToolId);
			$data['firstCuttingTool'] = $firstCuttingTool ? ['id' => $firstCuttingTool->ToolId, 'name' => $firstCuttingTool->Name] : null;
		} else {
			$data['firstCuttingTool'] = null;
		}

		// Inside Cutting Tool
		if ($clientmodel->InsideCuttingToolId) {
			$insideCuttingTool = Tool::find($clientmodel->InsideCuttingToolId);
			$data['insideCuttingTool'] = $insideCuttingTool ? ['id' => $insideCuttingTool->ToolId, 'name' => $insideCuttingTool->Name] : null;
		} else {
			$data['insideCuttingTool'] = null;
		}

		// Loop Cutting Tool
		if ($clientmodel->LoopCuttingToolId) {
			$loopCuttingTool = Tool::find($clientmodel->LoopCuttingToolId);
			$data['loopCuttingTool'] = $loopCuttingTool ? ['id' => $loopCuttingTool->ToolId, 'name' => $loopCuttingTool->Name] : null;
		} else {
			$data['loopCuttingTool'] = null;
		}

		// Loop Folding Tool
		if ($clientmodel->LoopFoldingToolId) {
			$loopFoldingTool = Tool::find($clientmodel->LoopFoldingToolId);
			$data['loopFoldingTool'] = $loopFoldingTool ? ['id' => $loopFoldingTool->ToolId, 'name' => $loopFoldingTool->Name] : null;
		} else {
			$data['loopFoldingTool'] = null;
		}

		return response()->json($data);
	}

	public function edit(ClientModel $clientmodel): Response
	{
		// Load client with buyer relationship
		$clientmodel->load('client.buyer');
		$client = $clientmodel->client;
		$buyer = $client ? $client->buyer : null;

		// Prepare related entities data
		$relatedData = [];

		// Leather
		if ($clientmodel->LeatherId) {
			$leather = Leather::find($clientmodel->LeatherId);
			$relatedData['leather'] = $leather ? ['id' => $leather->LeatherId, 'name' => $leather->Name] : null;
		}

		// Leather Colour
		if ($clientmodel->LeatherColourId) {
			$leatherColour = Colour::find($clientmodel->LeatherColourId);
			$relatedData['leatherColour'] = $leatherColour ? ['id' => $leatherColour->ColourId, 'name' => $leatherColour->ColourName] : null;
		}

		// Lining
		if ($clientmodel->LiningId) {
			$lining = Lining::find($clientmodel->LiningId);
			$relatedData['lining'] = $lining ? ['id' => $lining->LiningId, 'name' => $lining->Name] : null;
		}

		// Varnish Colour
		if ($clientmodel->VarnishColourId) {
			$varnishColour = Colour::find($clientmodel->VarnishColourId);
			$relatedData['varnishColour'] = $varnishColour ? ['id' => $varnishColour->ColourId, 'name' => $varnishColour->ColourName] : null;
		}

		// Design Name
		if ($clientmodel->DesignNameId) {
			$designName = DesignName::find($clientmodel->DesignNameId);
			$relatedData['designName'] = $designName ? ['id' => $designName->DesignNameId, 'name' => $designName->Name] : null;
		}

		// Design Description
		if ($clientmodel->DesignDescriptionId) {
			$designDescription = DesignDescription::find($clientmodel->DesignDescriptionId);
			$relatedData['designDescription'] = $designDescription ? ['id' => $designDescription->DesignDescriptionId, 'name' => $designDescription->Description] : null;
		}

		// Special 1
		if ($clientmodel->SpecialId1) {
			$special1 = Special::find($clientmodel->SpecialId1);
			$relatedData['special1'] = $special1 ? ['id' => $special1->SpecialId, 'name' => $special1->Description] : null;
		}

		// Special 2
		if ($clientmodel->SpecialId2) {
			$special2 = Special::find($clientmodel->SpecialId2);
			$relatedData['special2'] = $special2 ? ['id' => $special2->SpecialId, 'name' => $special2->Description] : null;
		}

		// Loop Band
		if ($clientmodel->LoopBandId) {
			$loopBand = LoopBand::find($clientmodel->LoopBandId);
			$relatedData['loopBand'] = $loopBand ? ['id' => $loopBand->LoopBandId, 'name' => $loopBand->LoopBandSize] : null;
		}

		// End Width
		if ($clientmodel->EndWidthId) {
			$endWidth = EndWidth::find($clientmodel->EndWidthId);
			$relatedData['endWidth'] = $endWidth ? ['id' => $endWidth->EndWidthId, 'name' => $endWidth->EndWidthSize] : null;
		}

		// Buckle
		if ($clientmodel->BuckleId) {
			$buckle = Buckle::find($clientmodel->BuckleId);
			$relatedData['buckle'] = $buckle ? ['id' => $buckle->BuckleId, 'name' => $buckle->BuckleType] : null;
		}

		// Buckle Width
		if ($clientmodel->BuckleWidthId) {
			$buckleWidth = BuckleWidth::find($clientmodel->BuckleWidthId);
			$relatedData['buckleWidth'] = $buckleWidth ? ['id' => $buckleWidth->BuckleWidthId, 'name' => $buckleWidth->BuckleWidthSize] : null;
		}

		// Strap Length Short Part
		if ($clientmodel->StrapLengthShortPartId) {
			$strapLengthShortPart = StrapLengthShortPart::find($clientmodel->StrapLengthShortPartId);
			$relatedData['strapLengthShortPart'] = $strapLengthShortPart ? ['id' => $strapLengthShortPart->StrapLengthShortPartId, 'name' => $strapLengthShortPart->ShortPart] : null;
		}

		// Strap Length Long Part
		if ($clientmodel->StrapLengthLongPartId) {
			$strapLengthLongPart = StrapLengthLongPart::find($clientmodel->StrapLengthLongPartId);
			$relatedData['strapLengthLongPart'] = $strapLengthLongPart ? ['id' => $strapLengthLongPart->StrapLengthLongPartId, 'name' => $strapLengthLongPart->LongPart] : null;
		}

		// Top Thread Colour
		if ($clientmodel->TopThreadColourId) {
			$topThreadColour = Colour::find($clientmodel->TopThreadColourId);
			$relatedData['topThreadColour'] = $topThreadColour ? ['id' => $topThreadColour->ColourId, 'name' => $topThreadColour->ColourName] : null;
		}

		// Bottom Thread Colour
		if ($clientmodel->BottomThreadColourId) {
			$bottomThreadColour = Colour::find($clientmodel->BottomThreadColourId);
			$relatedData['bottomThreadColour'] = $bottomThreadColour ? ['id' => $bottomThreadColour->ColourId, 'name' => $bottomThreadColour->ColourName] : null;
		}

		// Top Thread Size
		if ($clientmodel->TopThreadSizeId) {
			$topThreadSize = ThreadSize::find($clientmodel->TopThreadSizeId);
			$relatedData['topThreadSize'] = $topThreadSize ? ['id' => $topThreadSize->ThreadSizeId, 'name' => $topThreadSize->ThreadSize] : null;
		}

		// Bottom Thread Size
		if ($clientmodel->BottomThreadSizeId) {
			$bottomThreadSize = ThreadSize::find($clientmodel->BottomThreadSizeId);
			$relatedData['bottomThreadSize'] = $bottomThreadSize ? ['id' => $bottomThreadSize->ThreadSizeId, 'name' => $bottomThreadSize->ThreadSize] : null;
		}

		// Stamping Short
		if ($clientmodel->StampingShortId) {
			$stampingShort = StampingShort::find($clientmodel->StampingShortId);
			$relatedData['stampingShort'] = $stampingShort ? ['id' => $stampingShort->StampingShortId, 'name' => $stampingShort->Description] : null;
		}

		// Stamping Long
		if ($clientmodel->StampingLongId) {
			$stampingLong = StampingLong::find($clientmodel->StampingLongId);
			$relatedData['stampingLong'] = $stampingLong ? ['id' => $stampingLong->StampingLongId, 'name' => $stampingLong->Description] : null;
		}

		// Strap Picture
		if ($clientmodel->StrapPictureId) {
			$strapPicture = StrapPicture::find($clientmodel->StrapPictureId);
			$relatedData['strapPicture'] = $strapPicture ? ['id' => $strapPicture->StrapPictureId, 'name' => $strapPicture->Name] : null;
		}

		// Note Info Line 1
		if ($clientmodel->NoteInfoLine1Id) {
			$noteInfoLine1 = NoteInfoLine1::find($clientmodel->NoteInfoLine1Id);
			$relatedData['noteInfoLine1'] = $noteInfoLine1 ? ['id' => $noteInfoLine1->NoteInfoLine1Id, 'name' => $noteInfoLine1->Line1] : null;
		}

		// Note Info Line 2
		if ($clientmodel->NoteInfoLine2Id) {
			$noteInfoLine2 = NoteInfoLine2::find($clientmodel->NoteInfoLine2Id);
			$relatedData['noteInfoLine2'] = $noteInfoLine2 ? ['id' => $noteInfoLine2->NoteInfoLine2Id, 'name' => $noteInfoLine2->Line2] : null;
		}

		// First Cutting Tool
		if ($clientmodel->FirstCuttingToolId) {
			$firstCuttingTool = Tool::find($clientmodel->FirstCuttingToolId);
			$relatedData['firstCuttingTool'] = $firstCuttingTool ? ['id' => $firstCuttingTool->ToolId, 'name' => $firstCuttingTool->Name] : null;
		}

		// Inside Cutting Tool
		if ($clientmodel->InsideCuttingToolId) {
			$insideCuttingTool = Tool::find($clientmodel->InsideCuttingToolId);
			$relatedData['insideCuttingTool'] = $insideCuttingTool ? ['id' => $insideCuttingTool->ToolId, 'name' => $insideCuttingTool->Name] : null;
		}

		// Loop Cutting Tool
		if ($clientmodel->LoopCuttingToolId) {
			$loopCuttingTool = Tool::find($clientmodel->LoopCuttingToolId);
			$relatedData['loopCuttingTool'] = $loopCuttingTool ? ['id' => $loopCuttingTool->ToolId, 'name' => $loopCuttingTool->Name] : null;
		}

		// Loop Folding Tool
		if ($clientmodel->LoopFoldingToolId) {
			$loopFoldingTool = Tool::find($clientmodel->LoopFoldingToolId);
			$relatedData['loopFoldingTool'] = $loopFoldingTool ? ['id' => $loopFoldingTool->ToolId, 'name' => $loopFoldingTool->Name] : null;
		}

		return Inertia::render('Admin/OrderManagement/CreateClientModel', [
			'clientmodel' => $clientmodel,
			'buyer' => $buyer ? ['id' => $buyer->id, 'name' => $buyer->buyer_name] : null,
			'client' => $client ? ['id' => $client->id, 'name' => $client->client_name] : null,
			...$relatedData,
		]);
	}

	public function update(Request $request, ClientModel $clientmodel)
	{
		$isDraft = $request->input('is_draft', false);

		$rules = [
			'ClientId' => ['nullable', 'integer'],
			'ModelNo' => ['nullable', 'string', 'max:255'],
			'RefNumber' => ['nullable', 'string', 'max:255'],
			'DescriptionOnInvoice' => ['nullable', 'string', 'max:500'],
			'OemReference' => ['nullable', 'string', 'max:255'],
			'LeatherId' => ['nullable', 'integer'],
			'LeatherColourId' => ['nullable', 'integer'],
			'LiningId' => ['nullable', 'integer'],
			'SpecialId1' => ['nullable', 'integer'],
			'SpecialId2' => ['nullable', 'integer'],
			'LoopBandId' => ['nullable', 'integer'],
			'EndWidthId' => ['nullable', 'integer'],
			'BuckleId' => ['nullable', 'integer'],
			'BuckleWidthId' => ['nullable', 'integer'],
			'DesignNameId' => ['nullable', 'integer'],
			'DesignDescriptionId' => ['nullable', 'integer'],
			'VarnishColourId' => ['nullable', 'integer'],
			'TopThreadColourId' => ['nullable', 'integer'],
			'BottomThreadColourId' => ['nullable', 'integer'],
			'TopThreadSizeId' => ['nullable', 'integer'],
			'BottomThreadSizeId' => ['nullable', 'integer'],
			'StampingShortId' => ['nullable', 'integer'],
			'StampingLongId' => ['nullable', 'integer'],
			'StrapPictureId' => ['nullable', 'integer'],
			'NoteInfoLine1Id' => ['nullable', 'integer'],
			'NoteInfoLine2Id' => ['nullable', 'integer'],
			'StrapLengthLongPartId' => ['nullable', 'integer'],
			'StrapLengthShortPartId' => ['nullable', 'integer'],
			'FirstCuttingToolId' => ['nullable', 'integer'],
			'InsideCuttingToolId' => ['nullable', 'integer'],
			'LoopCuttingToolId' => ['nullable', 'integer'],
			'LoopFoldingToolId' => ['nullable', 'integer'],
			'ReinforceType' => ['nullable', 'integer', 'in:0,1'],
			'BigLoop' => ['nullable', 'integer'],
			'SmallLoop' => ['nullable', 'integer'],
			'BigLoopQty' => ['nullable', 'integer'],
			'SmallLoopQty' => ['nullable', 'integer'],
			'status' => ['nullable', 'integer', 'in:1,2,3'], // 1=Draft, 2=Finalized/In Production, 3=Completed
		];

		// If submitting (not draft), add required validations
		if (!$isDraft) {
			$rules['ClientId'] = ['required', 'integer', 'exists:clients,id'];
			$rules['ModelNo'] = ['required', 'string', 'max:255', 'unique:clientmodel,ModelNo,' . $clientmodel->clientmodelId . ',clientmodelId'];
			$rules['RefNumber'] = ['required', 'string', 'max:255'];
		}

		$validated = $request->validate($rules);

		// Log the validated data for debugging
		Log::info('Updating ClientModel', [
			'clientmodelId' => $clientmodel->clientmodelId,
			'validated_data' => $validated,
			'is_draft' => $isDraft
		]);

		// Update all fields that are present in the validated data
		// Use update() to ensure all fields are updated, including null values
		$clientmodel->update($validated);

		if ($isDraft) {
			return back()->with('success', 'Draft updated successfully.');
		}

		return redirect()
			->route('admin.order-management.model-template')
			->with('success', 'Client Model updated successfully.');
	}

	public function duplicate(ClientModel $clientmodel)
	{
		// Create a new record with the same data but status "Draft" (1)
		$newModel = $clientmodel->replicate();
		$newModel->status = 1; // 1 = Draft
		$newModel->save();

		return redirect()
			->route('admin.order-management.model-template')
			->with('success', 'Client Model duplicated successfully.');
	}

	public function destroy(ClientModel $clientmodel)
	{
		// Only allow deletion if status is "Draft" or "Newly Added"
		if (!in_array($clientmodel->status, [1, 2])) {
			return redirect()
				->route('admin.order-management.model-template')
				->with('error', 'Only records with status "Draft" or "Finalized" can be deleted.');
		}

		$clientmodel->delete();

		return redirect()
			->route('admin.order-management.model-template')
			->with('success', 'Client Model deleted successfully.');
	}
}
