<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\LoyaltyExpirationResource\Pages;
use App\Filament\Resources\Shop\LoyaltyAccountResource;
use App\Models\Shop\LoyaltyTransaction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LoyaltyExpirationResource extends Resource
{
    protected static ?string $model = LoyaltyTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 1000;

    public static function getNavigationGroup(): ?string
    {
        return __('loyalty_expiration.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty_expiration.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty_expiration.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty_expiration.nav.plural_model_label');
    }

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->can('view_any_shop::loyalty::account') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
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
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('loyalty_expiration.columns.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.id')
                    ->label(__('loyalty_expiration.columns.account_id'))
                    ->url(fn (LoyaltyTransaction $record): string => LoyaltyAccountResource::getUrl('view', ['record' => $record->account_id]))
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.client_id')
                    ->label(__('loyalty_expiration.columns.client_id'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account.phone')
                    ->label(__('loyalty_expiration.columns.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('loyalty_expiration.columns.amount'))
                    ->state(fn (LoyaltyTransaction $record): string => number_format(abs((float) $record->amount), 2, '.', ' '))
                    ->sortable(),
                Tables\Columns\TextColumn::make('meta.accrual_id')
                    ->label(__('loyalty_expiration.columns.accrual_id'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label(__('loyalty_expiration.columns.balance_after'))
                    ->money('UAH', divideBy: false)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('quick_period')
                    ->label(__('loyalty_expiration.filters.quick_period'))
                    ->options([
                        'today' => __('loyalty_expiration.filters.today'),
                        '7_days' => __('loyalty_expiration.filters.last_7_days'),
                        'month' => __('loyalty_expiration.filters.this_month'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'today' => $query->whereDate('created_at', today()),
                            '7_days' => $query->where('created_at', '>=', now()->subDays(7)->startOfDay()),
                            'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                            default => $query,
                        };
                    }),
                Filter::make('created_at')
                    ->label(__('loyalty_expiration.filters.period'))
                    ->form([
                        DatePicker::make('from')->label(__('loyalty_expiration.filters.from')),
                        DatePicker::make('until')->label(__('loyalty_expiration.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators['from'] = __('loyalty_expiration.filters.from') . ' ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators['until'] = __('loyalty_expiration.filters.until') . ' ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('account')
            ->where('type', LoyaltyTransaction::TYPE_EXPIRE)
            ->where('source', 'system_expire');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyExpirations::route('/'),
        ];
    }
}
