<?php

namespace Tests\Unit;

use App\Models\Shop\ProductCategory;
use App\Support\ProductCategoryIcons;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Database\Seeders\ProductCategoryIconsSeeder;
use Tests\TestCase;

class ProductCategoryIconsTest extends TestCase
{
    public function test_only_whitelisted_identifiers_are_used(): void
    {
        $this->assertSame('heroicon-o-cake', ProductCategoryIcons::valid('heroicon-o-cake'));
        $this->assertSame('custom:meat', ProductCategoryIcons::valid('custom:meat'));
        $this->assertSame(ProductCategoryIcons::DEFAULT, ProductCategoryIcons::valid('<svg onload=alert(1)>'));
        $this->assertSame(ProductCategoryIcons::DEFAULT, ProductCategoryIcons::valid('custom:../meat'));
        $this->assertSame(ProductCategoryIcons::DEFAULT, ProductCategoryIcons::valid(null));
        $this->assertArrayHasKey('custom:pies', ProductCategoryIcons::customOptions());
        $this->assertStringContainsString('currentColor', (string) ProductCategoryIcons::customSvg('custom:pies'));
    }

    public function test_model_does_not_store_arbitrary_svg_or_component_names(): void
    {
        $category = new ProductCategory;
        $category->icon = '<svg>unsafe</svg>';
        $this->assertSame(ProductCategoryIcons::DEFAULT, $category->icon);
        $this->assertSame(ProductCategoryIcons::DEFAULT, $category->catalog_icon);
    }

    public function test_icon_color_is_restricted_to_hex(): void
    {
        $category = new ProductCategory;
        $category->icon_color = '#ef4444';
        $this->assertSame('#EF4444', $category->icon_color);
        $this->assertSame('#EF4444', $category->catalog_icon_color);

        $category->icon_color = 'red; background:url(javascript:1)';
        $this->assertNull($category->icon_color);
        $this->assertNull($category->catalog_icon_color);
    }

    public function test_custom_icon_is_rendered_inline_and_not_as_an_image(): void
    {
        $html = Blade::render('<x-product-category-icon icon="custom:meat" color="#EF4444" />');
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('currentColor', $html);
        $this->assertStringContainsString('color:#EF4444', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_uploaded_svg_is_sanitized_added_and_recolorable(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'category-svg-');
        file_put_contents($source, '<svg viewBox="0 0 24 24"><path fill="#ef4444" d="M2 2h20v20H2z"/></svg>');
        $identifier = ProductCategoryIcons::installUploaded($source, 'My Custom Icon.svg');
        $name = substr($identifier, 7);
        $stored = public_path("images/category-icons/{$name}.svg");
        try {
            $this->assertStringStartsWith('custom:my-custom-icon', $identifier);
            $this->assertFileExists($stored);
            $this->assertStringContainsString('fill="currentColor"', file_get_contents($stored));
            $this->assertSame($identifier, ProductCategoryIcons::valid($identifier));
        } finally {
            @unlink($source);
            @unlink($stored);
        }
    }

    public function test_dangerous_uploaded_svg_is_rejected(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'category-svg-');
        file_put_contents($source, '<svg viewBox="0 0 24 24"><script>alert(1)</script></svg>');
        try {
            $this->expectException(ValidationException::class);
            ProductCategoryIcons::installUploaded($source, 'unsafe.svg');
        } finally {
            @unlink($source);
        }
    }

    public function test_svg_code_can_be_previewed_and_added_to_collection(): void
    {
        $code = '<svg viewBox="0 0 24 24"><circle fill="#00ff00" cx="12" cy="12" r="8"/></svg>';
        $preview = ProductCategoryIcons::preview($code);
        $this->assertNotNull($preview);
        $this->assertStringContainsString('fill="currentColor"', (string) $preview);

        $identifier = ProductCategoryIcons::installCode($code, 'Inserted SVG Test');
        $stored = public_path('images/category-icons/' . substr($identifier, 7) . '.svg');
        try {
            $this->assertStringStartsWith('custom:inserted-svg-test', $identifier);
            $this->assertFileExists($stored);
        } finally {
            @unlink($stored);
        }
    }

    public function test_category_icon_seeder_contains_only_valid_icons_and_excludes_test_folder(): void
    {
        $this->assertArrayNotHasKey('test', ProductCategoryIconsSeeder::ASSIGNMENTS);
        foreach (ProductCategoryIconsSeeder::ASSIGNMENTS as $slug => $settings) {
            $this->assertNotSame('test', $slug);
            $this->assertSame($settings['icon'], ProductCategoryIcons::valid($settings['icon']));
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $settings['color']);
        }
    }
}
