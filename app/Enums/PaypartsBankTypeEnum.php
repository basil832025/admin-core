<?php

namespace App\Enums;

enum PaypartsBankTypeEnum: string
{
    case PrivatBank = 'privatbank';
    case MonoBank = 'monobank';

    public function label(): string
    {
        return match ($this) {
            self::PrivatBank => 'Приватбанк',
            self::MonoBank => 'Монобанк',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
