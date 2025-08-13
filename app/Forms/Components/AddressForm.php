<?php

namespace App\Forms\Components;

use App\Models\Shop\ClientAddress;
use Filament\Forms;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Model;

class AddressForm extends Field
{
    protected string $view = 'filament-forms::components.group';

    protected ?string $relationship = null;

    public function relationship(string $relationship): static
    {
        $this->relationship = $relationship;
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (AddressForm $component, ?Model $record) {
            $selectedAddressId = $component
                    ->getContainer()
                    ?->getComponent('selected_address_id')
                    ?->getState()
                ?? $record?->client_address_id; // если select ещё не загружен
       //     dump('SELECTED_ADDRESS_ID', $selectedAddressId);
    //        dump('RECORD.client_address_id', $record->client_address_id);
            if (! $selectedAddressId || $selectedAddressId === '-1') {
                $component->state([
                    'street' => null,
                    'house' => null,
                    'apartment' => null,
                    'intercom' => null,
                    'floor' => null,
                    'entrance' => null,
                    'zip' => null,
                    'city' => null,
                    'country' => null,
                    'note' => null,
                    'type' => null,
                    'is_private_house' => false,
                ]);
                return;
            }

            $address = \App\Models\Shop\ClientAddress::find($selectedAddressId);

            // Обновляем только если state еще пустой
            if (empty($component->getState()['street'])) {
                $component->state($address->toArray());
            }
        });

        $this->dehydrated(false); // чтобы вся форма не ушла в payload, мы отдельно сохраняем
    }


    public function saveRelationships(): void
    {
        $state = $this->getState();
        $record = $this->getRecord();

        if (! $record || ! $record->clients_id) return;

        // ⬇️ 100% способ получить ID выбранного адреса
        $selectedAddressId = data_get($this->getContainer()->getRawState(), 'selected_address_id');

        // ✅ Проверка (можно временно оставить)
        // dump('selectedAddressId', $selectedAddressId);

        // 🟡 Создаём новый, только если выбран '-1'
     //   dd($selectedAddressId);
        if ((int) $selectedAddressId === -1) {
            $address = ClientAddress::create([
                ...$state,
                'client_id' => $record->clients_id,
            ]);
        //    dd($address);
           // $record->client_address_id = $address->id;
         //   dd($record->client_address_id);

        //    $record->client_address_id = $address->id;
         //   $record->save();
            // ✅ Обновляем state внутри формы — иначе Filament затрёт
            \DB::table('shop_orders')
                ->where('id', $record->id)
                ->update(['client_address_id' => $address->id]);
          //  dd($record);
            return;
        }

        // 🟢 Если выбран существующий адрес — обновляем
        $address = ClientAddress::find($selectedAddressId);
       // dd($address);
        if ($address) {
            $address->update($state);

            // обязательно связываем с заказом
            $record->client_address_id = $address->id;
            $record->save();
        }
    }






    public function getChildComponents(): array
    {
        return [
            Section::make('Адресс клиента:')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('street')->required()->label('Улица'),
                Forms\Components\TextInput::make('house')->required()->label('Дом'),
                Forms\Components\TextInput::make('apartment')->label('Квартира'),
                Forms\Components\TextInput::make('intercom')->label('Домофон'),
                Forms\Components\TextInput::make('floor')->label('Этаж'),
                Forms\Components\TextInput::make('entrance')->label('Подъезд'),
              //  Forms\Components\TextInput::make('zip')->label('Индекс'),
                Forms\Components\TextInput::make('city')->label('Город'),
            //    Forms\Components\TextInput::make('country')->label('Страна'),
                Forms\Components\Select::make('type')->label('Тип адреса')->options([
                    'home' => 'Дом',
                    'work' => 'Работа',
                    'friends' => 'Друзья',
                ]),
                Forms\Components\Toggle::make('is_private_house')->label('Частный дом'),
                Forms\Components\Textarea::make('note')->label('Примечание')->columnSpanFull(),
            ])
            ])
                ,
        ];
    }

    public function getRelationship(): string
    {
        return $this->evaluate($this->relationship) ?? 'address';
    }
}
