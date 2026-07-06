<?php

namespace Tests\Unit;

use App\Services\ProductPriceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductPriceCalculatorTest extends TestCase
{
    private ProductPriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ProductPriceCalculator;
    }

    #[DataProvider('operationProvider')]
    public function test_it_applies_each_operation(string $operation, float $value, float $expected): void
    {
        $result = $this->calculator->calculate(
            200,
            null,
            null,
            $operation,
            $value,
            ProductPriceCalculator::KEEP_OLD_PRICE,
            2,
        );

        $this->assertSame($expected, $result['price']);
    }

    public static function operationProvider(): array
    {
        return [
            'increase percent' => [ProductPriceCalculator::INCREASE_PERCENT, 7, 214.0],
            'decrease percent' => [ProductPriceCalculator::DECREASE_PERCENT, 10, 180.0],
            'add amount' => [ProductPriceCalculator::ADD_AMOUNT, 25, 225.0],
            'subtract amount' => [ProductPriceCalculator::SUBTRACT_AMOUNT, 50, 150.0],
            'fixed price' => [ProductPriceCalculator::FIXED_PRICE, 319.99, 319.99],
        ];
    }

    public function test_subtraction_cannot_make_price_negative(): void
    {
        $result = $this->calculator->calculate(
            100,
            null,
            null,
            ProductPriceCalculator::SUBTRACT_AMOUNT,
            150,
            ProductPriceCalculator::KEEP_OLD_PRICE,
            2,
        );

        $this->assertSame(0.0, $result['price']);
    }

    public function test_keep_old_price_changes_only_current_price_and_recalculates_discount(): void
    {
        $result = $this->calculator->calculate(
            189,
            315,
            40,
            ProductPriceCalculator::INCREASE_PERCENT,
            7,
            ProductPriceCalculator::KEEP_OLD_PRICE,
            2,
        );

        $this->assertSame(202.23, $result['price']);
        $this->assertSame(315.0, $result['old_price']);
        $this->assertSame(35.8, $result['manual_discount_percent']);
    }

    public function test_change_both_applies_the_same_operation_to_both_prices(): void
    {
        $result = $this->calculator->calculate(
            189,
            315,
            40,
            ProductPriceCalculator::INCREASE_PERCENT,
            7,
            ProductPriceCalculator::CHANGE_BOTH,
            2,
        );

        $this->assertSame(202.23, $result['price']);
        $this->assertSame(337.05, $result['old_price']);
        $this->assertSame(40.0, $result['manual_discount_percent']);
    }

    public function test_remove_promotion_clears_old_price_and_discount(): void
    {
        $result = $this->calculator->calculate(
            189,
            315,
            40,
            ProductPriceCalculator::INCREASE_PERCENT,
            7,
            ProductPriceCalculator::REMOVE_PROMOTION,
            2,
        );

        $this->assertSame(202.23, $result['price']);
        $this->assertNull($result['old_price']);
        $this->assertNull($result['manual_discount_percent']);
    }

    public function test_fixed_price_with_change_both_removes_effective_discount(): void
    {
        $result = $this->calculator->calculate(
            189,
            315,
            40,
            ProductPriceCalculator::FIXED_PRICE,
            250,
            ProductPriceCalculator::CHANGE_BOTH,
            2,
        );

        $this->assertSame(250.0, $result['price']);
        $this->assertSame(250.0, $result['old_price']);
        $this->assertNull($result['manual_discount_percent']);
    }

    public function test_decrease_over_one_hundred_percent_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(
            100,
            null,
            null,
            ProductPriceCalculator::DECREASE_PERCENT,
            101,
            ProductPriceCalculator::KEEP_OLD_PRICE,
        );
    }

    public function test_rounding_defaults_to_whole_price(): void
    {
        $result = $this->calculator->calculate(
            189,
            null,
            null,
            ProductPriceCalculator::INCREASE_PERCENT,
            7,
            ProductPriceCalculator::KEEP_OLD_PRICE,
        );

        $this->assertSame(202.0, $result['price']);
    }

    public function test_rounding_can_use_one_decimal_place(): void
    {
        $result = $this->calculator->calculate(
            189,
            null,
            null,
            ProductPriceCalculator::INCREASE_PERCENT,
            7,
            ProductPriceCalculator::KEEP_OLD_PRICE,
            1,
        );

        $this->assertSame(202.2, $result['price']);
    }
}
