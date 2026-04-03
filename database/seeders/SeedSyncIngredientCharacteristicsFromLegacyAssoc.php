<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedSyncIngredientCharacteristicsFromLegacyAssoc extends Seeder
{
    private array $ingredientCharacteristicIds = [4, 5, 6, 7, 8, 19, 20, 25];

    private array $synonyms = [
        'сир сулугуні' => 'сулугуні',
        'сир моцарелла' => 'моцарела',
        'сир моцарела' => 'моцарела',
        'сир рікотта' => 'рікотта',
        'сир дорблю' => 'дорблю',
        'кетчуп власного приготування' => 'домашній кетчуп',
        'цибуля ріпчаста' => 'цибуля',
        'перець халапеньо' => 'халапеньо',
        'гриби печериці' => 'гриби печериці',
    ];

    public function run(): void
    {
        DB::statement('SET SESSION group_concat_max_len = 20000');

        $valueLookup = $this->buildValueLookup();

        $parents = DB::table('bs_products as p')
            ->whereNull('p.parent_id')
            ->where(function ($query): void {
                $query->whereNull('p.is_imported')->orWhere('p.is_imported', 0);
            })
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('product_consist_assoc as a')
                    ->whereColumn('a.product_id', 'p.id');
            })
            ->select(['p.id', 'p.slug'])
            ->orderBy('p.id')
            ->get();

        $processed = 0;
        $updatedProducts = 0;
        $createdValues = 0;
        $insertedPivots = 0;

        DB::beginTransaction();

        try {
            foreach ($parents as $parent) {
                $processed++;
                $productId = (int) $parent->id;

                $ingredientTitles = DB::table('product_consist_assoc as a')
                    ->join('catalog_consist_info as ci', function ($join): void {
                        $join->on('ci.record_id', '=', 'a.consist_id')
                            ->where('ci.lang', '=', '2');
                    })
                    ->where('a.product_id', $productId)
                    ->orderBy('a.consist_id')
                    ->pluck('ci.title')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter(fn ($v) => $v !== '')
                    ->values()
                    ->all();

                if (empty($ingredientTitles)) {
                    continue;
                }

                $targetPairs = [];

                foreach ($ingredientTitles as $title) {
                    $resolved = $this->resolveOrCreateValue($title, $valueLookup, $createdValues);
                    if ($resolved === null) {
                        continue;
                    }

                    $pairKey = $resolved['characteristic_id'] . ':' . $resolved['value_id'];
                    $targetPairs[$pairKey] = $resolved;
                }

                if (empty($targetPairs)) {
                    continue;
                }

                DB::table('bs_product_characteristic_value')
                    ->where('product_id', $productId)
                    ->whereIn('characteristic_id', $this->ingredientCharacteristicIds)
                    ->delete();

                foreach ($targetPairs as $pair) {
                    DB::table('bs_product_characteristic_value')->insert([
                        'product_id' => $productId,
                        'characteristic_id' => $pair['characteristic_id'],
                        'characteristic_value_id' => $pair['value_id'],
                        'value_text' => null,
                        'value_number' => null,
                        'value_datetime' => null,
                        'price_modifier' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $insertedPivots++;
                }

                $updatedProducts++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info(
            "Ingredient characteristic sync done. Processed: {$processed}, Updated products: {$updatedProducts}, Created values: {$createdValues}, Inserted pivots: {$insertedPivots}"
        );
    }

    private function buildValueLookup(): array
    {
        $lookup = [];

        $rows = DB::table('bs_characteristic_values as cv')
            ->join('bs_characteristics as c', 'c.id', '=', 'cv.characteristic_id')
            ->whereIn('cv.characteristic_id', $this->ingredientCharacteristicIds)
            ->select(['cv.id', 'cv.characteristic_id', 'cv.value', 'c.slug'])
            ->get();

        foreach ($rows as $row) {
            $texts = $this->extractLocalizedTexts((string) $row->value);
            foreach ($texts as $text) {
                $key = $this->normalizeText($text);
                if ($key === '') {
                    continue;
                }

                if (! isset($lookup[$key])) {
                    $lookup[$key] = [
                        'value_id' => (int) $row->id,
                        'characteristic_id' => (int) $row->characteristic_id,
                    ];
                }
            }
        }

        return $lookup;
    }

    private function resolveOrCreateValue(string $rawTitle, array &$valueLookup, int &$createdValues): ?array
    {
        $key = $this->normalizeText($rawTitle);
        if ($key === '') {
            return null;
        }

        if (isset($this->synonyms[$key])) {
            $key = $this->normalizeText($this->synonyms[$key]);
        }

        if (isset($valueLookup[$key])) {
            return $valueLookup[$key];
        }

        $characteristicId = $this->detectCharacteristicId($rawTitle);
        if ($characteristicId === null) {
            $characteristicId = 8;
        }

        $nextSort = (int) (DB::table('bs_characteristic_values')
            ->where('characteristic_id', $characteristicId)
            ->max('sort_order') ?? 0) + 1;

        $jsonValue = json_encode([
            'uk' => $rawTitle,
            'ru' => $rawTitle,
            'en' => $rawTitle,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $valueId = (int) DB::table('bs_characteristic_values')->insertGetId([
            'characteristic_id' => $characteristicId,
            'value' => $jsonValue,
            'sort_order' => $nextSort,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolved = [
            'value_id' => $valueId,
            'characteristic_id' => $characteristicId,
        ];

        $valueLookup[$key] = $resolved;
        $createdValues++;

        return $resolved;
    }

    private function detectCharacteristicId(string $title): ?int
    {
        $n = $this->normalizeText($title);

        $groups = [
            4 => ['телятин', 'кур', 'індич', 'свинин', 'ялович', 'баранин', 'ягня', 'мяс', 'м\'яс', 'м’яс', 'фарш', 'шинка', 'бекон', 'ковбас', 'салям', 'пепероні'],
            5 => ['сьомг', 'лосос', 'кревет', 'кальмар', 'тун', 'міді', 'морепроду', 'риба', 'оселед', 'анчоус'],
            6 => ['сир', 'сулугун', 'моцарел', 'рікотт', 'дорблю', 'чеддер', 'пармезан', 'гауда', 'бринз', 'фета', 'маскарпон'],
            7 => ['соус', 'кетчуп', 'майонез', 'гірчиц', 'гуакамол', 'альфредо', 'барбекю', 'песто', 'томатн'],
            19 => ['вишн', 'яблук', 'груш', 'полуниц', 'ягод', 'малина', 'чорниц', 'смородин', 'абрикос', 'слив', 'чорнослив', 'ізюм', 'родзин', 'цукат', 'клубник', 'фрукт', 'кокос', 'банан', 'лимон', 'апельсин', 'мандарин'],
            20 => ['тісто', 'борошн', 'молоко', 'яйц', 'цукор', 'мед', 'кориц', 'мак', 'ваніл', 'дріждж', 'дрожж', 'спец', 'олія', 'масло', 'вершк', 'крем', 'праліне', 'горіх', 'мигдал', 'миндал', 'чабрец', 'орегано', 'прованськ', 'кунжут'],
            25 => ['зелень', 'базил', 'петруш', 'кріп', 'укроп', 'шпинат', 'рукол', 'кінза', 'м\'ята', 'м’ята', 'зелена цибуля'],
            8 => ['томат', 'помідор', 'перець', 'халапень', 'цибул', 'картопл', 'гриб', 'печериц', 'баклаж', 'кабач', 'буряк', 'буряков', 'капуст', 'моркв', 'огір', 'олив', 'маслин', 'часник', 'селера', 'мангольд', 'листя', 'шампінь', 'авокад'],
        ];

        foreach ($groups as $characteristicId => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($n, $keyword)) {
                    return $characteristicId;
                }
            }
        }

        return null;
    }

    private function extractLocalizedTexts(string $valueJson): array
    {
        $decoded = json_decode($valueJson, true);

        if (is_array($decoded)) {
            $texts = [];
            foreach (['uk', 'ru', 'en'] as $key) {
                if (! empty($decoded[$key])) {
                    $texts[] = (string) $decoded[$key];
                }
            }

            if (empty($texts)) {
                foreach ($decoded as $v) {
                    if (is_string($v) && trim($v) !== '') {
                        $texts[] = $v;
                    }
                }
            }

            return $texts;
        }

        return [trim($valueJson)];
    }

    private function normalizeText(string $text): string
    {
        $t = mb_strtolower(trim($text));
        $t = str_replace(['`', '’', '“', '”'], ["'", "'", '"', '"'], $t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        return trim($t);
    }
}
