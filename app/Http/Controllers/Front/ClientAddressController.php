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
            'city'              => 'nullable|string|max:255',
            'street'            => 'required|string|max:255',
            'house'             => 'required|string|max:50',
            'apartment'         => 'nullable|string|max:50',
            'intercom'          => 'nullable|string|max:255',
            'floor'             => 'nullable|integer',
            'entrance'          => 'nullable|string|max:255',
            'note'              => 'nullable|string|max:500',
            'is_private_house'  => 'boolean',
            'type'              => 'nullable|string|max:50',
            'latitude'          => 'nullable|numeric',
            'longitude'         => 'nullable|numeric',
            'street_place_id'   => 'nullable|string|max:255',
            'formatted_address' => 'nullable|string|max:255',
        ]);

        $validated['client_id'] = $client->id;
        $validated['is_private_house'] = $request->boolean('is_private_house', false);

        ClientAddress::create($validated);

        return redirect()
            ->route('profile.addresses.index')
            ->with('success', st('profile.addresses.success_added', 'Адреса успішно додана'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($address)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403);
        }
        
        // Находим адрес с явной проверкой принадлежности
        $addressModel = ClientAddress::where('id', $address)
            ->where('client_id', $user->id)
            ->first();
        
        if (!$addressModel) {
            abort(403);
        }

        return view('pages.profile.addresses.form', [
            'address' => $addressModel,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $address)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403);
        }
        
        // Находим адрес с явной проверкой принадлежности
        $addressModel = ClientAddress::where('id', $address)
            ->where('client_id', $user->id)
            ->first();
        
        if (!$addressModel) {
            abort(403);
        }

        $validated = $request->validate([
            'city'              => 'nullable|string|max:255',
            'street'            => 'required|string|max:255',
            'house'             => 'required|string|max:50',
            'apartment'         => 'nullable|string|max:50',
            'intercom'          => 'nullable|string|max:255',
            'floor'             => 'nullable|integer',
            'entrance'          => 'nullable|string|max:255',
            'note'              => 'nullable|string|max:500',
            'is_private_house'  => 'boolean',
            'type'              => 'nullable|string|max:50',
            'latitude'          => 'nullable|numeric',
            'longitude'         => 'nullable|numeric',
            'street_place_id'   => 'nullable|string|max:255',
            'formatted_address' => 'nullable|string|max:255',
        ]);

        $validated['is_private_house'] = $request->boolean('is_private_house', false);

        $addressModel->update($validated);

        return redirect()
            ->route('profile.addresses.index')
            ->with('success', st('profile.addresses.success_updated', 'Адреса успішно оновлена'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($address)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403);
        }
        
        // Находим адрес с явной проверкой принадлежности
        $addressModel = ClientAddress::where('id', $address)
            ->where('client_id', $user->id)
            ->first();
        
        if (!$addressModel) {
            abort(403);
        }

        $addressModel->delete();

        return redirect()
            ->route('profile.addresses.index')
            ->with('success', st('profile.addresses.success_deleted', 'Адреса успішно видалена'));
    }
}

