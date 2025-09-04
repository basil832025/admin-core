<?php
// app/Forms/Components/AddressForm.php
namespace App\Forms\Components;


use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class AddressForm
{
    public static function make(string $statePath = 'address'): Group
    {
        return Group::make()
            ->statePath($statePath)
            ->schema([
                Grid::make(2)->schema([
                    Select::make('street_place_id')
                        ->label('Улица (Київ)')
                        ->live()
                        ->searchable()
                    //    ->default(null) // ключ существует до маунта
                        ->getSearchResultsUsing(function (Select $c, string $search): array {
                            if (mb_strlen($search) < 2) return [];

                            $token = session('gplaces_token')
                                ?? tap((string) Str::uuid(), fn ($t) => session(['gplaces_token' => $t]));

                            $resp = Http::timeout(8)->acceptJson()->get(
                                'https://maps.googleapis.com/maps/api/place/autocomplete/json',
                                [
                                    'input'        => $search,
                                    'types'        => 'address',
                                    'language'     => 'uk',
                                    'components'   => 'country:ua',
                                    'location'     => '50.4501,30.5234',
                                    'radius'       => 30000,
                                    'sessiontoken' => $token,
                                    'key'          => config('services.google_maps.key'),
                                ]
                            );

                            $predictions = $resp->json('predictions') ?? [];

                            return collect($predictions)
                                ->filter(function ($p) {
                                    $types = $p['types'] ?? [];
                                    $isStreet = in_array('route', $types) || in_array('geocode', $types);
                                    $d = mb_strtolower($p['description'] ?? '');
                                    $inKyiv = str_contains($d, 'київ') || str_contains($d, 'киев') || str_contains($d, 'kyiv')
                                        || str_contains($d, 'kyiv oblast') || str_contains($d, 'київська');
                                    return $isStreet && $inKyiv;
                                })
                                ->mapWithKeys(function ($p) {
                                    $main = $p['structured_formatting']['main_text'] ?? '';
                                    $sec  = $p['structured_formatting']['secondary_text'] ?? '';
                                    return [$p['place_id'] => trim($main . ($sec ? " — $sec" : ''))];
                                })
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) return null;

                            $token = session('gplaces_token') ?? null;
                            $resp = Http::timeout(8)->acceptJson()->get(
                                'https://maps.googleapis.com/maps/api/place/details/json',
                                [
                                    'place_id'     => $value,
                                    'fields'       => 'formatted_address',
                                    'language'     => 'uk',
                                    'sessiontoken' => $token,
                                    'key'          => config('services.google_maps.key'),
                                ]
                            );

                            return data_get($resp->json(), 'result.formatted_address') ?? (string) $value;
                        })
                        ->afterStateUpdated(function ($state, callable $set) {
                          //  dump($set,$state);
                            if (! $state) return;
                            // ВАЖНО: берём префикс контейнера (например, 'address')
                       //     $base = $component->getContainer()->getStatePath();
                            $token = session('gplaces_token') ?? null;
                            $resp = Http::timeout(1)->acceptJson()->get(
                                'https://maps.googleapis.com/maps/api/place/details/json',
                                [
                                    'place_id'     => $state,
                                    'fields'       => 'address_components,geometry,formatted_address',
                                    'language'     => 'uk',
                                    'sessiontoken' => $token,
                                    'key'          => config('services.google_maps.key'),
                                ]
                            );

                            $res   = $resp->json('result') ?? [];
                            $comps = collect($res['address_components'] ?? []);

                            $routeComp = $comps->first(fn ($c) => in_array('route', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('premise', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('establishment', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('point_of_interest', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('street_address', $c['types'] ?? []));

                            $street = data_get($routeComp, 'long_name')
                                ?: \Illuminate\Support\Str::before($res['formatted_address'] ?? '', ',');

                            $set('street', $street);
                            $set('formatted_address', $res['formatted_address'] ?? null);
                            $set('latitude', data_get($res, 'geometry.location.lat'));
                            $set('longitude', data_get($res, 'geometry.location.lng'));
                        })
                        ->columnSpan(2),

                    TextInput::make('street')->label('Улица')->columnSpan(2),
                    TextInput::make('house')->label('Дом')->required(),
                    TextInput::make('apartment')->label('Квартира'),
                    TextInput::make('intercom')->label('Домофон'),
                    TextInput::make('floor')->label('Этаж'),
                    TextInput::make('entrance')->label('Подъезд'),
                    TextInput::make('city')->label('Город')->default('Київ'),
                    Hidden::make('latitude')->dehydrated(),
                    Hidden::make('longitude')->dehydrated(),
                    TextInput::make('formatted_address')->label('Полный адрес')->dehydrated()->columnSpan(2),
                    Select::make('type')->label('Тип адреса')->options([
                        'home' => 'Дом', 'work' => 'Работа', 'friends' => 'Друзья',
                    ]),
                    Toggle::make('is_private_house')->label('Частный дом'),
                    Textarea::make('note')->label('Примечание по доставке')->columnSpanFull(),
                ]),
            ]);
    }
    public static function defaults(): array
    {
        return [
            'street_place_id'   => null,
            'street'            => null,
            'house'             => null,
            'apartment'         => null,
            'intercom'          => null,
            'floor'             => null,
            'entrance'          => null,
            'zip'               => null,
            'city'              => 'Київ',
            'country'           => null,
            'note'              => null,
            'type'              => null,
            'is_private_house'  => false,
            'latitude'          => null,
            'longitude'         => null,
            'formatted_address' => null,
        ];
    }
}
