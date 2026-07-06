<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products;
use App\Filament\Clusters\Products\Resources\ProductPriceChangeBatchResource\Pages;
use App\Models\Shop\ProductPriceChangeBatch;
use App\Services\ProductBulkPriceService;
use App\Services\ProductPriceCalculator;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ProductPriceChangeBatchResource extends Resource
{
    protected static ?string $model = ProductPriceChangeBatch::class;

    protected static ?string $cluster = Products::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Журнал зміни цін';

    protected static ?string $modelLabel = 'операція зміни цін';

    protected static ?string $pluralModelLabel = 'Журнал зміни цін';

    protected static ?int $navigationSort = 2;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function shouldRegisterNavigation(): bool
    {
        return ProductBulkPriceService::canManage(Filament::auth()->user());
    }

    public static function canViewAny(): bool
    {
        return ProductBulkPriceService::canManage(Filament::auth()->user());
    }

    public static function canView(Model $record): bool
    {
        return ProductBulkPriceService::canManage(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Адміністратор')->placeholder('Система'),
                TextColumn::make('scope')
                    ->label('Область')
                    ->formatStateUsing(fn (string $state): string => ProductBulkPriceService::scopeLabel($state)),
                TextColumn::make('operation')
                    ->label('Операція')
                    ->formatStateUsing(fn (string $state): string => ProductPriceCalculator::operationOptions()[$state] ?? $state),
                TextColumn::make('value')->label('Значення')->numeric(decimalPlaces: 2),
                TextColumn::make('rounding_precision')->label('Знаків')->numeric(),
                TextColumn::make('affected_count')->label('Товарів')->numeric()->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusLabel($state))
                    ->color(fn (string $state): string => static::statusColor($state)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('revert')
                    ->label('Скасувати зміни')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (ProductPriceChangeBatch $record): bool => $record->status === 'completed' && $record->reverted_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Скасувати масову зміну цін?')
                    ->modalDescription('Будуть відновлені ціни, старі ціни та відсотки знижок, зафіксовані перед цією операцією.')
                    ->action(function (ProductPriceChangeBatch $record): void {
                        $user = Filament::auth()->user();
                        abort_unless(ProductBulkPriceService::canManage($user), 403);

                        try {
                            app(ProductBulkPriceService::class)->revert($record, $user);
                            Notification::make()->success()->title('Зміни цін скасовано')->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Не вдалося скасувати зміни')
                                ->body($exception->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Операція')->columns(3)->schema([
                TextEntry::make('id')->label('#'),
                TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('user.name')->label('Адміністратор')->placeholder('Система'),
                TextEntry::make('scope')->label('Область')->formatStateUsing(fn (string $state): string => ProductBulkPriceService::scopeLabel($state)),
                TextEntry::make('operation')->label('Операція')->formatStateUsing(fn (string $state): string => ProductPriceCalculator::operationOptions()[$state] ?? $state),
                TextEntry::make('value')->label('Значення')->numeric(decimalPlaces: 2),
                TextEntry::make('old_price_mode')->label('Режим старої ціни')->formatStateUsing(fn (string $state): string => ProductPriceCalculator::oldPriceModeOptions()[$state] ?? $state),
                TextEntry::make('rounding_precision')->label('Знаків після коми')->numeric(),
                IconEntry::make('include_variants')->label('Варіанти')->boolean(),
                TextEntry::make('affected_count')->label('Товарів')->numeric(),
                TextEntry::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => static::statusLabel($state))->color(fn (string $state): string => static::statusColor($state)),
                TextEntry::make('error')->label('Помилка')->columnSpanFull()->visible(fn (ProductPriceChangeBatch $record): bool => filled($record->error)),
            ]),
            RepeatableEntry::make('items')
                ->label('Змінені товари')
                ->columns(7)
                ->schema([
                    TextEntry::make('product_label')->label('Товар')->columnSpan(2),
                    TextEntry::make('old_price')->label('Ціна до')->money('UAH'),
                    TextEntry::make('new_price')->label('Ціна після')->money('UAH'),
                    TextEntry::make('old_old_price')->label('Стара до')->money('UAH')->placeholder('—'),
                    TextEntry::make('new_old_price')->label('Стара після')->money('UAH')->placeholder('—'),
                    TextEntry::make('new_discount_percent')
                        ->label('Знижка після')
                        ->formatStateUsing(fn ($state): string => (string) round((float) $state, 0))
                        ->suffix('%')
                        ->placeholder('0%'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductPriceChangeBatches::route('/'),
            'view' => Pages\ViewProductPriceChangeBatch::route('/{record}'),
        ];
    }

    private static function statusLabel(string $status): string
    {
        return [
            'processing' => 'Виконується',
            'completed' => 'Виконано',
            'failed' => 'Помилка',
            'reverted' => 'Скасовано',
        ][$status] ?? $status;
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'failed' => 'danger',
            'reverted' => 'gray',
            default => 'warning',
        };
    }
}
