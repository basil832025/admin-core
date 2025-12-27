<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
case Cart       = 'cart';
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
        self::Cart       => __('order_status.cart'),
        self::New        => __('order_status.new'),
        self::Processing => __('order_status.processing'),
        self::OnHold     => __('order_status.on_hold'),
        self::Filling    => __('order_status.filling'),
        self::Molding    => __('order_status.molding'),
        self::Baking     => __('order_status.baking'),
        self::Prepared   => __('order_status.prepared'),
        self::Assembled  => __('order_status.assembled'),
        self::Shipped   => __('order_status.shipped'),
        self::Delivered  => __('order_status.delivered'),
        self::Cancelled  => __('order_status.cancelled'),
    };
}

    public function getColor(): string|array|null
{
    // названия должны совпадать с токенами из ->colors() панели
    return match ($this) {
        self::Cart       => 'gray',
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
        self::Cancelled  => 'gray',
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
        self::Cart       => 'heroicon-m-shopping-cart',
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
        self::Cart       => 5,
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

    /**
     * Получить цвета для фронтенда (hex коды)
     * Возвращает массив: ['bg' => hex, 'text' => hex]
     */
    public function getFrontendColors(): array
{
    return match ($this) {
        self::Cart       => ['bg' => '#E5E7EB', 'text' => '#374151'], // gray-200, gray-700
        self::New        => ['bg' => '#E9D5FF', 'text' => '#6B21A8'], // violet-200, violet-800
        self::Processing => ['bg' => '#FDE68A', 'text' => '#92400E'], // amber-200, amber-800
        self::OnHold     => ['bg' => '#E5E7EB', 'text' => '#374151'], // gray-200, gray-700
        self::Filling    => ['bg' => '#99F6E4', 'text' => '#134E4A'], // teal-200, teal-800
        self::Molding    => ['bg' => '#C7D2FE', 'text' => '#3730A3'], // indigo-200, indigo-800
        self::Baking     => ['bg' => '#FED7AA', 'text' => '#9A3412'], // orange-200, orange-800
        self::Prepared   => ['bg' => '#D9F99D', 'text' => '#365314'], // lime-200, lime-800
        self::Assembled  => ['bg' => '#A5F3FC', 'text' => '#164E63'], // cyan-200, cyan-800
        self::Shipped    => ['bg' => '#BAE6FD', 'text' => '#0C4A6E'], // sky-200, sky-900
        self::Delivered  => ['bg' => '#BBF7D0', 'text' => '#166534'], // green-200 (Success), green-800
        self::Cancelled  => ['bg' => '#E5E7EB', 'text' => '#374151'], // gray-200, gray-700
    };
}
}
