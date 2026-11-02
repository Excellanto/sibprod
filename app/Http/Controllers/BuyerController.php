<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerController extends Controller
{
	public function index(Request $request): Response
	{
		$this->authorize('viewAny', Buyer::class);
		$search = $request->input('search', '');
		$sortField = $request->input('sortField', 'id');
		$sortOrder = $request->input('sortOrder', -1); // -1 for desc, 1 for asc
		
		$query = Buyer::query()
			->select(['id', 'buyer_name', 'email', 'phone', 'country'])
			->withCount('clients')
			->with(['clients' => function ($query) {
				$query->select([
					'id',
					'buyer_id',
					'client_name',
					'currency',
					'taxrule',
					'postalcode',
					'attention',
					'email',
					'phone',
					'address',
					'city',
					'country',
					'port_of_discharge',
				]);
			}])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('buyer_name', 'like', "%{$search}%")
						->orWhere('email', 'like', "%{$search}%")
						->orWhere('phone', 'like', "%{$search}%");
				});
			});
		
		// Handle sorting - validate sortField to prevent SQL injection
		$allowedSortFields = ['id', 'buyer_name', 'email', 'phone', 'country', 'clients_count'];
		$sortField = in_array($sortField, $allowedSortFields) ? $sortField : 'id';
		
		if ($sortField === 'clients_count') {
			$query->orderBy('clients_count', $sortOrder === 1 ? 'asc' : 'desc');
		} else {
			$query->orderBy($sortField, $sortOrder === 1 ? 'asc' : 'desc');
		}
		
		$buyers = $query->paginate(10)->withQueryString();

		return Inertia::render('Admin/Buyers/Index', [
			'buyers' => $buyers,
		]);
	}

	public function create(): Response
	{
		$this->authorize('create', Buyer::class);
		return Inertia::render('Admin/Buyers/Create');
	}

	public function store(Request $request)
	{
		$this->authorize('create', Buyer::class);
		$data = $request->validate([
			'buyer_name' => ['required', 'string', 'max:255'],
			'email' => ['nullable', 'string', 'email', 'max:255'],
			'phone' => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s()]+$/'],
			'address' => ['nullable', 'string'],
			'city' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
			'country' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
		]);

		Buyer::create($data);

		return redirect()->route('admin.buyers.index')->with('success', 'Buyer created.');
	}

	public function edit(Buyer $buyer): Response
	{
		$this->authorize('update', $buyer);
		return Inertia::render('Admin/Buyers/Edit', [
			'buyer' => $buyer,
		]);
	}

	public function update(Request $request, Buyer $buyer)
	{
		$this->authorize('update', $buyer);
		$data = $request->validate([
			'buyer_name' => ['required', 'string', 'max:255'],
			'email' => ['nullable', 'string', 'email', 'max:255'],
			'phone' => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s()]+$/'],
			'address' => ['nullable', 'string'],
			'city' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
			'country' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
		]);

		$buyer->update($data);

		return redirect()->route('admin.buyers.index')->with('success', 'Buyer updated.');
	}

	public function destroy(Buyer $buyer)
	{
		$this->authorize('delete', $buyer);
		$buyer->delete();
		return back()->with('success', 'Buyer deleted.');
	}
}
