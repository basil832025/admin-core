<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\ContactMessageResource\Pages\ListContactMessages;
use App\Models\ContactMessage;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $slug = 'shop/contact-messages';
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Звернення з сайту';
    protected static ?string $pluralModelLabel = 'Звернення з сайту';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }

    public static function canViewAny(): bool
    {
        if (config('project.name') !== 'duxi') {
            return false;
        }

        $user = Filament::auth()->user();

        return $user && ($user->hasRole(config('shield.super_admin.name', 'super_admin'))
            || $user->can('view_any_contact_message'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('name')->label('Ім’я')->searchable(),
                TextColumn::make('contact')->label('Контакт для відповіді')->searchable()->copyable(),
                TextColumn::make('message')->label('Повідомлення')->wrap(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
