<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function store(Request $request, Buyer $buyer)
    {
        $this->authorize('create', Client::class);
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:32'],
            'taxrule' => ['nullable', 'string', 'max:255'],
            'postalcode' => ['nullable', 'string', 'max:32'],
            'attention' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
            'country' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
            'port_of_discharge' => ['nullable', 'string', 'max:255'],
        ]);

        $buyer->clients()->create($data);

        return back()->with('success', 'Client added successfully.');
    }

    public function update(Request $request, Client $client)
    {
        $this->authorize('update', $client);
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:32'],
            'taxrule' => ['nullable', 'string', 'max:255'],
            'postalcode' => ['nullable', 'string', 'max:32'],
            'attention' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
            'country' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\']+$/'],
            'port_of_discharge' => ['nullable', 'string', 'max:255'],
        ]);

        $client->update($data);

        return back()->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);
        $client->delete();

        return back()->with('success', 'Client deleted.');
    }
}
