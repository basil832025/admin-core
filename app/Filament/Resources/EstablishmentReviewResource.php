<?php

namespace App\Filament\Resources;

use App\Models\EstablishmentReview;
use App\Filament\Resources\EstablishmentReviewResource\Pages;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class EstablishmentReviewResource extends Resource
{
    protected static ?string $model = EstablishmentReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = 'Відгуки (заклад)';
    protected static ?int $navigationSort = 65;

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('view_any_product::review');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('author_name')
                    ->label("Автор")
                    ->required()
                    ->maxLength(120),

                Forms\Components\Select::make('rating')
                    ->label('Оцінка')
                    ->options([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5])
                    ->required(),

                Forms\Components\Textarea::make('text')
                    ->label('Відгук')
                    ->rows(6)
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Опубліковано')
                    ->default(false),

                Forms\Components\DateTimePicker::make('posted_at')
                    ->label('Дата')
                    ->seconds(false)
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('posted_at')->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('author_name')
                    ->label('Автор')
                    ->searchable(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('★')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Опубл.')
                    ->boolean(),

                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Дата')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Опубліковано')
                    ->trueLabel('Так')
                    ->falseLabel('На модерації')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publish')
                    ->label('Опублікувати')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (EstablishmentReview $record) => ! (bool) $record->is_active)
                    ->action(fn (EstablishmentReview $record) => $record->update(['is_active' => true])),
                Tables\Actions\Action::make('hide')
                    ->label('Сховати')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (EstablishmentReview $record) => (bool) $record->is_active)
                    ->action(fn (EstablishmentReview $record) => $record->update(['is_active' => false])),
            ])
            ->defaultSort('posted_at', 'desc');
    }

    protected static function pendingCount(): int
    {
        return Cache::remember('nav:establishment_reviews:pending', now()->addMinute(), fn () =>
            EstablishmentReview::query()->where('is_active', false)->count()
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingCount();
        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::pendingCount() ? 'warning' : 'gray';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstablishmentReviews::route('/'),
            'edit' => Pages\EditEstablishmentReview::route('/{record}/edit'),
        ];
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.content');
    }

}
