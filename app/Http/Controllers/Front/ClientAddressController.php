<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Shop\ClientAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ClientAddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $client = Auth::user();
        $addresses = $client->addresses()->orderByDesc('id')->get();

        return view('pages.profile.addresses.index', [
            'addresses' => $addresses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.profile.addresses.form', [
            'address' => new ClientAddress(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $client = Auth::user();

        $validated = $request->validate([
            'city' => 'nullable|string|max:255',
            'street' => 'required|string|max:255',
            'house' => 'required|string|max:50',
            'apartment' => 'nullable|string|max:50',
            'intercom' => 'nullable|string|max:255',
            'floor' => 'nullable|integer',
            'entrance' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
            'is_private_house' => 'boolean',
            'type' => 'nullable|string|max:50',
        ]);

        $validated['client_id'] = $client->id;
        $validated['is_private_house'] = $request->boolean('is_private_house', false);

        ClientAddress::create($validated);

        return redirect()
            ->route('profile.addresses.index')
            ->with('success', 'Адреса успішно додана');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ClientAddress $address)
    {
        // Проверка, что адрес принадлежит текущему пользователю
        if ($address->client_id !== Auth::id()) {
            abort(403);
        }

        return view('pages.profile.addresses.form', [
            'address' => $address,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClientAddress $address)
    {
        // Проверка, что адрес принадлежит текущему пользователю
        if ($address->client_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'city' => 'nullable|string|max:255',
            'street' => 'required|string|max:255',
            'house' => 'required|string|max:50',
            'apartment' => 'nullable|string|max:50',
            'intercom' => 'nullable|string|max:255',
            'floor' => 'nullable|integer',
            'entrance' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
            'is_private_house' => 'boolean',
            'type' => 'nullable|string|max:50',
        ]);

        $validated['is_private_house'] = $request->boolean('is_private_house', false);

        $address->update($validated);

        return redirect()
            ->route('profile.addresses.index')
            ->with('success', 'Адреса успішно оновлена');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClientAddress $address)
    {
        // Проверка, что адрес принадлежит текущему пользователю
        if ($address->client_id !== Auth::id()) {
            abort(403);
        }

        $address->delete();

        return redirect()
            ->route('profile.addresses.index')
            ->with('success', 'Адреса успішно видалена');
    }
}

