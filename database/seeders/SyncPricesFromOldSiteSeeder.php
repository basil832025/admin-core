<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

class SyncPricesFromOldSiteSeeder extends Seeder
{
    /**
     * Боевые категории (исключаем src-* импортные категории).
     *
     * @var array<int, string>
     */
    private array $productionCategorySlugs = [
        'surnue-pirogi',
        'myasnue-pirogi',
        'postnue-pirogi',
        'sladkie-pirogi',
        'tradicionnue',
        'pies',
        'kombo-nabor',
        'napitki',
        'sousu-k-pirogam',
        'tasting-sets',
        'desertu',
    ];

    public function run(): void
    {
        $remote = $this->createRemotePdo();
        $remotePriceMap = $this->loadRemotePriceMap($remote);

        if ($remotePriceMap === []) {
            $this->lineWarn('Не найдены цены в старой базе (catalog_params).');

            return;
        }

        $categoryIds = DB::table('bs_product_categories')
            ->whereIn('slug', $this->productionCategorySlugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($categoryIds === []) {
            $this->lineWarn('Не найдены боевые категории в bs_product_categories.');

            return;
        }

        $localProducts = DB::table('bs_products')
            ->select('id', 'code2', 'price', 'slug', 'parent_id')
            ->whereIn('category_id', $categoryIds)
            ->whereNotNull('code2')
            ->where('code2', '<>', '')
            ->get();

        $checked = 0;
        $matched = 0;
        $updated = 0;
        $unchanged = 0;
        $notMatched = 0;
        $reportRows = [];

        foreach ($localProducts as $product) {
            $checked++;
            $code2 = trim((string) $product->code2);

            if (! isset($remotePriceMap[$code2])) {
                $notMatched++;
                continue;
            }

            $matched++;

            $localPrice = $this->toPriceString($product->price);
            $remotePrice = $remotePriceMap[$code2];

            if ($localPrice === $remotePrice) {
                $unchanged++;
                continue;
            }

            DB::table('bs_products')
                ->where('id', (int) $product->id)
                ->update([
                    'price' => $remotePrice,
                    'updated_at' => Carbon::now(),
                ]);

            $updated++;

            $reportRows[] = [
                'id' => (int) $product->id,
                'parent_id' => $product->parent_id !== null ? (int) $product->parent_id : null,
                'slug' => (string) $product->slug,
                'code2' => $code2,
                'local_price_old' => $localPrice,
                'remote_price_new' => $remotePrice,
                'delta' => number_format((float) $remotePrice - (float) $localPrice, 2, '.', ''),
            ];
        }

        $this->writeReport($reportRows);

        $this->lineInfo("Проверено локальных позиций: {$checked}");
        $this->lineInfo("Совпало по code2: {$matched}");
        $this->lineInfo("Обновлено цен: {$updated}");
        $this->lineInfo("Без изменений: {$unchanged}");
        $this->lineInfo("Не найдено в старой базе: {$notMatched}");
    }

    private function createRemotePdo(): PDO
    {
        $host = trim((string) env('OLD_SITE_DB_HOST', ''));
        $name = trim((string) env('OLD_SITE_DB_NAME', ''));
        $user = trim((string) env('OLD_SITE_DB_USER', ''));
        $pass = (string) env('OLD_SITE_DB_PASSWORD', '');

        if ($host === '' || $name === '' || $user === '' || $pass === '') {
            throw new \RuntimeException(
                'Заполните OLD_SITE_DB_HOST, OLD_SITE_DB_NAME, OLD_SITE_DB_USER, OLD_SITE_DB_PASSWORD в .env',
            );
        }

        try {
            return new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('Не удалось подключиться к старой БД: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, string>
     */
    private function loadRemotePriceMap(PDO $remote): array
    {
        $sql = <<<SQL
SELECT coded2, price
FROM catalog_params
WHERE active = 1
  AND coded2 IS NOT NULL
  AND coded2 <> ''
SQL;

        $rows = $remote->query($sql)->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            $code2 = trim((string) ($row['coded2'] ?? ''));
            if ($code2 === '') {
                continue;
            }

            $map[$code2] = $this->toPriceString($row['price'] ?? null);
        }

        return $map;
    }

    private function toPriceString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param array<int, array<string, int|string|null>> $rows
     */
    private function writeReport(array $rows): void
    {
        $path = storage_path('app/price-sync-report.csv');
        $fp = fopen($path, 'wb');
        if ($fp === false) {
            $this->lineWarn('Не удалось записать отчет price-sync-report.csv');

            return;
        }

        fputcsv($fp, ['id', 'parent_id', 'slug', 'code2', 'local_price_old', 'remote_price_new', 'delta']);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $this->lineInfo('Отчет изменений: ' . $path);
    }

    private function lineInfo(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }

    private function lineWarn(string $message): void
    {
        if ($this->command) {
            $this->command->warn($message);
        }
    }
}
