<?php

namespace App\Services;

use InvalidArgumentException;

class ProductPriceCalculator
{
    public const INCREASE_PERCENT = 'increase_percent';

    public const DECREASE_PERCENT = 'decrease_percent';

    public const ADD_AMOUNT = 'add_amount';

    public const SUBTRACT_AMOUNT = 'subtract_amount';

    public const FIXED_PRICE = 'fixed_price';

    public const KEEP_OLD_PRICE = 'keep_old_price';

    public const CHANGE_BOTH = 'change_both';

    public const REMOVE_PROMOTION = 'remove_promotion';

    public static function operationOptions(): array
    {
        return [
            self::INCREASE_PERCENT => 'Збільшити на відсоток',
            self::DECREASE_PERCENT => 'Зменшити на відсоток',
            self::ADD_AMOUNT => 'Додати суму',
            self::SUBTRACT_AMOUNT => 'Відняти суму',
            self::FIXED_PRICE => 'Встановити фіксовану ціну',
        ];
    }

    public static function oldPriceModeOptions(): array
    {
        return [
            self::KEEP_OLD_PRICE => 'Змінити тільки поточну ціну',
            self::CHANGE_BOTH => 'Змінити поточну і стару ціну',
            self::REMOVE_PROMOTION => 'Прибрати акцію',
        ];
    }

    public function calculate(
        float $price,
        ?float $oldPrice,
        ?float $manualDiscountPercent,
        string $operation,
        float $value,
        string $oldPriceMode,
        int $roundingPrecision = 0,
    ): array {
        $this->assertValid($operation, $value, $oldPriceMode, $roundingPrecision);

        $newPrice = $this->applyOperation($price, $operation, $value, $roundingPrecision);
        $newOldPrice = match ($oldPriceMode) {
            self::KEEP_OLD_PRICE => $oldPrice,
            self::CHANGE_BOTH => ($oldPrice !== null && $oldPrice > 0)
                ? $this->applyOperation($oldPrice, $operation, $value, $roundingPrecision)
                : $oldPrice,
            self::REMOVE_PROMOTION => null,
        };

        $newDiscountPercent = $this->discountPercent($newPrice, $newOldPrice);

        return [
            'price' => $newPrice,
            'old_price' => $newOldPrice,
            'manual_discount_percent' => $newDiscountPercent,
        ];
    }

    public function discountPercent(float $price, ?float $oldPrice): ?float
    {
        if ($oldPrice === null || $oldPrice <= 0 || $price <= 0 || $oldPrice <= $price) {
            return null;
        }

        return round((($oldPrice - $price) / $oldPrice) * 100, 2);
    }

    public function assertValid(string $operation, float $value, string $oldPriceMode, int $roundingPrecision = 0): void
    {
        if (! array_key_exists($operation, self::operationOptions())) {
            throw new InvalidArgumentException('Невідома операція зміни ціни.');
        }

        if (! array_key_exists($oldPriceMode, self::oldPriceModeOptions())) {
            throw new InvalidArgumentException('Невідомий режим обробки старої ціни.');
        }

        if ($value < 0) {
            throw new InvalidArgumentException('Значення операції не може бути від’ємним.');
        }

        if ($operation === self::DECREASE_PERCENT && $value > 100) {
            throw new InvalidArgumentException('Зменшення не може перевищувати 100%.');
        }

        if (! in_array($roundingPrecision, [0, 1, 2], true)) {
            throw new InvalidArgumentException('Точність округлення повинна бути від 0 до 2 знаків.');
        }
    }

    private function applyOperation(float $price, string $operation, float $value, int $roundingPrecision): float
    {
        $result = match ($operation) {
            self::INCREASE_PERCENT => $price * (1 + ($value / 100)),
            self::DECREASE_PERCENT => $price * (1 - ($value / 100)),
            self::ADD_AMOUNT => $price + $value,
            self::SUBTRACT_AMOUNT => $price - $value,
            self::FIXED_PRICE => $value,
        };

        return round(max(0, $result), $roundingPrecision);
    }
}
