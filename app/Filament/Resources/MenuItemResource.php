<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\Language;
use App\Models\MenuItem;
use App\Models\Menu;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;

class MenuItemResource extends Resource
{
    use Translatable;
    protected static ?string $model = MenuItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $modelLabel = 'Пункт меню';
    protected static ?string $pluralModelLabel = 'Пункты меню';
    protected static ?string $slug = 'menu-items';
    protected static ?string $recordTitleAttribute = 'title';

    // Не показываем этот ресурс в боковом меню — управляем через ItemsTree
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        $locales        = \App\Models\Setting::getActiveLocales();;
        $defaultLocale  = Setting::value('default_language_code') ?: config('app.locale');

        return $form->schema([
            Forms\Components\Hidden::make('menu_id')
                ->required()
                ->dehydrated(true)

                // 1) дефолт при создании (модалка на ItemsTree)
                ->default(function ($livewire) {
                    // если это наша страница дерева — у неё есть public $menuId
                    if (property_exists($livewire, 'menuId') && $livewire->menuId) {
                        return (int) $livewire->menuId;
                    }

                    // фоллбек: вдруг открыли по route (не через модалку)
                    $p = request()->route('menu') ?? request()->route('record');
                    if ($p instanceof Menu) return $p->getKey();
                    if (is_array($p))     return (int) ($p['menu'] ?? reset($p));
                    return (int) $p;
                })

                // 2) при гидратации формы (редактирование) — берём из записи,
                //    иначе из $livewire->menuId, иначе из маршрута
                ->afterStateHydrated(function (Forms\Set $set, ?MenuItem $record, $state, $livewire) {
                    if ($record?->menu_id) {
                        $set('menu_id', (int) $record->menu_id);
                        return;
                    }

                    if (property_exists($livewire, 'menuId') && $livewire->menuId) {
                        $set('menu_id', (int) $livewire->menuId);
                        return;
                    }

                    $p = request()->route('menu') ?? request()->route('record');
                    if ($p instanceof Menu)   { $set('menu_id', $p->getKey()); return; }
                    if (is_numeric($p))       { $set('menu_id', (int) $p);     return; }
                }),

            Forms\Components\Select::make('parent_id')
                ->label('Родитель')
                ->options(function (Forms\Get $get, ?MenuItem $record) {
                    $menuId = $get('menu_id') ?: $record?->menu_id;

                    $items = MenuItem::query()
                        ->when($menuId, fn($q) => $q->where('menu_id', $menuId))
                        ->when($record?->exists, fn($q) => $q->whereKeyNot($record->getKey()))
                        ->orderBy('sort')
                        ->get();

                    $locale = app()->getLocale();

                    $options = $items->mapWithKeys(function (MenuItem $i) use ($locale) {
                        $title = is_array($i->title)
                            ? ($i->title[$locale] ?? reset($i->title))
                            : $i->title;

                        return [$i->id => $title ?: ('#'.$i->id)];
                    })->all();

                    // добавляем пункт "корень"
                    return [-1 => '— без родителя —'] + $options;
                })
                ->default(-1)
                ->searchable()
                ->preload()
                ->native(false)

                // при открытии формы — если null, подставляем -1
                ->afterStateHydrated(fn (Forms\Set $set, ?MenuItem $record) =>
                $set('parent_id', $record?->parent_id ?? -1)
                )

                // при сохранении оставляем -1 как есть
                ->dehydrateStateUsing(fn ($state) => $state ?? -1)

                // отключаем exists-валидацию (потому что -1 не существует)
                ->rules(['integer'])
                ->helperText('Оставьте -1 для корневого уровня'),    // никаких -1

            Translate::make()
                ->locales($locales)
                ->prefixLocaleLabel()
                ->columns(1)
                ->columnSpanFull()
                ->schema(fn (string $locale) => [
                    Forms\Components\TextInput::make("title")
                        ->label('Заголовок')
                        ->required($locale === $defaultLocale)
                        ->maxLength(255),
                ]),

            Forms\Components\Grid::make(12)->schema([
                Forms\Components\Select::make('link_type')
                    ->label('Тип ссылки')
                    ->options([
                        'page'          => 'Страница',
                        'category'      => 'Категория каталога',
                        'blog'          => 'Блог (пост)',
                        'blog_category' => 'Категория блога',
                        'url'           => 'Произвольный URL',
                    ])
                    ->reactive()
                    ->default('url')
                    ->columnSpan(4),
                Forms\Components\Hidden::make('menu_id')
                    ->required()
                    ->dehydrated(true)
                    ->default(function ($livewire) {
                        return $livewire instanceof ItemsTree
                            ? (int) $livewire->menuId
                            // Дефолт №2: из параметра роута /{menu}/items
                            : (function () {
                                $p = request()->route('menu');
                                return (int) match (true) {
                                    $p instanceof Menu => $p->getKey(),
                                    is_array($p)       => ($p['menu'] ?? reset($p)),
                                    default            => $p,
                                };
                            })();
                    }),


                // Для page (App\Models\Pages)
                Forms\Components\Select::make('target_id')
                    ->label('Целевая сущность')
                    ->options(function (Forms\Get $get) {
                        $optionsFor = function ($qb, string $jsonField = 'title', ?string $locale = null) {
                            $locale = $locale ?: app()->getLocale();
                            $path   = '$."'.$locale.'"'; // защищаем кавычки

                            return $qb->select([
                                'id',
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT($jsonField, '$path')) as t"),
                            ])
                                ->orderBy('id','desc')
                                ->pluck('t','id')
                                ->map(fn ($v, $k) => $v ?: ('#'.$k)) //fallback если пусто
                                ->all();
                        };

                        $type   = $get('link_type');
                        $locale = app()->getLocale();

                        if ($type === 'page' && class_exists(\App\Models\Pages::class)) {
                            return $optionsFor(\App\Models\Pages::query(), 'title', $locale);
                        }

                        if ($type === 'category' && class_exists(\App\Models\Shop\ProductCategory::class)) {
                            return $optionsFor(\App\Models\Shop\ProductCategory::query(), 'title', $locale);
                        }

                        if ($type === 'blog' && class_exists(\App\Models\Blog::class)) {
                            return $optionsFor(\App\Models\Blog::query(), 'title', $locale);
                        }

                        if ($type === 'blog_category' && class_exists(\App\Models\BlogCategory::class)) {
                            return $optionsFor(\App\Models\BlogCategory::query(), 'name', $locale);
                        }

                        return [];
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn (Forms\Get $get) => in_array($get('link_type'), ['page','category','blog','blog_category'], true))
                    ->columnSpan(4),

                Forms\Components\TextInput::make('url')
                    ->label('URL')
                    ->placeholder('https://... или /contact')
                    ->maxLength(512)
                    ->visible(fn (Forms\Get $get) => $get('link_type') === 'url')
                    ->columnSpan(4),

                Forms\Components\TextInput::make('icon')
                    ->label('Иконка (slug)')
                    ->helperText('Напр.: footer-phone, footer-mail — по желанию')
                    ->columnSpan(3),

                Forms\Components\Toggle::make('is_active')
                    ->label('Активно')
                    ->default(true)
                    ->columnSpan(2),
                Forms\Components\Toggle::make('auth_only')
                    ->label('Только для авторизованных')
                    ->helperText('Показывать пункт только вошедшим пользователям')
                    ->default(false)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('sort')
                    ->label('Сортировка')
                    ->numeric()
                    ->default(100)
                    ->columnSpan(2),
            ])->columns(12),

            Forms\Components\Grid::make(12)->schema([
                Forms\Components\DateTimePicker::make('visible_from')
                    ->label('Показывать с')
                    ->seconds(false)
                    ->columnSpan(6),
                Forms\Components\DateTimePicker::make('visible_to')
                    ->label('Показывать до')
                    ->seconds(false)
                    ->columnSpan(6),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $appLocale = app()->getLocale();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("title")->label('Текст')->searchable(),
                Tables\Columns\TextColumn::make('menu_id')->label('Меню')->sortable(),
                Tables\Columns\TextColumn::make('parent_id')->label('Родитель')->sortable(),
                Tables\Columns\TextColumn::make('link_type')->label('Тип'),
                Tables\Columns\IconColumn::make('is_active')->label('Активно')->boolean(),
                Tables\Columns\TextColumn::make('sort')->label('Сорт.')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Обновлено')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu_id')
                    ->label('Меню')
                    ->options(fn() => Menu::query()->orderBy('slug')->pluck('slug','id')->all()),
                Tables\Filters\TernaryFilter::make('is_active')->label('Активность')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])
            ]);
    }
    public static function getPages(): array
    {
        return [
            'index'      => Pages\ListMenuItems::route('/'),
            //'items-tree' => Pages\ItemsTree::route('/{record}/items'), // ← {record}
            'items-tree' => Pages\ItemsTree::route('/{menu}/items'),
           // 'items-tree' => \App\Filament\Resources\MenuItemResource\Pages\ItemsTree::route('/{menu}/items'),
        ];
    }
  /*  public static function getPages(): array
    {
        return [
            // НУЖЕН для хлебных крошек/back ссылок:
            'index'      => \App\Filament\Resources\MenuItemResource\Pages\ListMenuItems::route('/'),

            // Наша рабочая страница дерева конкретного меню:
            'items-tree' => \App\Filament\Resources\MenuItemResource\Pages\ItemsTree::route('/{menu}/items'),
        ];
    }*/
}
