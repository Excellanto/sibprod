<?php

namespace App\Http\Controllers;

use App\Models\Buckle;
use App\Models\BuckleWidth;
use App\Models\Colour;
use App\Models\ColourObject;
use App\Models\Design;
use App\Models\DesignName;
use App\Models\DesignDescription;
use App\Models\EndWidth;
use App\Models\Leather;
use App\Models\Lining;
use App\Models\LoopBand;
use App\Models\NoteInfoLine1;
use App\Models\NoteInfoLine2;
use App\Models\ProdDesign;
use App\Models\Reinforce;
use App\Models\Special;
use App\Models\StampingLong;
use App\Models\StampingShort;
use App\Models\StrapLengthLongPart;
use App\Models\StrapLengthShortPart;
use App\Models\Tool;
use App\Models\StrapPicture;
use App\Models\ThreadSize;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionDataController extends Controller
{
	public function colourObject(Request $request): Response
	{
		$this->authorize('viewAny', ColourObject::class);
		$search = $request->string('search')->toString();
		$colourObjects = ColourObject::query()
			->select(['ColourObjectId', 'ColourObject'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('ColourObjectId', 'like', "%{$search}%")
						->orWhere('ColourObject', 'like', "%{$search}%");
				});
			})
			->orderByDesc('ColourObjectId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/ColourObject', [
			'activeSection' => 'colour-object',
			'colourObjects' => $colourObjects,
		]);
	}

	public function storeColourObject(Request $request)
	{
		$this->authorize('create', ColourObject::class);
		$data = $request->validate([
			'ColourObject' => ['required', 'string', 'max:255'],
		]);

		ColourObject::create($data);

		return redirect()->route('admin.production-data.colour-object')->with('success', 'Colour Object created successfully.');
	}

	public function updateColourObject(Request $request, ColourObject $colourObject)
	{
		$this->authorize('update', $colourObject);
		$data = $request->validate([
			'ColourObject' => ['required', 'string', 'max:255'],
		]);

		$colourObject->update($data);

		return redirect()->route('admin.production-data.colour-object')->with('success', 'Colour Object updated successfully.');
	}

	public function colours(Request $request): Response
	{
		$this->authorize('viewAny', Colour::class);
		$search = $request->string('search')->toString();
		$colourObjectId = $request->integer('colourObjectId', 0);
		
		$colours = Colour::query()
			->with('colourObject')
			->select(['ColourId', 'ColourName', 'ColourObjectId'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('ColourId', 'like', "%{$search}%")
						->orWhere('ColourName', 'like', "%{$search}%")
						->orWhere('ColourObjectId', 'like', "%{$search}%");
				});
			})
			->when($colourObjectId > 0, function ($q) use ($colourObjectId) {
				$q->where('ColourObjectId', $colourObjectId);
			})
			->orderByDesc('ColourId')
			->paginate(10)
			->withQueryString();

		// Get all ColourObjects for the dropdown
		$colourObjects = ColourObject::select(['ColourObjectId', 'ColourObject'])->orderBy('ColourObject')->get();

		return Inertia::render('Admin/ProductionData/Colours', [
			'activeSection' => 'colours',
			'colours' => $colours,
			'colourObjects' => $colourObjects,
			'filters' => [
				'colourObjectId' => $colourObjectId > 0 ? $colourObjectId : null,
			],
		]);
	}

	public function storeColour(Request $request)
	{
		$this->authorize('create', Colour::class);
		$data = $request->validate([
			'ColourName' => ['required', 'string', 'max:255'],
			'ColourObjectId' => ['required', 'integer', 'exists:colourobject,ColourObjectId'],
		]);

		Colour::create($data);

		return redirect()->route('admin.production-data.colours')->with('success', 'Colour created successfully.');
	}

	public function updateColour(Request $request, Colour $colour)
	{
		$this->authorize('update', $colour);
		$data = $request->validate([
			'ColourName' => ['required', 'string', 'max:255'],
			'ColourObjectId' => ['required', 'integer', 'exists:colourobject,ColourObjectId'],
		]);

		$colour->update($data);

		return redirect()->route('admin.production-data.colours')->with('success', 'Colour updated successfully.');
	}

	public function leather(Request $request): Response
	{
		$this->authorize('viewAny', Leather::class);
		$search = $request->string('search')->toString();
		$leathers = Leather::query()
			->select(['LeatherId', 'Name', 'Supplier'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('LeatherId', 'like', "%{$search}%")
						->orWhere('Name', 'like', "%{$search}%")
						->orWhere('Supplier', 'like', "%{$search}%");
				});
			})
			->orderByDesc('LeatherId')
			->paginate(10)
			->withQueryString();

		// Get unique suppliers for the dropdown
		$suppliers = Leather::select('Supplier')
			->distinct()
			->whereNotNull('Supplier')
			->where('Supplier', '!=', '')
			->orderBy('Supplier')
			->pluck('Supplier')
			->toArray();

		return Inertia::render('Admin/ProductionData/Leather', [
			'activeSection' => 'leather',
			'leathers' => $leathers,
			'suppliers' => $suppliers,
		]);
	}

	public function storeLeather(Request $request)
	{
		$this->authorize('create', Leather::class);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
			'Supplier' => ['required', 'string', 'max:255'],
		]);

		Leather::create($data);

		return redirect()->route('admin.production-data.leather')->with('success', 'Leather created successfully.');
	}

	public function updateLeather(Request $request, Leather $leather)
	{
		$this->authorize('update', $leather);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
			'Supplier' => ['required', 'string', 'max:255'],
		]);

		$leather->update($data);

		return redirect()->route('admin.production-data.leather')->with('success', 'Leather updated successfully.');
	}

	public function lining(Request $request): Response
	{
		$this->authorize('viewAny', Lining::class);
		$search = $request->string('search')->toString();
		$linings = Lining::query()
			->select(['LiningId', 'Name'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('LiningId', 'like', "%{$search}%")
						->orWhere('Name', 'like', "%{$search}%");
				});
			})
			->orderByDesc('LiningId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/Lining', [
			'activeSection' => 'lining',
			'linings' => $linings,
		]);
	}

	public function storeLining(Request $request)
	{
		$this->authorize('create', Lining::class);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		Lining::create($data);

		return redirect()->route('admin.production-data.lining')->with('success', 'Lining created successfully.');
	}

	public function updateLining(Request $request, Lining $lining)
	{
		$this->authorize('update', $lining);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		$lining->update($data);

		return redirect()->route('admin.production-data.lining')->with('success', 'Lining updated successfully.');
	}

	public function loopBand(Request $request): Response
	{
		$this->authorize('viewAny', LoopBand::class);
		$search = $request->string('search')->toString();
		$loopBands = LoopBand::query()
			->select(['LoopBandId', 'LoopBandSize'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('LoopBandId', 'like', "%{$search}%")
						->orWhere('LoopBandSize', 'like', "%{$search}%");
				});
			})
			->orderByDesc('LoopBandId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/LoopBand', [
			'activeSection' => 'loop-band',
			'loopBands' => $loopBands,
		]);
	}

	public function storeLoopBand(Request $request)
	{
		$this->authorize('create', LoopBand::class);
		$data = $request->validate([
			'LoopBandSize' => ['required', 'string', 'max:255'],
		]);

		LoopBand::create($data);

		return redirect()->route('admin.production-data.loop-band')->with('success', 'Loop Band created successfully.');
	}

	public function updateLoopBand(Request $request, LoopBand $loopBand)
	{
		$this->authorize('update', $loopBand);
		$data = $request->validate([
			'LoopBandSize' => ['required', 'string', 'max:255'],
		]);

		$loopBand->update($data);

		return redirect()->route('admin.production-data.loop-band')->with('success', 'Loop Band updated successfully.');
	}

	public function buckle(Request $request): Response
	{
		$this->authorize('viewAny', Buckle::class);
		$search = $request->string('search')->toString();
		$buckles = Buckle::query()
			->select(['BuckleId', 'BuckleType'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('BuckleId', 'like', "%{$search}%")
						->orWhere('BuckleType', 'like', "%{$search}%");
				});
			})
			->orderByDesc('BuckleId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/Buckle', [
			'activeSection' => 'buckle',
			'buckles' => $buckles,
		]);
	}

	public function storeBuckle(Request $request)
	{
		$this->authorize('create', Buckle::class);
		$data = $request->validate([
			'BuckleType' => ['required', 'string', 'max:255'],
		]);

		Buckle::create($data);

		return redirect()->route('admin.production-data.buckle')->with('success', 'Buckle created successfully.');
	}

	public function updateBuckle(Request $request, Buckle $buckle)
	{
		$this->authorize('update', $buckle);
		$data = $request->validate([
			'BuckleType' => ['required', 'string', 'max:255'],
		]);

		$buckle->update($data);

		return redirect()->route('admin.production-data.buckle')->with('success', 'Buckle updated successfully.');
	}

	public function buckleWidth(Request $request): Response
	{
		$this->authorize('viewAny', BuckleWidth::class);
		$search = $request->string('search')->toString();
		$buckleWidths = BuckleWidth::query()
			->select(['BuckleWidthId', 'BuckleWidthSize'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('BuckleWidthId', 'like', "%{$search}%")
						->orWhere('BuckleWidthSize', 'like', "%{$search}%");
				});
			})
			->orderByDesc('BuckleWidthId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/BuckleWidth', [
			'activeSection' => 'buckle-width',
			'buckleWidths' => $buckleWidths,
		]);
	}

	public function storeBuckleWidth(Request $request)
	{
		$this->authorize('create', BuckleWidth::class);
		$data = $request->validate([
			'BuckleWidthSize' => ['required', 'string', 'max:255'],
		]);

		BuckleWidth::create($data);

		return redirect()->route('admin.production-data.buckle-width')->with('success', 'Buckle Width created successfully.');
	}

	public function updateBuckleWidth(Request $request, BuckleWidth $buckleWidth)
	{
		$this->authorize('update', $buckleWidth);
		$data = $request->validate([
			'BuckleWidthSize' => ['required', 'string', 'max:255'],
		]);

		$buckleWidth->update($data);

		return redirect()->route('admin.production-data.buckle-width')->with('success', 'Buckle Width updated successfully.');
	}

	public function thread(): Response
	{
		return Inertia::render('Admin/ProductionData/Thread', [
			'activeSection' => 'thread',
		]);
	}

	public function threadSize(Request $request): Response
	{
		$this->authorize('viewAny', ThreadSize::class);
		$search = $request->string('search')->toString();
		$threadSizes = ThreadSize::query()
			->select(['ThreadSizeId', 'ThreadSize'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('ThreadSizeId', 'like', "%{$search}%")
						->orWhere('ThreadSize', 'like', "%{$search}%");
				});
			})
			->orderByDesc('ThreadSizeId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/ThreadSize', [
			'activeSection' => 'thread-size',
			'threadSizes' => $threadSizes,
		]);
	}

	public function storeThreadSize(Request $request)
	{
		$this->authorize('create', ThreadSize::class);
		$data = $request->validate([
			'ThreadSize' => ['required', 'string', 'max:255'],
		]);

		ThreadSize::create($data);

		return redirect()->route('admin.production-data.thread-size')->with('success', 'Thread Size created successfully.');
	}

	public function updateThreadSize(Request $request, ThreadSize $threadSize)
	{
		$this->authorize('update', $threadSize);
		$data = $request->validate([
			'ThreadSize' => ['required', 'string', 'max:255'],
		]);

		$threadSize->update($data);

		return redirect()->route('admin.production-data.thread-size')->with('success', 'Thread Size updated successfully.');
	}

	public function endWidth(Request $request): Response
	{
		$this->authorize('viewAny', EndWidth::class);
		$search = $request->string('search')->toString();
		$endWidths = EndWidth::query()
			->select(['EndWidthId', 'EndWidthSize'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('EndWidthId', 'like', "%{$search}%")
						->orWhere('EndWidthSize', 'like', "%{$search}%");
				});
			})
			->orderByDesc('EndWidthId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/EndWidth', [
			'activeSection' => 'end-width',
			'endWidths' => $endWidths,
		]);
	}

	public function storeEndWidth(Request $request)
	{
		$this->authorize('create', EndWidth::class);
		$data = $request->validate([
			'EndWidthSize' => ['required', 'string', 'max:255'],
		]);

		EndWidth::create($data);

		return redirect()->route('admin.production-data.end-width')->with('success', 'End Width created successfully.');
	}

	public function updateEndWidth(Request $request, EndWidth $endWidth)
	{
		$this->authorize('update', $endWidth);
		$data = $request->validate([
			'EndWidthSize' => ['required', 'string', 'max:255'],
		]);

		$endWidth->update($data);

		return redirect()->route('admin.production-data.end-width')->with('success', 'End Width updated successfully.');
	}

	public function designName(Request $request): Response
	{
		$this->authorize('viewAny', DesignName::class);
		$search = $request->string('search')->toString();
		$designNames = DesignName::query()
			->select(['DesignNameId', 'Name'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('DesignNameId', 'like', "%{$search}%")
						->orWhere('Name', 'like', "%{$search}%");
				});
			})
			->orderByDesc('DesignNameId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/DesignName', [
			'activeSection' => 'design-name',
			'designNames' => $designNames,
		]);
	}

	public function storeDesignName(Request $request)
	{
		$this->authorize('create', DesignName::class);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		DesignName::create($data);

		return redirect()->route('admin.production-data.design-name')->with('success', 'Design Name created successfully.');
	}

	public function updateDesignName(Request $request, DesignName $designName)
	{
		$this->authorize('update', $designName);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		$designName->update($data);

		return redirect()->route('admin.production-data.design-name')->with('success', 'Design Name updated successfully.');
	}

	public function destroyDesignName(DesignName $designName)
	{
		$this->authorize('delete', $designName);
		$designName->delete();
		return redirect()->route('admin.production-data.design-name')->with('success', 'Design Name deleted successfully.');
	}

	public function designDescription(Request $request): Response
	{
		$this->authorize('viewAny', DesignDescription::class);
		$search = $request->string('search')->toString();
		$designDescriptions = DesignDescription::query()
			->select(['DesignDescriptionId', 'Description'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('DesignDescriptionId', 'like', "%{$search}%")
						->orWhere('Description', 'like', "%{$search}%");
				});
			})
			->orderByDesc('DesignDescriptionId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/DesignDescription', [
			'activeSection' => 'design-description',
			'designDescriptions' => $designDescriptions,
		]);
	}

	public function storeDesignDescription(Request $request)
	{
		$this->authorize('create', DesignDescription::class);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		DesignDescription::create($data);

		return redirect()->route('admin.production-data.design-description')->with('success', 'Design Description created successfully.');
	}

	public function updateDesignDescription(Request $request, DesignDescription $designDescription)
	{
		$this->authorize('update', $designDescription);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$designDescription->update($data);

		return redirect()->route('admin.production-data.design-description')->with('success', 'Design Description updated successfully.');
	}

	public function destroyDesignDescription(DesignDescription $designDescription)
	{
		$this->authorize('delete', $designDescription);
		$designDescription->delete();
		return redirect()->route('admin.production-data.design-description')->with('success', 'Design Description deleted successfully.');
	}

	public function reinforce(Request $request): Response
	{
		$this->authorize('viewAny', Reinforce::class);
		$search = $request->string('search')->toString();
		$reinforces = Reinforce::query()
			->select(['ReinforceId', 'ReinforceType'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('ReinforceId', 'like', "%{$search}%")
						->orWhere('ReinforceType', 'like', "%{$search}%");
				});
			})
			->orderByDesc('ReinforceId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/Reinforce', [
			'activeSection' => 'reinforce',
			'reinforces' => $reinforces,
		]);
	}

	public function storeReinforce(Request $request)
	{
		$this->authorize('create', Reinforce::class);
		$data = $request->validate([
			'ReinforceType' => ['required', 'string', 'max:255'],
		]);

		Reinforce::create($data);

		return redirect()->route('admin.production-data.reinforce')->with('success', 'Reinforce created successfully.');
	}

	public function updateReinforce(Request $request, Reinforce $reinforce)
	{
		$this->authorize('update', $reinforce);
		$data = $request->validate([
			'ReinforceType' => ['required', 'string', 'max:255'],
		]);

		$reinforce->update($data);

		return redirect()->route('admin.production-data.reinforce')->with('success', 'Reinforce updated successfully.');
	}

	public function strapLengthLongPart(Request $request): Response
	{
		$this->authorize('viewAny', StrapLengthLongPart::class);
		$search = $request->string('search')->toString();
		$strapLengthLongParts = StrapLengthLongPart::query()
			->select(['StrapLengthLongPartId', 'LongPart'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('StrapLengthLongPartId', 'like', "%{$search}%")
						->orWhere('LongPart', 'like', "%{$search}%");
				});
			})
			->orderByDesc('StrapLengthLongPartId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/StrapLengthLongPart', [
			'activeSection' => 'strap-length-long-part',
			'strapLengthLongParts' => $strapLengthLongParts,
		]);
	}

	public function storeStrapLengthLongPart(Request $request)
	{
		$this->authorize('create', StrapLengthLongPart::class);
		$data = $request->validate([
			'LongPart' => ['required', 'string', 'max:255'],
		]);

		StrapLengthLongPart::create($data);

		return redirect()->route('admin.production-data.strap-length-long-part')->with('success', 'Strap Length Long Part created successfully.');
	}

	public function updateStrapLengthLongPart(Request $request, StrapLengthLongPart $strapLengthLongPart)
	{
		$this->authorize('update', $strapLengthLongPart);
		$data = $request->validate([
			'LongPart' => ['required', 'string', 'max:255'],
		]);

		$strapLengthLongPart->update($data);

		return redirect()->route('admin.production-data.strap-length-long-part')->with('success', 'Strap Length Long Part updated successfully.');
	}

	public function destroyStrapLengthLongPart(StrapLengthLongPart $strapLengthLongPart)
	{
		$this->authorize('delete', $strapLengthLongPart);
		$strapLengthLongPart->delete();
		return redirect()->route('admin.production-data.strap-length-long-part')->with('success', 'Strap Length Long Part deleted successfully.');
	}

	public function strapLengthShortPart(Request $request): Response
	{
		$this->authorize('viewAny', StrapLengthShortPart::class);
		$search = $request->string('search')->toString();
		$strapLengthShortParts = StrapLengthShortPart::query()
			->select(['StrapLengthShortPartId', 'ShortPart'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('StrapLengthShortPartId', 'like', "%{$search}%")
						->orWhere('ShortPart', 'like', "%{$search}%");
				});
			})
			->orderByDesc('StrapLengthShortPartId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/StrapLengthShortPart', [
			'activeSection' => 'strap-length-short-part',
			'strapLengthShortParts' => $strapLengthShortParts,
		]);
	}

	public function storeStrapLengthShortPart(Request $request)
	{
		$this->authorize('create', StrapLengthShortPart::class);
		$data = $request->validate([
			'ShortPart' => ['required', 'string', 'max:255'],
		]);

		StrapLengthShortPart::create($data);

		return redirect()->route('admin.production-data.strap-length-short-part')->with('success', 'Strap Length Short Part created successfully.');
	}

	public function updateStrapLengthShortPart(Request $request, StrapLengthShortPart $strapLengthShortPart)
	{
		$this->authorize('update', $strapLengthShortPart);
		$data = $request->validate([
			'ShortPart' => ['required', 'string', 'max:255'],
		]);

		$strapLengthShortPart->update($data);

		return redirect()->route('admin.production-data.strap-length-short-part')->with('success', 'Strap Length Short Part updated successfully.');
	}

	public function destroyStrapLengthShortPart(StrapLengthShortPart $strapLengthShortPart)
	{
		$this->authorize('delete', $strapLengthShortPart);
		$strapLengthShortPart->delete();
		return redirect()->route('admin.production-data.strap-length-short-part')->with('success', 'Strap Length Short Part deleted successfully.');
	}

	public function stampingLong(Request $request): Response
	{
		$this->authorize('viewAny', StampingLong::class);
		$search = $request->string('search')->toString();
		$stampingLongs = StampingLong::query()
			->select(['StampingLongId', 'Description'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('StampingLongId', 'like', "%{$search}%")
						->orWhere('Description', 'like', "%{$search}%");
				});
			})
			->orderByDesc('StampingLongId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/StampingLong', [
			'activeSection' => 'stamping-long',
			'stampingLongs' => $stampingLongs,
		]);
	}

	public function storeStampingLong(Request $request)
	{
		$this->authorize('create', StampingLong::class);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		StampingLong::create($data);

		return redirect()->route('admin.production-data.stamping-long')->with('success', 'Stamping Long created successfully.');
	}

	public function updateStampingLong(Request $request, StampingLong $stampingLong)
	{
		$this->authorize('update', $stampingLong);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$stampingLong->update($data);

		return redirect()->route('admin.production-data.stamping-long')->with('success', 'Stamping Long updated successfully.');
	}

	public function stampingShort(Request $request): Response
	{
		$this->authorize('viewAny', StampingShort::class);
		$search = $request->string('search')->toString();
		$stampingShorts = StampingShort::query()
			->select(['StampingShortId', 'Description'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('StampingShortId', 'like', "%{$search}%")
						->orWhere('Description', 'like', "%{$search}%");
				});
			})
			->orderByDesc('StampingShortId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/StampingShort', [
			'activeSection' => 'stamping-short',
			'stampingShorts' => $stampingShorts,
		]);
	}

	public function storeStampingShort(Request $request)
	{
		$this->authorize('create', StampingShort::class);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		StampingShort::create($data);

		return redirect()->route('admin.production-data.stamping-short')->with('success', 'Stamping Short created successfully.');
	}

	public function updateStampingShort(Request $request, StampingShort $stampingShort)
	{
		$this->authorize('update', $stampingShort);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$stampingShort->update($data);

		return redirect()->route('admin.production-data.stamping-short')->with('success', 'Stamping Short updated successfully.');
	}

	public function strapPictures(Request $request): Response
	{
		$this->authorize('viewAny', StrapPicture::class);
		$search = $request->string('search')->toString();
		$strapPictures = StrapPicture::query()
			->select(['StrapPictureId', 'Name'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('StrapPictureId', 'like', "%{$search}%")
						->orWhere('Name', 'like', "%{$search}%");
				});
			})
			->orderByDesc('StrapPictureId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/StrapPictures', [
			'activeSection' => 'strap-pictures',
			'strapPictures' => $strapPictures,
		]);
	}

	public function storeStrapPicture(Request $request)
	{
		$this->authorize('create', StrapPicture::class);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
			'picture' => ['required', 'image', 'max:2048'], // Max 2MB
		]);

		// Handle file upload
		if ($request->hasFile('picture')) {
			$file = $request->file('picture');
			$filename = $data['Name'] . '.' . $file->getClientOriginalExtension();
			$file->storeAs('private/strappictures', $filename);
			$data['Name'] = $filename;
		}

		StrapPicture::create($data);

		return redirect()->route('admin.production-data.strap-pictures')->with('success', 'Strap Picture created successfully.');
	}

	public function updateStrapPicture(Request $request, StrapPicture $strapPicture)
	{
		$this->authorize('update', $strapPicture);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
			'picture' => ['nullable', 'image', 'max:2048'], // Optional on update
		]);

		// Handle file upload if new picture is provided
		if ($request->hasFile('picture')) {
			// Delete old file if exists
			$oldFilePath = storage_path('app/private/strappictures/' . $strapPicture->Name);
			if (file_exists($oldFilePath)) {
				unlink($oldFilePath);
			}

			$file = $request->file('picture');
			$filename = $data['Name'] . '.' . $file->getClientOriginalExtension();
			$file->storeAs('private/strappictures', $filename);
			$data['Name'] = $filename;
		} else {
			// If no new file, keep the existing name but update if Name changed
			// Extract extension from existing name
			$existingExtension = pathinfo($strapPicture->Name, PATHINFO_EXTENSION);
			if ($existingExtension) {
				$data['Name'] = $data['Name'] . '.' . $existingExtension;
			}
		}

		$strapPicture->update($data);

		return redirect()->route('admin.production-data.strap-pictures')->with('success', 'Strap Picture updated successfully.');
	}

	public function serveStrapPicture($filename)
	{
		$path = storage_path('app/private/strappictures/' . $filename);
		
		if (!file_exists($path)) {
			abort(404);
		}

		return response()->file($path);
	}

	public function special(Request $request): Response
	{
		$this->authorize('viewAny', Special::class);
		$search = $request->string('search')->toString();
		$specials = Special::query()
			->select(['SpecialId', 'Description'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('SpecialId', 'like', "%{$search}%")
						->orWhere('Description', 'like', "%{$search}%");
				});
			})
			->orderByDesc('SpecialId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/Special', [
			'activeSection' => 'special',
			'specials' => $specials,
		]);
	}

	public function storeSpecial(Request $request)
	{
		$this->authorize('create', Special::class);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		Special::create($data);

		return redirect()->route('admin.production-data.special')->with('success', 'Special created successfully.');
	}

	public function updateSpecial(Request $request, Special $special)
	{
		$this->authorize('update', $special);
		$data = $request->validate([
			'Description' => ['required', 'string', 'max:255'],
		]);

		$special->update($data);

		return redirect()->route('admin.production-data.special')->with('success', 'Special updated successfully.');
	}

	public function prodDesign(Request $request): Response
	{
		$this->authorize('viewAny', ProdDesign::class);
		$search = $request->string('search')->toString();
		$prodDesigns = ProdDesign::query()
			->select(['id', 'Name'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('id', 'like', "%{$search}%")
						->orWhere('Name', 'like', "%{$search}%");
				});
			})
			->orderByDesc('id')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/ProdDesign', [
			'activeSection' => 'prod-design',
			'prodDesigns' => $prodDesigns,
		]);
	}

	public function storeProdDesign(Request $request)
	{
		$this->authorize('create', ProdDesign::class);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		ProdDesign::create($data);

		return redirect()->route('admin.production-data.prod-design')->with('success', 'Production Design created successfully.');
	}

	public function updateProdDesign(Request $request, ProdDesign $prodDesign)
	{
		$this->authorize('update', $prodDesign);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		$prodDesign->update($data);

		return redirect()->route('admin.production-data.prod-design')->with('success', 'Production Design updated successfully.');
	}

	public function noteInfoLine1(Request $request): Response
	{
		$this->authorize('viewAny', NoteInfoLine1::class);
		$search = $request->string('search')->toString();
		$noteInfoLine1s = NoteInfoLine1::query()
			->select(['NoteInfoLine1Id', 'Line1'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('NoteInfoLine1Id', 'like', "%{$search}%")
						->orWhere('Line1', 'like', "%{$search}%");
				});
			})
			->orderByDesc('NoteInfoLine1Id')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/NoteInfoLine1', [
			'activeSection' => 'note-info-line1',
			'noteInfoLine1s' => $noteInfoLine1s,
		]);
	}

	public function storeNoteInfoLine1(Request $request)
	{
		$this->authorize('create', NoteInfoLine1::class);
		$data = $request->validate([
			'Line1' => ['required', 'string', 'max:255'],
		]);

		NoteInfoLine1::create($data);

		return redirect()->route('admin.production-data.note-info-line1')->with('success', 'Note Info Line1 created successfully.');
	}

	public function updateNoteInfoLine1(Request $request, NoteInfoLine1 $noteInfoLine1)
	{
		$this->authorize('update', $noteInfoLine1);
		$data = $request->validate([
			'Line1' => ['required', 'string', 'max:255'],
		]);

		$noteInfoLine1->update($data);

		return redirect()->route('admin.production-data.note-info-line1')->with('success', 'Note Info Line1 updated successfully.');
	}

	public function destroyNoteInfoLine1(NoteInfoLine1 $noteInfoLine1)
	{
		$this->authorize('delete', $noteInfoLine1);
		$noteInfoLine1->delete();
		return redirect()->route('admin.production-data.note-info-line1')->with('success', 'Note Info Line1 deleted successfully.');
	}

	public function noteInfoLine2(Request $request): Response
	{
		$this->authorize('viewAny', NoteInfoLine2::class);
		$search = $request->string('search')->toString();
		$noteInfoLine2s = NoteInfoLine2::query()
			->select(['NoteInfoLine2Id', 'Line2'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('NoteInfoLine2Id', 'like', "%{$search}%")
						->orWhere('Line2', 'like', "%{$search}%");
				});
			})
			->orderByDesc('NoteInfoLine2Id')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/NoteInfoLine2', [
			'activeSection' => 'note-info-line2',
			'noteInfoLine2s' => $noteInfoLine2s,
		]);
	}

	public function storeNoteInfoLine2(Request $request)
	{
		$this->authorize('create', NoteInfoLine2::class);
		$data = $request->validate([
			'Line2' => ['required', 'string', 'max:255'],
		]);

		NoteInfoLine2::create($data);

		return redirect()->route('admin.production-data.note-info-line2')->with('success', 'Note Info Line2 created successfully.');
	}

	public function updateNoteInfoLine2(Request $request, NoteInfoLine2 $noteInfoLine2)
	{
		$this->authorize('update', $noteInfoLine2);
		$data = $request->validate([
			'Line2' => ['required', 'string', 'max:255'],
		]);

		$noteInfoLine2->update($data);

		return redirect()->route('admin.production-data.note-info-line2')->with('success', 'Note Info Line2 updated successfully.');
	}

	public function destroyNoteInfoLine2(NoteInfoLine2 $noteInfoLine2)
	{
		$this->authorize('delete', $noteInfoLine2);
		$noteInfoLine2->delete();
		return redirect()->route('admin.production-data.note-info-line2')->with('success', 'Note Info Line2 deleted successfully.');
	}

	public function tools(Request $request): Response
	{
		$this->authorize('viewAny', Tool::class);
		$search = $request->string('search')->toString();
		$tools = Tool::query()
			->select(['ToolId', 'Name'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('ToolId', 'like', "%{$search}%")
						->orWhere('Name', 'like', "%{$search}%");
				});
			})
			->orderByDesc('ToolId')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/ProductionData/Tools', [
			'activeSection' => 'tools',
			'tools' => $tools,
		]);
	}

	public function storeTool(Request $request)
	{
		$this->authorize('create', Tool::class);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		Tool::create($data);

		return redirect()->route('admin.production-data.tools')->with('success', 'Tool created successfully.');
	}

	public function updateTool(Request $request, Tool $tool)
	{
		$this->authorize('update', $tool);
		$data = $request->validate([
			'Name' => ['required', 'string', 'max:255'],
		]);

		$tool->update($data);

		return redirect()->route('admin.production-data.tools')->with('success', 'Tool updated successfully.');
	}

	public function destroyTool(Tool $tool)
	{
		$this->authorize('delete', $tool);
		$tool->delete();
		return redirect()->route('admin.production-data.tools')->with('success', 'Tool deleted successfully.');
	}

	public function destroyColourObject(ColourObject $colourObject)
	{
		$this->authorize('delete', $colourObject);
		$colourObject->delete();
		return redirect()->route('admin.production-data.colour-object')->with('success', 'Colour Object deleted successfully.');
	}

	public function destroyColour(Colour $colour)
	{
		$this->authorize('delete', $colour);
		$colour->delete();
		return redirect()->route('admin.production-data.colours')->with('success', 'Colour deleted successfully.');
	}

	public function destroyLeather(Leather $leather)
	{
		$this->authorize('delete', $leather);
		$leather->delete();
		return redirect()->route('admin.production-data.leather')->with('success', 'Leather deleted successfully.');
	}

	public function destroyLining(Lining $lining)
	{
		$this->authorize('delete', $lining);
		$lining->delete();
		return redirect()->route('admin.production-data.lining')->with('success', 'Lining deleted successfully.');
	}

	public function destroyLoopBand(LoopBand $loopBand)
	{
		$this->authorize('delete', $loopBand);
		$loopBand->delete();
		return redirect()->route('admin.production-data.loop-band')->with('success', 'Loop Band deleted successfully.');
	}

	public function destroyBuckle(Buckle $buckle)
	{
		$this->authorize('delete', $buckle);
		$buckle->delete();
		return redirect()->route('admin.production-data.buckle')->with('success', 'Buckle deleted successfully.');
	}

	public function destroyBuckleWidth(BuckleWidth $buckleWidth)
	{
		$this->authorize('delete', $buckleWidth);
		$buckleWidth->delete();
		return redirect()->route('admin.production-data.buckle-width')->with('success', 'Buckle Width deleted successfully.');
	}

	public function destroyThreadSize(ThreadSize $threadSize)
	{
		$this->authorize('delete', $threadSize);
		$threadSize->delete();
		return redirect()->route('admin.production-data.thread-size')->with('success', 'Thread Size deleted successfully.');
	}

	public function destroyEndWidth(EndWidth $endWidth)
	{
		$this->authorize('delete', $endWidth);
		$endWidth->delete();
		return redirect()->route('admin.production-data.end-width')->with('success', 'End Width deleted successfully.');
	}


	public function destroyReinforce(Reinforce $reinforce)
	{
		$this->authorize('delete', $reinforce);
		$reinforce->delete();
		return redirect()->route('admin.production-data.reinforce')->with('success', 'Reinforce deleted successfully.');
	}


	public function destroyStampingLong(StampingLong $stampingLong)
	{
		$this->authorize('delete', $stampingLong);
		$stampingLong->delete();
		return redirect()->route('admin.production-data.stamping-long')->with('success', 'Stamping Long deleted successfully.');
	}

	public function destroyStampingShort(StampingShort $stampingShort)
	{
		$this->authorize('delete', $stampingShort);
		$stampingShort->delete();
		return redirect()->route('admin.production-data.stamping-short')->with('success', 'Stamping Short deleted successfully.');
	}

	public function destroyStrapPicture(StrapPicture $strapPicture)
	{
		$this->authorize('delete', $strapPicture);
		
		// Delete the associated file if it exists
		$filePath = storage_path('app/private/strappictures/' . $strapPicture->Name);
		if (file_exists($filePath)) {
			unlink($filePath);
		}
		
		$strapPicture->delete();
		return redirect()->route('admin.production-data.strap-pictures')->with('success', 'Strap Picture deleted successfully.');
	}

	public function destroySpecial(Special $special)
	{
		$this->authorize('delete', $special);
		$special->delete();
		return redirect()->route('admin.production-data.special')->with('success', 'Special deleted successfully.');
	}

	public function destroyProdDesign(ProdDesign $prodDesign)
	{
		$this->authorize('delete', $prodDesign);
		$prodDesign->delete();
		return redirect()->route('admin.production-data.prod-design')->with('success', 'Production Design deleted successfully.');
	}

}

