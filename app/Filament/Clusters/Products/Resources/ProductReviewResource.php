<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products;
use App\Filament\Clusters\Products\Resources\ProductReviewResource\Pages;
use App\Filament\Clusters\Products\Resources\ProductReviewResource\RelationManagers;

use App\Models\Setting;
use App\Models\Shop\ProductReview;
use App\Enums\ReviewStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;

class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationLabel = null;
    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('product_review.nav.navigation_label');
    }

    public static function form(Form $form): Form
    {
     //   $locales = static::getActiveLocales();
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label(__('product_review.fields.product'))
                ->options(\app\Models\Shop\Product::query()
                    ->MainProduct()->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$defaultLocale}\"')) ASC")
                    ->pluck('title', 'id')
                                     )

              //  ->relationship('product', 'title') // при необходимости замените на нужное поле
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('name')
                ->label(__('product_review.fields.name'))
                ->required()
                ->maxLength(120),

            Forms\Components\TextInput::make('email')
                ->label(__('product_review.fields.email'))
                ->email()
                ->required()
                ->maxLength(190),

            Forms\Components\Radio::make('rating')
                ->label(__('product_review.fields.rating'))
                ->options([
                    1 => '★☆☆☆☆',
                    2 => '★★☆☆☆',
                    3 => '★★★☆☆',
                    4 => '★★★★☆',
                    5 => '★★★★★',
                ])
                ->inline()
                ->required(),

            Forms\Components\Textarea::make('content')
                ->label(__('product_review.fields.review'))
                ->rows(5),

            Forms\Components\Select::make('status')
                ->label(__('product_review.fields.status'))
                ->options(ReviewStatus::labels())
                ->default(ReviewStatus::Pending->value)
            ->required()
            ->native(false),

            Forms\Components\TextInput::make('ip')->label(__('product_review.fields.ip'))->readOnly()->dehydrated(false),
            Forms\Components\TextInput::make('user_agent')->label(__('product_review.fields.user_agent'))->readOnly()->dehydrated(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.title')
                    ->label(__('product_review.columns.product'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('product_review.columns.name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('product_review.columns.email'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('rating')
                    ->label(__('product_review.columns.rating'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('product_review.columns.status'))
                    ->formatStateUsing(fn ($state) =>
                        ReviewStatus::labels()[
                        $state instanceof ReviewStatus ? $state->value : (string) $state
                        ] ?? (string) $state
                    )
                    ->colors([
                        'warning' => fn ($state) => ($state instanceof ReviewStatus ? $state : ReviewStatus::from((string) $state)) === ReviewStatus::Pending,
                        'success' => fn ($state) => ($state instanceof ReviewStatus ? $state : ReviewStatus::from((string) $state)) === ReviewStatus::Published,
                        'danger'  => fn ($state) => ($state instanceof ReviewStatus ? $state : ReviewStatus::from((string) $state)) === ReviewStatus::Rejected,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('product_review.columns.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
        Tables\Filters\SelectFilter::make('status')
            ->label(__('product_review.filters.status'))
            ->options(ReviewStatus::labels()),
    ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
            Tables\Actions\Action::make('publish')
                ->label(__('product_review.actions.publish'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (ProductReview $record) => $record->status === \App\Enums\ReviewStatus::Pending)
                ->action(fn (ProductReview $record) => $record->update(['status' => ReviewStatus::Published])),

            Tables\Actions\Action::make('reject')
                ->label(__('product_review.actions.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (ProductReview $record) => $record->status !== \App\Enums\ReviewStatus::Rejected)
                ->action(fn (ProductReview $record) => $record->update(['status' => ReviewStatus::Rejected])),
        ])
        ->bulkActions([
            Tables\Actions\BulkAction::make('bulk_publish')
                ->label(__('product_review.actions.publish_bulk'))
                ->color('success')
                ->action(fn ($records) => $records->each->update(['status' => ReviewStatus::Published])),
            Tables\Actions\BulkAction::make('bulk_reject')
                ->label(__('product_review.actions.reject_bulk'))
                ->color('danger')
                ->action(fn ($records) => $records->each->update(['status' => ReviewStatus::Rejected])),
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }
    protected static function pendingCount(): int
    {
        return Cache::remember(
            'nav:reviews:pending:'.auth()->id(),
            now()->addMinute(),
            fn () => ProductReview::query()
                ->where('status', ReviewStatus::Pending->value) // или ->where('status', ReviewStatus::Pending)
        ->count()
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingCount();
        return $count ? (string) $count : null; // верни null, чтобы бейдж не показывался при 0
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::pendingCount() ? 'warning' : 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::pendingCount()
            ? 'На модерации'
            : null;
    }
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProductReviews::route('/'),
            'create' => Pages\CreateProductReview::route('/create'),
            'view'   => Pages\ViewProductReview::route('/{record}'),
            'edit'   => Pages\EditProductReview::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string { return __('product_review.nav.model_label'); }
    public static function getPluralModelLabel(): string { return __('product_review.nav.plural_model_label'); }
}
