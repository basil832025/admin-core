<?php

namespace Tests\Unit;

use App\Services\CatalogVariantDiscount;
use PHPUnit\Framework\TestCase;

class CatalogVariantDiscountTest extends TestCase
{
    public function test_each_variant_uses_its_own_price(): void
    {
        $service = new CatalogVariantDiscount;
        foreach ([548 => 438.0, 693 => 554.0, 839 => 671.0] as $price => $expected) {
            $values = $service->calculate($price, null, null, 20);
            $this->assertSame($expected, $values['price']);
            $this->assertSame((float) $price, $values['old_price']);
            $this->assertSame(20.0, $values['manual_discount_percent']);
        }
    }

    public function test_existing_discounts_are_preserved(): void
    {
        $service = new CatalogVariantDiscount;
        $this->assertNull($service->calculate(400, 500, null, 20));
        $this->assertNull($service->calculate(400, null, 10, 20));
        $this->assertNull($service->calculate(0, null, null, 20));
        $this->assertNull($service->calculate(400, null, null, 0));
    }

    public function test_repeated_application_does_not_stack_discount(): void
    {
        $service = new CatalogVariantDiscount;
        $values = $service->calculate(500, null, null, 20);
        $this->assertNull($service->calculate($values['price'], $values['old_price'], $values['manual_discount_percent'], 20));
    }

    public function test_following_variant_changes_discount_using_its_original_price(): void
    {
        $service = new CatalogVariantDiscount;

        $this->assertSame(21.0, $service->discountPercent(498, 631, 21));
        $this->assertSame([
            'old_price' => 631.0,
            'manual_discount_percent' => 15.0,
            'price' => 536.0,
        ], $service->valuesForFollowingVariant(498, 631, 15));
    }

    public function test_following_variant_removes_discount_and_restores_original_price(): void
    {
        $service = new CatalogVariantDiscount;

        $this->assertSame([
            'old_price' => null,
            'manual_discount_percent' => null,
            'price' => 631.0,
        ], $service->valuesForFollowingVariant(498, 631, null));
    }

    public function test_calculated_discount_can_be_matched_when_manual_value_is_missing(): void
    {
        $service = new CatalogVariantDiscount;

        $this->assertSame(21.0, $service->discountPercent(498, 631, null));
        $this->assertNull($service->discountPercent(631, null, null));
    }
}
