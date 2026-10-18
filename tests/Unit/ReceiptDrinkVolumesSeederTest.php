<?php

namespace Tests\Unit;

use Database\Seeders\ReceiptDrinkVolumesSeeder;
use PHPUnit\Framework\TestCase;

class ReceiptDrinkVolumesSeederTest extends TestCase
{
    public function test_existing_receipt_queries_gain_volumes_without_replacing_their_layout_or_other_fields(): void
    {
        $paths = [
            'database/seeders/LogisticReceiptExtendedTemplateSeeder.php',
            'database/migrations/2026_04_13_180000_update_kitchen_template_add_calc_components.php',
        ];
        $seeder = new ReceiptDrinkVolumesSeeder;

        foreach ($paths as $path) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
            preg_match("/<<<'SQL'\\R(.*?)\\RSQL;/s", $source, $match);
            $this->assertNotEmpty($match[1] ?? '', $path);
            $query = $seeder->updateQuery($match[1]);

            $this->assertStringStartsWith('SELECT', $query);
            $this->assertStringContainsString("JSON_EXTRACT(oi.meta, '$.volume')", $query);
            $this->assertStringContainsString('cp.price_unit_quantity', $query);
            $this->assertStringContainsString('p.price_unit_quantity', $query);
            $this->assertStringContainsString('AS calc_components', $query);
            $this->assertStringContainsString('AS size_label', $query);
            $this->assertStringContainsString('ORDER BY pci.id', $query);
            $this->assertSame($query, $seeder->updateQuery($query));
        }
    }

    public function test_unknown_queries_are_rejected_instead_of_silently_marked_as_fixed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ReceiptDrinkVolumesSeeder)->updateQuery('SELECT 1');
    }
}
