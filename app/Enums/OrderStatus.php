<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
case New        = 'new';         // Новый
case Processing = 'processing';  // В обработке
case OnHold     = 'on_hold';     // Отложен
case Filling    = 'filling';     // Начинка
case Molding    = 'molding';     // Лепка
case Baking     = 'baking';      // Печь
case Prepared   = 'prepared';    // Приготовлен
case Assembled  = 'assembled';   // Собран
case Shipped   = 'shipped';    // Доставка (в пути)
case Delivered  = 'delivered';   // Доставлен
case Cancelled  = 'cancelled';   // Отменен

    public function getLabel(): string
{
    return match ($this) {
        self::New        => 'Новый',
        self::Processing => 'В обработке',
        self::OnHold     => 'Отложен',
        self::Filling    => 'Начинка',
        self::Molding    => 'Лепка',
        self::Baking     => 'Печь',
        self::Prepared   => 'Приготовлен',
        self::Assembled  => 'Собран',
        self::Shipped   => 'Доставка',
        self::Delivered  => 'Доставлен',
        self::Cancelled  => 'Отменен',
    };
}

    public function getColor(): string|array|null
{
    // названия должны совпадать с токенами из ->colors() панели
    return match ($this) {
        self::New        => 'violet',
        self::Processing => 'amber',
        self::OnHold     => 'gray',
        self::Filling    => 'teal',
        self::Molding    => 'indigo',
        self::Baking     => 'orange',
        self::Prepared   => 'lime',
        self::Assembled  => 'cyan',
        self::Shipped    => 'sky',
        self::Delivered  => 'success',
        self::Cancelled  => 'danger',
    };
}
    function userCanSetStatus(OrderStatus $status): bool
    {
        $u = auth()->user();

        // общее право — разрешает все статусы
        if ($u->can('set_order_status')) {
            return true;
        }

        // индивидуальное право по статусу
        return $u->can('set_order_status_' . $status->value);
    }
    public function getIcon(): ?string
{
    return match ($this) {
        self::New        => 'heroicon-m-sparkles',
        self::Processing => 'heroicon-m-arrow-path',
        self::OnHold     => 'heroicon-m-pause-circle',
        self::Filling    => 'heroicon-m-beaker',
        self::Molding    => 'heroicon-m-puzzle-piece',
        self::Baking     => 'heroicon-m-fire',
        self::Prepared   => 'heroicon-m-check',
        self::Assembled  => 'heroicon-m-cube',
        self::Shipped   => 'heroicon-m-truck',
        self::Delivered  => 'heroicon-m-check-badge',
        self::Cancelled  => 'heroicon-m-x-circle',
    };
}
    public function rank(): int
{
    return match ($this) {

        self::New        => 10,
        self::OnHold     => 15,
        self::Processing => 20,
        self::Filling    => 30,
        self::Molding    => 40,
        self::Baking     => 50,
        self::Prepared   => 60,
        self::Assembled  => 70,
        self::Shipped    => 80,
        self::Delivered  => 90,
        self::Cancelled  => 100,   // особый случай, пусть будет «вне процесса»
    };
}
    public static function sorted(): array
{
    return collect(self::cases())->sortBy(fn ($s) => $s->rank())->all();
}

    public static function options(): array
{
    return collect(self::sorted())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all();
}

    public static function iconsMap(): array
{
    return collect(self::sorted())->mapWithKeys(fn ($s) => [$s->value => $s->getIcon()])->all();
}

    public static function colorsMap(): array
{
    return collect(self::sorted())->mapWithKeys(fn ($s) => [$s->value => $s->getColor()])->all();
}
}
