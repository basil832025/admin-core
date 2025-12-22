<?php

namespace App\Filament\Resources\SvgImageResource\Pages;

use App\Filament\Resources\SvgImageResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSvgImage extends EditRecord
{
    protected static string $resource = SvgImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()
                ->label(__('product.actions.cancel'))
                ->color('warning')
                // можно задать куда вести, по умолчанию вернёт на index-роту
                ->url($this->getResource()::getUrl('index')),

            $this->getSaveFormAction()
                ->label(__('product.actions.save'))
                ->keyBindings(['mod+s'])

                ->formId('form'),
               // ->successRedirectUrl($this->getResource()::getUrl('index')),
            // Сохранить и перейти к списку
            // Сохранить и закрыть (на список)
            Actions\Action::make('saveAndClose')
                ->label('Зберегти та закрити')
                ->color('primary')
                ->action(function () {
                    $this->save(); // триггерит валидацию, хуки, нотификации
                    $this->redirect($this->getResource()::getUrl('index'), navigate: true);
                }),
          /*  $this->getSaveFormAction()
                ->label('Зберегти та закрити')
                ->color('primary')
                ->formId('form')
                ->successRedirectUrl($this->getResource()::getUrl('index')),*/
            Actions\DeleteAction::make(),
        ];
    }

}
