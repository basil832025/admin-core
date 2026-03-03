<?php

namespace App\Filament\Resources\Callcenter;

use App\Filament\Resources\Callcenter\SyncedCatalogResource\Pages;
use App\Models\Callcenter\Source;
use App\Models\Callcenter\SourceCategory;
use App\Models\Callcenter\SourceProduct;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SyncedCatalogResource extends Resource
{
    protected static ?string $model = SourceProduct::class;

    protected static ?string $slug = 'callcenter/synced-catalog';
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?int $navigationSort = 50;
    protected static ?string $navigationGroup = 'Магазин';

    public static function getNavigationLabel(): string
    {
        return 'Справочники сайтов';
    }

    public static function getPluralLabel(): string
    {
        return 'Синхронизированный каталог';
    }

    public static function getModelLabel(): string
    {
        return 'Синхронизированный товар';
    }

    protected static function canAccessModule(): bool
    {
        $user = auth('admin')->user();

        if (! $user instanceof User) {
            return false;
        }

        $permissions = [
            'access_synced_site_directories',
            'access_callcenter_orders',
            'view_any_callcenter::order',
            'view_callcenter::order',
        ];

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['source', 'localProduct']))
            ->defaultSort('id', 'desc')
            ->groups([
                Group::make('external_category_id')
                    ->label('Группа')
                    ->getTitleFromRecordUsing(fn (SourceProduct $record): string => static::resolveGroupName($record))
                    ->collapsible(),
            ])
            ->defaultGroup('external_category_id')
            ->columns([
                TextColumn::make('source.name')
                    ->label('Сайт')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                ImageColumn::make('preview')
                    ->label('Фото')
                    ->getStateUsing(function (SourceProduct $record): ?string {
                        $base = rtrim((string) ($record->source?->base_url ?? ''), '/');
                        $imageId = trim((string) ($record->external_parent_id ?: $record->external_id));

                        $local = trim((string) ($record->localProduct?->main_image_url ?? ''));
                        $img = $local !== ''
                            ? $local
                            : trim((string) data_get($record->payload, 'img', ''));

                        if ($img === '') {
                            return ($base !== '' && $imageId !== '')
                                ? ($base . '/images/catalog_products/' . $imageId . '.1.b.png')
                                : null;
                        }

                        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
                            $sourceHost = parse_url((string) ($record->source?->base_url ?? ''), PHP_URL_HOST);
                            $imageHost = parse_url($img, PHP_URL_HOST);

                            if ($sourceHost && $imageHost && strcasecmp($sourceHost, $imageHost) !== 0) {
                                if (str_contains(mb_strtolower($imageHost), 'pirogovaya.online')) {
                                    $path = (string) parse_url($img, PHP_URL_PATH);
                                    $query = (string) parse_url($img, PHP_URL_QUERY);

                                    if ($base !== '') {
                                        return $base . '/' . ltrim($path, '/') . ($query !== '' ? ('?' . $query) : '');
                                    }
                                }
                            }

                            return $img;
                        }

                        if (str_starts_with($img, '//')) {
                            return 'https:' . $img;
                        }

                        if ($base === '') {
                            return $img;
                        }

                        return $base . '/' . ltrim($img, '/');
                    })
                    ->defaultImageUrl(url('/images/placeholder-4x3.jpg'))
                    ->height(52)
                    ->width(72),

                TextColumn::make('title')
                    ->label('Товар')
                    ->description(fn (SourceProduct $record): ?string => $record->size_label ?: null)
                    ->searchable(),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('UAH')
                    ->sortable(),

                TextColumn::make('localProduct.title')
                    ->label('Локальный товар')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['uk'] ?? $state['ru'] ?? $state['en'] ?? '—') : ($state ?: '—'))
                    ->description(fn (SourceProduct $record): string => $record->localProduct?->code2 ?: 'Нет связи')
                    ->toggleable(),

                TextColumn::make('external_id')
                    ->label('Внешний ID')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('group_name')
                    ->label('Группа')
                    ->getStateUsing(fn (SourceProduct $record): string => static::resolveGroupName($record))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source_id')
                    ->label('Сайт')
                    ->options(fn () => Source::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('source_category')
                    ->label('Группа')
                    ->options(function (): array {
                        return SourceCategory::query()
                            ->with('source:id,name')
                            ->orderBy('source_id')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(function (SourceCategory $category): array {
                                $title = $category->title;
                                $name = is_array($title)
                                    ? ((string) ($title['uk'] ?? $title['ru'] ?? $title['en'] ?? $category->external_id))
                                    : (string) $title;
                                $source = $category->source?->name ?: 'Сайт';
                                $key = $category->source_id . ':' . $category->external_id;

                                return [
                                    $key => $source . ' / ' . $name,
                                ];
                            })
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $value = (string) ($data['value'] ?? '');
                        if ($value === '' || ! str_contains($value, ':')) {
                            return $query;
                        }

                        [$sourceId, $externalId] = explode(':', $value, 2);

                        return $query
                            ->where('source_id', (int) $sourceId)
                            ->where('external_category_id', $externalId);
                    })
                    ->searchable(),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([])
            ->bulkActions([])
            ->paginated([25, 50, 100]);
    }

    protected static function resolveGroupName(SourceProduct $record): string
    {
        $category = data_get($record->payload, 'categories.0');

        if (is_array($category)) {
            $title = $category['title'] ?? null;

            if (is_array($title)) {
                $name = (string) ($title['uk'] ?? $title['ru'] ?? $title['en'] ?? '');
                if ($name !== '') {
                    return $name;
                }
            }

            if (is_string($title) && $title !== '') {
                return $title;
            }

            if (isset($category['id']) && (string) $category['id'] !== '') {
                return 'ID: ' . (string) $category['id'];
            }
        }

        if ($record->external_category_id) {
            return 'ID: ' . $record->external_category_id;
        }

        return 'Без группы';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSyncedCatalog::route('/'),
        ];
    }
}
