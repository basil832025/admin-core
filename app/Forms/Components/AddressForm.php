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
use Filament\Forms\Get;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AddressForm
{
    public static function make(string $statePath = 'address'): Group
    {
        return Group::make()
            ->statePath($statePath)
            ->schema([
                Grid::make(12)->schema([
                    // Используем TextInput вместо Select, так как серверные запросы не работают из-за ограничений по referer
                    // Автокомплит будет инициализирован через клиентский JavaScript
                    TextInput::make('street')
                        ->label(__('order.fields.address_street_place'))
                        ->required()
                        // В Filament/Livewire быстрый ввод в live-поле может терять символы из-за
                        // частых сетевых обновлений и перерисовок. Обновляем состояние только при blur.
                        ->live(onBlur: true)
                        ->dehydrated()
                        ->extraAttributes([
                            'id' => 'filament-address-street-input',
                            'data-address-autocomplete' => 'true',
                        ])
                        ->afterStateUpdated(function ($state, callable $set) {
                            // Это поле будет заполняться через JavaScript, но оставляем колбэк для совместимости
                            // Не сбрасываем значение, если оно было установлено через автокомплит
                            $set('street_place_id', $state);
                        })
                        ->columnSpan(6),
                    Hidden::make('street_place_id')->dehydrated(),
                    
                    // Старое поле Select закомментировано, так как серверные запросы не работают
                    /*
                    Select::make('street_place_id')
                        ->label(__('order.fields.address_street_place'))
                        ->live()
                        ->searchable()
                        ->getSearchResultsUsing(function (Select $c, string $search): array {
                            if (mb_strlen($search) < 2) {
                                return [];
                            }

                            // 1) проверяем ключ
                            $key = config('services.google_maps.key');
                            if (! $key) {
                                Log::error('GPlaces autocomplete: google_maps.key is empty', [
                                    'search' => $search,
                                    'env'    => app()->environment(),
                                ]);
                                return [];
                            }

                            // сессионный токен
                            $token = session('gplaces_token')
                                ?? tap((string) Str::uuid(), fn ($t) => session(['gplaces_token' => $t]));

                            try {
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
                                        'key'          => $key,
                                    ]
                                );
                            } catch (\Throwable $e) {
                                Log::error('GPlaces autocomplete HTTP exception', [
                                    'search'  => $search,
                                    'message' => $e->getMessage(),
                                ]);
                                return [];
                            }

                            if (! $resp->ok()) {
                                Log::error('GPlaces autocomplete HTTP error', [
                                    'search' => $search,
                                    'status' => $resp->status(),
                                    'body'   => $resp->body(),
                                ]);
                                return [];
                            }

                            $apiStatus = $resp->json('status');
                            if ($apiStatus !== 'OK') {
                                Log::error('GPlaces autocomplete API error', [
                                    'search' => $search,
                                    'status' => $apiStatus,
                                    'error'  => $resp->json('error_message'),
                                ]);
                                return [];
                            }

                            $predictions = $resp->json('predictions') ?? [];

                            return collect($predictions)
                                ->filter(function ($p) {
                                    $types    = $p['types'] ?? [];
                                    $isStreet = in_array('route', $types) || in_array('geocode', $types);
                                    $d        = mb_strtolower($p['description'] ?? '');
                                    $inKyiv   = str_contains($d, 'київ')
                                        || str_contains($d, 'киев')
                                        || str_contains($d, 'kyiv')
                                        || str_contains($d, 'kyiv oblast')
                                        || str_contains($d, 'київська');

                                    return $isStreet && $inKyiv;
                                })
                                ->mapWithKeys(function ($p) {
                                    $main = $p['structured_formatting']['main_text'] ?? '';
                                    $sec  = $p['structured_formatting']['secondary_text'] ?? '';

                                    return [
                                        $p['place_id'] => trim($main . ($sec ? " — $sec" : '')),
                                    ];
                                })
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }

                            $key = config('services.google_maps.key');
                            if (! $key) {
                                Log::error('GPlaces optionLabel: google_maps.key is empty', [
                                    'value' => $value,
                                ]);
                                return (string) $value;
                            }

                            $token = session('gplaces_token') ?? null;

                            try {
                                $resp = Http::timeout(8)->acceptJson()->get(
                                    'https://maps.googleapis.com/maps/api/place/details/json',
                                    [
                                        'place_id'     => $value,
                                        'fields'       => 'formatted_address',
                                        'language'     => 'uk',
                                        'sessiontoken' => $token,
                                        'key'          => $key,
                                    ]
                                );
                            } catch (\Throwable $e) {
                                Log::error('GPlaces optionLabel HTTP exception', [
                                    'value'   => $value,
                                    'message' => $e->getMessage(),
                                ]);
                                return (string) $value;
                            }

                            if (! $resp->ok()) {
                                Log::error('GPlaces optionLabel HTTP error', [
                                    'value'  => $value,
                                    'status' => $resp->status(),
                                    'body'   => $resp->body(),
                                ]);
                                return (string) $value;
                            }

                            $apiStatus = $resp->json('status');
                            if ($apiStatus !== 'OK') {
                                Log::error('GPlaces optionLabel API error', [
                                    'value'  => $value,
                                    'status' => $apiStatus,
                                    'error'  => $resp->json('error_message'),
                                ]);
                                return (string) $value;
                            }

                            return data_get($resp->json(), 'result.formatted_address') ?? (string) $value;
                        })
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }

                            $key = config('services.google_maps.key');
                            if (! $key) {
                                Log::error('GPlaces details(afterStateUpdated): google_maps.key is empty', [
                                    'place_id' => $state,
                                ]);
                                return;
                            }

                            $token = session('gplaces_token') ?? null;

                            try {
                                $resp = Http::timeout(1)->acceptJson()->get(
                                    'https://maps.googleapis.com/maps/api/place/details/json',
                                    [
                                        'place_id'     => $state,
                                        'fields'       => 'address_components,geometry,formatted_address',
                                        'language'     => 'uk',
                                        'sessiontoken' => $token,
                                        'key'          => $key,
                                    ]
                                );
                            } catch (\Throwable $e) {
                                Log::error('GPlaces details(afterStateUpdated) HTTP exception', [
                                    'place_id' => $state,
                                    'message'  => $e->getMessage(),
                                ]);
                                return;
                            }

                            if (! $resp->ok()) {
                                Log::error('GPlaces details(afterStateUpdated) HTTP error', [
                                    'place_id' => $state,
                                    'status'   => $resp->status(),
                                    'body'     => $resp->body(),
                                ]);
                                return;
                            }

                            $apiStatus = $resp->json('status');
                            if ($apiStatus !== 'OK') {
                                Log::error('GPlaces details(afterStateUpdated) API error', [
                                    'place_id' => $state,
                                    'status'   => $apiStatus,
                                    'error'    => $resp->json('error_message'),
                                ]);
                                return;
                            }

                            $res   = $resp->json('result') ?? [];
                            $comps = collect($res['address_components'] ?? []);

                            $routeComp = $comps->first(fn ($c) => in_array('route', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('premise', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('establishment', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('point_of_interest', $c['types'] ?? []))
                                ?? $comps->first(fn ($c) => in_array('street_address', $c['types'] ?? []));

                            $street = data_get($routeComp, 'long_name')
                                ?: Str::before($res['formatted_address'] ?? '', ',');

                            $set('street', $street);
                            $set('formatted_address', $res['formatted_address'] ?? null);
                            $lat = data_get($res, 'geometry.location.lat');
                            $lng = data_get($res, 'geometry.location.lng');
                            $set('latitude', $lat);
                            $set('longitude', $lng);
                            
                            // Триггерим обновление поля доставки при изменении координат
                            if ($lat && $lng) {
                                $set('delivery_price_display', $lat);
                            }
                        })
                        ->columnSpan(2),
                    */
                    
                    // TextInput::make('street')->label(__('order.fields.address_street'))->columnSpan(2),
                    TextInput::make('house')
                        ->label(__('order.fields.address_house'))
                        ->required()
                        ->columnSpan(3),
                    TextInput::make('apartment')
                        ->label(__('order.fields.address_apartment'))
                        ->visible(fn (Get $get) => ! (bool) $get('is_private_house'))
                        ->columnSpan(3),
                    TextInput::make('entrance')
                        ->label(__('order.fields.address_entrance'))
                        ->visible(fn (Get $get) => ! (bool) $get('is_private_house'))
                        ->columnSpan(2),
                    TextInput::make('intercom')
                        ->label(__('order.fields.address_intercom'))
                        ->visible(fn (Get $get) => ! (bool) $get('is_private_house'))
                        ->columnSpan(2),
                    TextInput::make('floor')
                        ->label(__('order.fields.address_floor'))
                        ->visible(fn (Get $get) => ! (bool) $get('is_private_house'))
                        ->columnSpan(2),
                    Select::make('type')
                        ->label(__('order.fields.address_type'))
                        ->options([
                            'home'    => __('order.address_types.home'),
                            'work'    => __('order.address_types.work'),
                            'friends' => __('order.address_types.friends'),
                        ])
                        ->columnSpan(3),
                    Toggle::make('is_private_house')
                        ->label(__('order.fields.address_private_house'))
                        ->live()
                        ->inline(false)
                        ->columnSpan(3),
                    // TextInput::make('city')->label(__('order.fields.address_city'))->default('Київ'),
                    Hidden::make('latitude')
                        ->dehydrated()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            \Log::info('AddressForm: latitude afterStateUpdated called', [
                                'latitude' => $state,
                                'state_type' => gettype($state),
                            ]);
                            
                            // Триггерим обновление поля доставки при изменении координат
                            $longitude = $get('address.longitude');
                            \Log::info('AddressForm: longitude from get', [
                                'longitude' => $longitude,
                                'longitude_type' => gettype($longitude),
                            ]);
                            
                            if ($state && $longitude) {
                                // Создаем уникальный ключ на основе координат для принудительного обновления
                                $updateKey = 'coords_' . $state . '_' . $longitude . '_' . time();
                                \Log::info('AddressForm: Setting delivery_coords_trigger', [
                                    'updateKey' => $updateKey,
                                ]);
                                $set('delivery_coords_trigger', $updateKey);
                            } else {
                                \Log::warning('AddressForm: latitude afterStateUpdated - missing data', [
                                    'has_latitude' => !empty($state),
                                    'has_longitude' => !empty($longitude),
                                ]);
                            }
                        }),
                    Hidden::make('longitude')
                        ->dehydrated()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            \Log::info('AddressForm: longitude afterStateUpdated called', [
                                'longitude' => $state,
                                'state_type' => gettype($state),
                            ]);
                            
                            // Триггерим обновление поля доставки при изменении координат
                            $latitude = $get('address.latitude');
                            \Log::info('AddressForm: latitude from get', [
                                'latitude' => $latitude,
                                'latitude_type' => gettype($latitude),
                            ]);
                            
                            if ($state && $latitude) {
                                // Создаем уникальный ключ на основе координат для принудительного обновления
                                $updateKey = 'coords_' . $latitude . '_' . $state . '_' . time();
                                \Log::info('AddressForm: Setting delivery_coords_trigger', [
                                    'updateKey' => $updateKey,
                                ]);
                                $set('delivery_coords_trigger', $updateKey);
                            } else {
                                \Log::warning('AddressForm: longitude afterStateUpdated - missing data', [
                                    'has_latitude' => !empty($latitude),
                                    'has_longitude' => !empty($state),
                                ]);
                            }
                        }),
                    // TextInput::make('formatted_address')->label(__('order.fields.address_formatted'))->dehydrated()->columnSpan(2),
                    Hidden::make('formatted_address')->dehydrated(),
                    Textarea::make('note')->label(__('order.fields.address_note'))->columnSpanFull(),
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
