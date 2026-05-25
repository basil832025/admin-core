<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteTemplateOverrideResource\Pages;
use App\Models\SiteTemplateOverride;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Wiebenieuwenhuis\FilamentCodeEditor\Components\CodeEditor;

class SiteTemplateOverrideResource extends Resource
{
    protected static ?string $model = SiteTemplateOverride::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Шаблоны сайта';

    protected static ?string $modelLabel = 'Шаблон сайта';

    protected static ?string $pluralModelLabel = 'Шаблоны сайта';

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasRole')
            && $user->hasRole(config('shield.super_admin.name', 'super_admin'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function canEdit($record): bool
    {
        return static::canAccessModule();
    }

    public static function buildDiffHtml(?SiteTemplateOverride $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('');
        }

        $originalLines = preg_split("/(\r\n|\r|\n)/", (string) $record->original_snapshot) ?: [];
        $currentLines = preg_split("/(\r\n|\r|\n)/", (string) ($record->override_body ?? '')) ?: [];
        $rows = static::buildDiffRows($originalLines, $currentLines);

        $html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">';
        $html .= '<div><div style="font-weight:700;margin-bottom:8px;">Оригинал</div>';
        $html .= '<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:auto;max-height:70vh;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-family:monospace;font-size:12px;line-height:1.5;">';
        foreach ($rows as $row) {
            $left = $row['left'];
            $bg = match ($row['type']) {
                'removed', 'changed' => '#fef2f2',
                default => '#ffffff',
            };
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="width:52px;padding:4px 8px;border-right:1px solid #e5e7eb;color:#64748b;vertical-align:top;">' . ($row['left_line'] ?? '') . '</td>';
            $html .= '<td style="padding:4px 8px;white-space:pre-wrap;word-break:break-word;">' . e($left) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></div></div>';

        $html .= '<div><div style="font-weight:700;margin-bottom:8px;">Текущая версия из БД</div>';
        $html .= '<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:auto;max-height:70vh;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-family:monospace;font-size:12px;line-height:1.5;">';
        foreach ($rows as $row) {
            $right = $row['right'];
            $bg = match ($row['type']) {
                'added', 'changed' => '#ecfeff',
                default => '#ffffff',
            };
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="width:52px;padding:4px 8px;border-right:1px solid #e5e7eb;color:#64748b;vertical-align:top;">' . ($row['right_line'] ?? '') . '</td>';
            $html .= '<td style="padding:4px 8px;white-space:pre-wrap;word-break:break-word;">' . e($right) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></div></div>';
        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * @param  array<int, string>  $originalLines
     * @param  array<int, string>  $currentLines
     * @return array<int, array{type:string,left:string,right:string,left_line:?int,right_line:?int}>
     */
    protected static function buildDiffRows(array $originalLines, array $currentLines): array
    {
        $m = count($originalLines);
        $n = count($currentLines);
        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $originalLines[$i] === $currentLines[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $rows = [];
        $i = 0;
        $j = 0;

        while ($i < $m && $j < $n) {
            if ($originalLines[$i] === $currentLines[$j]) {
                $rows[] = [
                    'type' => 'same',
                    'left' => $originalLines[$i],
                    'right' => $currentLines[$j],
                    'left_line' => $i + 1,
                    'right_line' => $j + 1,
                ];
                $i++;
                $j++;
                continue;
            }

            if ($lcs[$i + 1][$j] === $lcs[$i][$j + 1]) {
                $rows[] = [
                    'type' => 'changed',
                    'left' => $originalLines[$i],
                    'right' => $currentLines[$j],
                    'left_line' => $i + 1,
                    'right_line' => $j + 1,
                ];
                $i++;
                $j++;
                continue;
            }

            if ($lcs[$i + 1][$j] > $lcs[$i][$j + 1]) {
                $rows[] = [
                    'type' => 'removed',
                    'left' => $originalLines[$i],
                    'right' => '',
                    'left_line' => $i + 1,
                    'right_line' => null,
                ];
                $i++;
                continue;
            }

            $rows[] = [
                'type' => 'added',
                'left' => '',
                'right' => $currentLines[$j],
                'left_line' => null,
                'right_line' => $j + 1,
            ];
            $j++;
        }

        while ($i < $m) {
            $rows[] = [
                'type' => 'removed',
                'left' => $originalLines[$i],
                'right' => '',
                'left_line' => $i + 1,
                'right_line' => null,
            ];
            $i++;
        }

        while ($j < $n) {
            $rows[] = [
                'type' => 'added',
                'left' => '',
                'right' => $currentLines[$j],
                'left_line' => null,
                'right_line' => $j + 1,
            ];
            $j++;
        }

        return $rows;
    }

    public static function templateTypeLabelFromKey(string $key): string
    {
        return match (true) {
            $key === 'home' => 'Стартовая',
            str_starts_with($key, 'pages.blog.') => 'Блог',
            str_starts_with($key, 'pages.') => 'Страницы',
            default => 'Прочее',
        };
    }

    public static function hasDifferences(?SiteTemplateOverride $record): bool
    {
        if (! $record) {
            return false;
        }

        return (string) ($record->override_body ?? '') !== (string) ($record->original_snapshot ?? '');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('template_tabs')
                ->tabs([
                    Tabs\Tab::make('Основное')
                        ->schema([
                            Grid::make(12)->schema([
                                TextInput::make('title')
                                    ->label('Название')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(4),
                                TextInput::make('key')
                                    ->label('Ключ')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),
                                TextInput::make('source_path')
                                    ->label('Исходный файл')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(5),
                                Toggle::make('is_active')
                                    ->label('Использовать версию из БД')
                                    ->columnSpan(3),
                                TextInput::make('engine')
                                    ->label('Движок')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                TextInput::make('last_synced_at')
                                    ->label('Оригинал синхронизирован')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) => $state ? (string) $state : '-')
                                    ->dehydrated(false)
                                    ->columnSpan(3),
                                Textarea::make('change_note')
                                    ->label('Комментарий к изменению')
                                    ->helperText('Необязательно. Попадет в историю при следующем изменении шаблона.')
                                    ->dehydrated(false)
                                    ->rows(2)
                                    ->columnSpan(4),
                            ]),
                        ]),
                    Tabs\Tab::make('Текущий шаблон')
                        ->schema([
                            Section::make('Редактируемый шаблон')
                                ->description('Если поле пустое или шаблон не активен, сайт использует оригинальный файл из resources/views.')
                                ->schema([
                                    CodeEditor::make('override_body')
                                        ->label('Код шаблона')
                                        ->default('')
                                        ->formatStateUsing(fn (mixed $state): string => is_string($state) ? $state : '')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tabs\Tab::make('Оригинал')
                        ->schema([
                            Placeholder::make('original_preview')
                                ->label('Оригинальный шаблон из файла')
                                ->content(fn (?SiteTemplateOverride $record) => new HtmlString('<pre style="white-space:pre-wrap;overflow:auto;max-height:70vh;padding:12px;background:#0f172a;color:#e2e8f0;border-radius:10px;">' . e((string) $record?->original_snapshot) . '</pre>')),
                        ]),
                    Tabs\Tab::make('История')
                        ->schema([
                            Placeholder::make('history_preview')
                                ->label('Последние изменения')
                                ->content(function (?SiteTemplateOverride $record): HtmlString {
                                    if (! $record) {
                                        return new HtmlString('');
                                    }

                                    $items = $record->versions()->latest('created_at')->limit(20)->get();

                                    if ($items->isEmpty()) {
                                        return new HtmlString('<div style="color:#64748b;">Изменений пока нет.</div>');
                                    }

                                    $html = '<div style="display:flex;flex-direction:column;gap:12px;">';
                                    foreach ($items as $version) {
                                        $html .= '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;">';
                                        $html .= '<div style="font-weight:600;margin-bottom:8px;">' . e(optional($version->created_at)->format('d.m.Y H:i:s') ?? '-') . '</div>';
                                        if ($version->change_note) {
                                            $html .= '<div style="margin-bottom:8px;color:#475569;">' . e($version->change_note) . '</div>';
                                        }
                                        $html .= '<div style="margin-bottom:8px;"><button type="button" wire:click="restoreVersionFromHistory(' . (int) $version->getKey() . ')" style="padding:6px 10px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-size:12px;cursor:pointer;">Восстановить эту версию</button></div>';
                                        $html .= '<pre style="white-space:pre-wrap;overflow:auto;max-height:200px;margin:0;padding:10px;background:#0f172a;color:#e2e8f0;border-radius:8px;">' . e(Str::limit((string) $version->body, 4000)) . '</pre>';
                                        $html .= '</div>';
                                    }
                                    $html .= '</div>';

                                    return new HtmlString($html);
                                }),
                        ]),
                    Tabs\Tab::make('Сравнение')
                        ->schema([
                            Placeholder::make('diff_preview')
                                ->label('Оригинал vs текущая версия')
                                ->content(fn (?SiteTemplateOverride $record) => static::buildDiffHtml($record)),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Название')->searchable()->sortable(),
                TextColumn::make('key')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::templateTypeLabelFromKey($state))
                    ->color(fn (string $state) => match (static::templateTypeLabelFromKey($state)) {
                        'Стартовая' => 'warning',
                        'Блог' => 'success',
                        'Страницы' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('key')->label('Ключ')->searchable()->copyable(),
                TextColumn::make('diff_status')
                    ->label('Отличия')
                    ->badge()
                    ->state(fn (SiteTemplateOverride $record): string => static::hasDifferences($record) ? 'Есть отличия' : 'Нет отличий')
                    ->color(fn (SiteTemplateOverride $record): string => static::hasDifferences($record) ? 'warning' : 'success'),
                TextColumn::make('source_path')->label('Файл')->toggleable(),
                TextColumn::make('override_body')
                    ->label('Содержимое')
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: function ($query, string $search) {
                        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';

                        return $query->where(function ($inner) use ($like) {
                            $inner->where('override_body', 'like', $like)
                                ->orWhere('original_snapshot', 'like', $like);
                        });
                    }),
                IconColumn::make('is_active')->label('Активен')->boolean(),
                TextColumn::make('updated_at')->label('Изменен')->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                SelectFilter::make('template_type')
                    ->label('Тип шаблона')
                    ->options([
                        'home' => 'Стартовая',
                        'pages' => 'Страницы',
                        'blog' => 'Блог',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'home' => $query->where('key', 'home'),
                            'pages' => $query->where('key', 'like', 'pages.%')->where('key', 'not like', 'pages.blog.%'),
                            'blog' => $query->where('key', 'like', 'pages.blog.%'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteTemplateOverrides::route('/'),
            'edit' => Pages\EditSiteTemplateOverride::route('/{record}/edit'),
        ];
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

}
