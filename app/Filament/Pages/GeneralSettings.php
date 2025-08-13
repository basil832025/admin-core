<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Pages\Actions\Action;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Illuminate\Support\Str;
class GeneralSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationGroup = 'Настройки';
    protected static ?string $navigationLabel = 'Общие настройки сайта';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.general-settings';

    public $site_name;
    public $logo_path;
    public $favicon_path;
    public $phone;
    public $email;
    public $social_links = [];
    public $default_language_code;
    public $admin_color_scheme; // ← вот это надо добавить
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) return false;

        // Две возможные схемы именования у Shield:
        $keys = [
            // из slug страницы: "general-settings" -> "page_general_settings"
            'page_' . Str::of(static::getSlug())->snake(),
            // из имени класса: "GeneralSettings" -> "page_GeneralSettings"
            'page_' . class_basename(static::class),
        ];

        foreach ($keys as $key) {
            if ($user->can($key)) {
                return true;
            }
        }
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->button()                 // делает её обычной кнопкой
                ->color('primary')          // основной цвет
                ->icon('heroicon-s-check')  // иконка (опционально)
                ->action('save'),           // указываем метод save()
        ];
    }
    protected function getFormSchema(): array
    {
        return [
            TextInput::make('site_name')
                ->label('Название сайта')
                ->required(),
            Section::make('Контакты')
                ->description('Заполните ваши контактные данные:')
                ->schema([

                    TextInput::make('phone')
                        ->label('Телефон'),

                    TextInput::make('email')
                        ->label('Email'),
                ])->columns(2)
                ->compact(),
            Section::make('Языки и цвета')
                ->description('Выберите язык по умолчанию для сайта и цветовую схему админки:')
                ->schema([
                    Select::make('default_language_code')
                        ->label('Язык по умолчанию')
                        ->options(\App\Models\Language::pluck('name', 'code'))
                        ->required(),
                    Select::make('admin_color_scheme')
                        ->label('Цветовая схема админки')
                        ->options([
                            'Rose' => 'Красная',
                            'gray'   => 'Серая',
                            'Blue'   => 'Голубая',
                            'Amber' => 'Жёлтая (по умолчанию)',
                            'Emerald'=> 'Зелёная',
                            'Orange'=> 'Оранжевая',
                        ])
                        ->default('primary')
                    //    ->required()
                    ,
                ])->columns(2)
                ->compact(),
            // Группируем логотип и favicon в две колонки
            Group::make()
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Логотип')
                        ->image()
                        ->directory('settings')
                        ->required(),

                    FileUpload::make('favicon_path')
                        ->label('Favicon')
                        ->image()
                        ->directory('settings')
                        //->required()
                    ,
                ])
                ->columns(2)
                ->columnSpanFull()      // чтобы занимать всю ширину формы
                ->label('Изображения сайта'),

            Repeater::make('social_links')
                ->label('Соцсети')
                ->schema([
                    TextInput::make('platform')
                        ->label('Платформа (Facebook, Instagram…)')
                        ->required(),
                    TextInput::make('url')
                        ->label('Ссылка')
                        ->url()
                        ->required(),
                ])
                ->columns(2),


        ];
    }

    public function mount(): void
    {
        $settings = Setting::first() ?? Setting::create([]);
        $this->form->fill($settings->toArray());
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = Setting::first();
        $settings->update($data);
        Notification::make()
            ->title('Успех')
            ->success()
            ->body('Настройки сохранены')
            ->send();
    }
}
