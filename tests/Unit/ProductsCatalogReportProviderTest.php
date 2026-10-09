<?php

namespace Tests\Unit;

use App\Models\Shop\Characteristic;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Models\Shop\ProductCharacteristicValue;
use App\Models\Shop\ProductUnit;
use App\Reports\DataProviders\ProductsCatalogReportProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ProductsCatalogReportProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application;
        $app->instance('config', new Repository(['app' => ['locale' => 'uk', 'fallback_locale' => 'en']]));
    }

    public function test_variants_have_separate_prices_and_inherit_parent_category(): void
    {
        $root = $this->product(1, 'ROOT', 100);
        $variant = $this->product(2, 'VARIANT', 225);
        $variant->setRawAttributes(array_merge($variant->getAttributes(), ['title' => '{}', 'parent_id' => 1]));
        $size = new ProductCharacteristicValue;
        $size->setRawAttributes(['value_text' => '50 мл']);
        $size->setRelation('characteristic', new Characteristic(['slug' => 'volume']));
        $size->setRelation('characteristicValue', null);
        $variant->setRelation('productCharacteristicValues', collect([$size]));
        $root->setRelation('children', collect([$variant]));

        $rows = $this->provider(collect([$root]))->resolve([]);

        $this->assertCount(1, $rows);
        $this->assertSame(['sku' => 'ROOT', 'title' => 'Товар', 'size_volume' => '50 мл',
            'price' => 225.0, 'category_name' => 'Категорія'], $rows[0]);
    }

    public function test_simple_product_uses_catalog_price_quantity_and_unit(): void
    {
        $root = $this->product(1, '00123', 19.5);
        $root->setRawAttributes(array_merge($root->getAttributes(), ['price_unit_quantity' => 1]));
        $unit = new ProductUnit;
        $unit->setRawAttributes(['short_name' => '{"uk":"мл"}']);
        $root->setRelation('unit', $unit);

        $rows = $this->provider(collect([$root]))->resolve([]);

        $this->assertSame('00123', $rows[0]['sku']);
        $this->assertSame('1 мл', $rows[0]['size_volume']);
        $this->assertSame(19.5, $rows[0]['price']);
    }

    private function product(int $id, string $sku, float $price): Product
    {
        $product = new Product;
        $product->setRawAttributes(['id' => $id, 'sku' => $sku, 'price' => $price,
            'title' => '{"uk":"Товар"}']);
        $category = new ProductCategory;
        $category->setRawAttributes(['title' => '{"uk":"Категорія"}']);
        $product->setRelation('mainCategory', $id === 1 ? $category : null);
        foreach (['children', 'categories', 'productCharacteristicValues'] as $relation) {
            $product->setRelation($relation, collect());
        }
        $product->setRelation('unit', null);

        return $product;
    }

    private function provider(Collection $products): ProductsCatalogReportProvider
    {
        return new class($products) extends ProductsCatalogReportProvider
        {
            public function __construct(private Collection $products) {}

            protected function fetchProducts(int $categoryId): Collection
            {
                return $this->products;
            }
        };
    }
}
