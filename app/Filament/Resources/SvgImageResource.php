<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SvgImageResource\Pages;
use App\Filament\Resources\SvgImageResource\RelationManagers;
use App\Models\SvgImage;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\HtmlString;
use Filament\Forms\Get;



class SvgImageResource extends Resource
{
    protected static ?string $model = SvgImage::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 90;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $navigationLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('svg_image.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('svg_image.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('svg_image.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('svg_image.nav.plural_model_label');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                // левая колонка — поля
                Group::make()->schema([
                    Section::make(__('svg_image.sections.main'))->schema([
                        TextInput::make('slug')
                            ->label(__('svg_image.fields.slug'))
                            ->helperText(__('svg_image.helpers.slug'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->regex('/^[a-z0-9\-_]+$/i')
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn ($state) => trim(strtolower(preg_replace('/[^a-z0-9\-_]+/i', '-', $state)), '-')),

                        TextInput::make('title')
                            ->label(__('svg_image.fields.title'))
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(__('svg_image.fields.description'))
                            ->rows(3),
                    ])->columns(1),
                    Section::make(__('svg_image.sections.colors'))->schema([
                        ColorPicker::make('default_color')
                            ->label(__('svg_image.fields.default_color'))
                            ->helperText(__('svg_image.helpers.default_color'))
                            ->nullable(),

                        TagsInput::make('color_variants')
                            ->label(__('svg_image.fields.color_variants'))
                            ->placeholder('#111827')
                            ->helperText(__('svg_image.helpers.color_variants'))
                            ->splitKeys([',', 'Enter', ' '])     // можно вводить через запятую или Enter
                            ->suggestions(['#111827','#374151','#6B7280','#FF7500','#EF4444','#10B981'])
                            ->rule('array')
                            ->hintIcon('heroicon-o-swatch')
                            ->nullable(),
                    ]),
                    Section::make(__('svg_image.sections.svg_code'))->schema([
                        Textarea::make('svg_code')
                            ->label(__('svg_image.fields.svg_code'))
                            ->rows(18)
                            ->required()
                            ->autosize()
                            ->helperText(__('svg_image.helpers.svg_code'))
                            ->live(), // для обновления превью
                    ])->columns(1),
                ])->columnSpan(7),

                // правая колонка — превью
                Group::make()->schema([
                    Toggle::make('is_attr')
                        ->label(__('svg_image.fields.is_attr'))
                        ->helperText(__('svg_image.helpers.is_attr'))
                        ->inline(false)
                        ->default(false),
                    Section::make(__('svg_image.sections.preview'))->schema([
                        Placeholder::make('svg_preview')
                            ->label('')
                            ->content(function (Get $get) {
                                $code = $get('svg_code') ?: '';
                                if (! str_starts_with(ltrim($code), '<svg')) {
                                    return new HtmlString('<div class="text-gray-500">' . e(__('svg_image.helpers.preview_invalid')) . '</div>');
                                }

                                // выберем цвет предпросмотра:
                                $default = trim((string) $get('default_color'));
                                $presets = (array) ($get('color_variants') ?: []);
                                $previewColor = $default ?: ($presets[0] ?? null);

                                $style = $previewColor ? ' style="color: '.e($previewColor).'"' : '';

                                return new HtmlString(
                                    '<div class="border rounded-lg p-3 bg-white">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm text-gray-500">' . e(__('svg_image.helpers.preview_live')) . '</div>'
                                    . ($previewColor
                                        ? '<div class="text-xs text-gray-500">color: <span class="px-1 rounded border" style="background:'.e($previewColor).'; color:#fff">'.e($previewColor).'</span></div>'
                                        : '<div class="text-xs text-gray-400">' . e(__('svg_image.helpers.preview_no_color')) . '</div>'
                                    ) .
                                    '</div>
                <div class="max-w-full overflow-auto"'.$style.'>'.$code.'</div>
            </div>'
                                );
                            })
                            ->extraAttributes(['class' => 'prose max-w-none'])
                            ->columnSpanFull()
                    ]),
                ])->columnSpan(5),
            ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([

                TextColumn::make('slug')->label(__('svg_image.columns.slug'))->searchable()->copyable(),
                TextColumn::make('title')->label(__('svg_image.columns.title'))->searchable(),

                // ВИДИМОЕ ПРЕВЬЮ (инлайн SVG)
                ViewColumn::make('svg_preview')
                    ->label(__('svg_image.columns.svg_preview'))
                    ->view('filament.tables.columns.svg-preview')
                    ->toggleable(), // можно выключать через пикер, но по умолчанию видим,
                IconColumn::make('is_attr')
                    ->label(__('svg_image.columns.is_attr'))
                    ->boolean()
                    ->tooltip(__('svg_image.helpers.is_attr_tooltip')),
                TextColumn::make('file_path')->label(__('svg_image.columns.file_path'))->url(fn ($record) => $record->public_url, shouldOpenInNewTab: true),
                TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->label(__('svg_image.columns.updated_at')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TernaryFilter::make('is_attr')
                ->label(__('svg_image.filters.is_attr'))
                ->placeholder(__('svg_image.filter_options.all'))
                ->trueLabel(__('svg_image.filter_options.yes'))
                ->falseLabel(__('svg_image.filter_options.no'))
                ->queries(
                    true: fn (Builder $q) => $q->where('is_attr', true),
                    false: fn (Builder $q) => $q->where('is_attr', false),
                    blank: fn (Builder $q) => $q, // без фильтра
                ),])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSvgImages::route('/'),
            'create' => Pages\CreateSvgImage::route('/create'),
            'edit'   => Pages\EditSvgImage::route('/{record}/edit'),
        ];
    }
}
