<?php

namespace App\Filament\Resources\Shop\OrderResource\Pages;

use App\Filament\Resources\Shop\OrderResource;
use Filament\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreateOrder extends CreateRecord
{
    use HasWizard;
    protected static string $resource = OrderResource::class;
    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getSubmitFormAction())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(false),
            ])
            ->columns(null);
    }
    protected function afterCreate(): void
    {
        $this->record->recalculateTotalPrice();
    }
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['id'], $data['number']); // ← гарантированно не передаём в INSERT
        return $data;
    }
    protected function getSteps(): array
    {
        return [
            Step::make('Детали заказа')
                ->schema([
                   // Section::make()->schema(OrderResource::getDetailsFormSchema())->columns(),
                    Section::make()->schema(OrderResource::getInfoTabSchema())->columns(),
                //    Section::make()->schema(OrderResource::getRightFormSchema())->columns(),

                ]),

            Step::make('Товары заказа')
                ->schema([
                    Section::make()->schema([
                        OrderResource::getItemsRepeater(),
                    ]),
                ]),
        ];
    }
}
