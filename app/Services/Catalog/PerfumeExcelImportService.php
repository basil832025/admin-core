<?php

namespace App\Services\Catalog;

use App\Models\Shop\Characteristic;
use App\Models\Shop\CharacteristicCategory;
use App\Models\Shop\CharacteristicValue;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Models\Shop\ProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class PerfumeExcelImportService
{
    private const MANAGED_CHARACTERISTICS = [
        'sevia-gender' => ['label' => 'Gender', 'column' => 7, 'type' => 'single', 'sort' => 10],
        'sevia-aroma-family' => ['label' => 'Aroma family', 'column' => 3, 'type' => 'single', 'sort' => 20],
        'sevia-segment' => ['label' => 'Segment', 'column' => 4, 'type' => 'single', 'sort' => 30],
        'sevia-concentration' => ['label' => 'Concentration', 'column' => 6, 'type' => 'single', 'sort' => 40],
        'sevia-top-notes' => ['label' => 'Top notes', 'column' => 9, 'type' => 'multi', 'sort' => 50],
        'sevia-middle-notes' => ['label' => 'Middle notes', 'column' => 10, 'type' => 'multi', 'sort' => 60],
        'sevia-base-notes' => ['label' => 'Base notes', 'column' => 11, 'type' => 'multi', 'sort' => 70],
    ];

    public function enabled(): bool
    {
        return config('catalog_import.product_excel_format') === 'sevia_perfumes';
    }

    public function preview(mixed $file): array
    {
        $path = $this->resolvePath($file);

        if ($path === null) {
            return ['rows' => [], 'errors' => ['Upload an Excel file first.']];
        }

        try {
            $rows = $this->readRows($path);
        } catch (Throwable $exception) {
            return ['rows' => [], 'errors' => [$exception->getMessage()]];
        }

        $result = [];

        foreach ($rows as $row) {
            $product = Product::query()->where('sku', $row['sku'])->first();
            $changes = $product ? $this->diffProduct($product, $row) : [];
            $errors = $this->validateRow($row);

            $result[] = [
                'row' => $row['row'],
                'sku' => $row['sku'],
                'name' => $row['name'],
                'brand' => $row['brand'],
                'price' => $row['price'],
                'exists' => (bool) $product,
                'changes' => $changes,
                'errors' => $errors,
                'can_import' => $errors === [],
            ];
        }

        return ['rows' => $result, 'errors' => []];
    }

    public function selectionOptions(mixed $file): array
    {
        $preview = $this->preview($file);
        $options = [];

        foreach ($preview['rows'] as $row) {
            $prefix = $row['exists'] ? 'update' : 'new';
            $suffix = $row['errors'] ? ' - has errors' : '';
            $options[(string) $row['row']] = "#{$row['row']} {$prefix}: {$row['sku']} {$row['name']} ({$row['brand']}){$suffix}";
        }

        return $options;
    }

    public function defaultSelectedRows(mixed $file): array
    {
        $preview = $this->preview($file);

        return collect($preview['rows'])
            ->filter(fn (array $row): bool => $row['can_import'])
            ->pluck('row')
            ->map(fn (int $row): string => (string) $row)
            ->all();
    }

    public function previewHtml(mixed $file): HtmlString
    {
        $preview = $this->preview($file);

        if ($preview['errors'] !== []) {
            return new HtmlString('<div class="text-sm text-danger-600">' . e(implode(' ', $preview['errors'])) . '</div>');
        }

        if ($preview['rows'] === []) {
            return new HtmlString('<div class="text-sm text-gray-500">No rows found.</div>');
        }

        $html = '<div class="overflow-x-auto"><table class="w-full text-xs"><thead><tr>'
            . '<th class="text-left p-2">Row</th><th class="text-left p-2">Status</th><th class="text-left p-2">SKU</th>'
            . '<th class="text-left p-2">Name</th><th class="text-left p-2">Brand</th><th class="text-left p-2">Price 1 ml</th>'
            . '<th class="text-left p-2">Changes</th></tr></thead><tbody>';

        foreach ($preview['rows'] as $row) {
            $status = $row['exists'] ? 'Existing' : 'New';
            $changes = $row['errors'] !== []
                ? implode('<br>', array_map('e', $row['errors']))
                : ($row['changes'] === [] ? 'No differences' : implode('<br>', array_map('e', $row['changes'])));

            $html .= '<tr class="border-t border-gray-200">'
                . '<td class="p-2">' . e((string) $row['row']) . '</td>'
                . '<td class="p-2">' . e($status) . '</td>'
                . '<td class="p-2">' . e($row['sku']) . '</td>'
                . '<td class="p-2">' . e($row['name']) . '</td>'
                . '<td class="p-2">' . e($row['brand']) . '</td>'
                . '<td class="p-2">' . e((string) $row['price']) . '</td>'
                . '<td class="p-2">' . $changes . '</td>'
                . '</tr>';
        }

        return new HtmlString($html . '</tbody></table></div>');
    }

    public function apply(mixed $file, array $selectedRows, ?string $imageDirectory = null, bool $overwriteImages = false): array
    {
        $path = $this->resolvePath($file);

        if ($path === null) {
            throw new \RuntimeException('Excel file was not uploaded.');
        }

        $selectedRows = array_map('intval', $selectedRows);
        $rows = array_filter(
            $this->readRows($path),
            fn (array $row): bool => in_array((int) $row['row'], $selectedRows, true)
        );

        $imageIndex = $this->imageIndex($imageDirectory);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'images_added' => 0, 'images_skipped' => 0, 'images_missing' => 0];

        DB::transaction(function () use ($rows, $imageIndex, $overwriteImages, &$stats): void {
            $characteristics = $this->ensureCharacteristics();

            foreach ($rows as $index => $row) {
                if ($this->validateRow($row) !== []) {
                    $stats['skipped']++;
                    continue;
                }

                $brandCategory = $this->ensureCategory($row['brand'], null, null, $index + 1);
                $this->attachCharacteristicsToCategory($brandCategory->id, array_values($characteristics));

                $product = Product::query()->where('sku', $row['sku'])->first();
                $isNew = ! $product;
                $sort = $product?->sort ?? $this->nextProductSort($brandCategory->id);

                $product = Product::query()->updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'title' => $this->translations($row['name']),
                        'slug' => $product?->slug ?: $this->uniqueProductSlug($row['brand'] . ' ' . $row['name'], $row['sku']),
                        'description' => $this->translations($row['description']),
                        'price' => $row['price'],
                        'unit_id' => $this->productUnitId('ml'),
                        'price_unit_quantity' => 1,
                        'old_price' => null,
                        'category_id' => $brandCategory->id,
                        'in_stock' => true,
                        'quantity' => 1000,
                        'is_hit' => $row['bestseller'],
                        'is_home' => true,
                        'sort' => $sort,
                    ]
                );

                $categoryPivotValues = Schema::hasColumn('bs_product_product_category', 'sort_order')
                    ? ['sort_order' => $sort]
                    : [];

                DB::table('bs_product_product_category')->updateOrInsert(
                    [
                        'product_id' => $product->id,
                        'product_category_id' => $brandCategory->id,
                    ],
                    $categoryPivotValues
                );

                $this->syncCharacteristics($product, $row, $characteristics);
                $this->syncMainImage($product, $row['sku'], $imageIndex, $overwriteImages, $stats);
                $stats[$isNew ? 'created' : 'updated']++;
            }
        });

        return $stats;
    }

    private function imageIndex(?string $directory): array
    {
        $directory = $this->clean((string) $directory);

        if ($directory === '') {
            return [];
        }

        $directory = trim($directory, " \t\n\r\0\x0B\"'");

        if (! is_dir($directory)) {
            throw new \RuntimeException("Image directory does not exist: {$directory}");
        }

        $index = [];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        foreach (File::files($directory) as $file) {
            $extension = mb_strtolower($file->getExtension());

            if (! in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            foreach ($this->imageKeys($file->getFilenameWithoutExtension()) as $key) {
                $index[$key] ??= $file->getRealPath();
            }
        }

        return $index;
    }

    private function syncMainImage(Product $product, string $sku, array $imageIndex, bool $overwriteImages, array &$stats): void
    {
        if ($imageIndex === []) {
            return;
        }

        if (! $overwriteImages && filled($product->main_image)) {
            $stats['images_skipped']++;
            return;
        }

        $sourcePath = null;

        foreach ($this->imageKeys($sku) as $key) {
            if (isset($imageIndex[$key])) {
                $sourcePath = $imageIndex[$key];
                break;
            }
        }

        if ($sourcePath === null) {
            $stats['images_missing']++;
            return;
        }

        $extension = mb_strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $targetPath = Product::productStorageDirectory('main') . '/' . $this->imageFileName($sku) . '.' . $extension;
        $targetFullPath = Storage::disk('public')->path($targetPath);

        File::ensureDirectoryExists(dirname($targetFullPath));

        if (! File::copy($sourcePath, $targetFullPath)) {
            throw new \RuntimeException("Failed to copy image for SKU {$sku}.");
        }

        $product->forceFill(['main_image' => $targetPath])->save();
        $stats['images_added']++;
    }

    private function imageKeys(string $value): array
    {
        $value = $this->clean($value);

        if ($value === '') {
            return [];
        }

        $keys = [$this->normalizeImageKey($value)];

        if (ctype_digit($value)) {
            $keys[] = ltrim($value, '0') ?: '0';
            $keys[] = str_pad(ltrim($value, '0') ?: '0', 3, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique(array_filter($keys)));
    }

    private function normalizeImageKey(string $value): string
    {
        return mb_strtolower($this->clean($value));
    }

    private function imageFileName(string $sku): string
    {
        return Str::slug($sku) ?: preg_replace('/[^A-Za-z0-9_-]+/', '-', $sku) ?: 'product';
    }

    private function readRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $index => $cells) {
            if ($index === 0) {
                continue;
            }

            if (! collect($cells)->filter(fn ($value): bool => trim((string) $value) !== '')->count()) {
                continue;
            }

            $rows[] = [
                'row' => $index + 1,
                'sku' => $this->clean($cells[0] ?? ''),
                'name' => $this->clean($cells[1] ?? ''),
                'brand' => $this->clean($cells[2] ?? ''),
                'aroma' => $this->clean($cells[3] ?? ''),
                'segment' => $this->clean($cells[4] ?? ''),
                'bestseller' => (bool) ((int) ($cells[5] ?? 0)),
                'concentration' => $this->clean($cells[6] ?? ''),
                'gender' => $this->clean($cells[7] ?? ''),
                'price' => (float) str_replace(',', '.', (string) ($cells[8] ?? 0)),
                'top_notes' => $this->splitValues($cells[9] ?? ''),
                'middle_notes' => $this->splitValues($cells[10] ?? ''),
                'base_notes' => $this->splitValues($cells[11] ?? ''),
                'description' => $this->clean($cells[12] ?? ''),
            ];
        }

        return $rows;
    }

    private function validateRow(array $row): array
    {
        $errors = [];

        foreach (['sku', 'name', 'brand'] as $field) {
            if ($row[$field] === '') {
                $errors[] = "{$field} is empty";
            }
        }

        if ($row['price'] <= 0) {
            $errors[] = 'price is empty';
        }

        return $errors;
    }

    private function diffProduct(Product $product, array $row): array
    {
        $changes = [];
        $title = $this->translationValue($product->title);
        $description = $this->translationValue($product->description);
        $brand = $product->mainCategory ? $this->translationValue($product->mainCategory->title) : '';

        foreach ([
            'name' => [$title, $row['name']],
            'brand' => [$brand, $row['brand']],
            'price' => [(string) (float) $product->price, (string) (float) $row['price']],
            'description' => [$description, $row['description']],
        ] as $field => [$old, $new]) {
            if ($this->normalize($old) !== $this->normalize($new)) {
                $changes[] = "{$field}: {$old} -> {$new}";
            }
        }

        return $changes;
    }

    private function ensureCharacteristics(): array
    {
        $category = CharacteristicCategory::query()->firstOrCreate(
            ['slug' => 'sevia-perfumes'],
            [
                'name' => $this->translations('Perfumes'),
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        $result = [];

        foreach (self::MANAGED_CHARACTERISTICS as $slug => $definition) {
            $result[$slug] = Characteristic::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category->id,
                    'name' => $this->translations($definition['label']),
                    'pricing_type' => 0,
                    'sort_order' => $definition['sort'],
                    'expand_values' => in_array($definition['type'], ['multi'], true),
                    'is_required' => false,
                    'field_type' => $definition['type'] === 'multi' ? 'multiselect' : 'select',
                    'is_active' => true,
                    'is_main_tab' => true,
                ]
            );
        }

        return $result;
    }

    private function syncCharacteristics(Product $product, array $row, array $characteristics): void
    {
        $managedIds = collect($characteristics)->pluck('id')->all();

        DB::table('bs_product_characteristic_value')
            ->where('product_id', $product->id)
            ->whereIn('characteristic_id', $managedIds)
            ->delete();

        $valuesBySlug = [
            'sevia-gender' => [$row['gender']],
            'sevia-aroma-family' => [$row['aroma']],
            'sevia-segment' => [$row['segment']],
            'sevia-concentration' => [$row['concentration']],
            'sevia-top-notes' => $row['top_notes'],
            'sevia-middle-notes' => $row['middle_notes'],
            'sevia-base-notes' => $row['base_notes'],
        ];

        foreach ($valuesBySlug as $slug => $values) {
            $characteristic = $characteristics[$slug];

            foreach ($values as $sort => $value) {
                $value = $this->clean($value);

                if ($value === '') {
                    continue;
                }

                $characteristicValue = $this->ensureCharacteristicValue($characteristic, $value, $sort + 1);

                DB::table('bs_product_characteristic_value')->updateOrInsert(
                    [
                        'product_id' => $product->id,
                        'characteristic_id' => $characteristic->id,
                        'characteristic_value_id' => $characteristicValue->id,
                    ],
                    [
                        'value_text' => null,
                        'value_number' => null,
                        'value_datetime' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    private function ensureCharacteristicValue(Characteristic $characteristic, string $value, int $sort): CharacteristicValue
    {
        $characteristic->loadMissing('values');

        $normalized = $this->normalize($value);
        $existing = $characteristic->values->first(
            fn (CharacteristicValue $item): bool => $this->normalize($this->translationValue($item->value)) === $normalized
        );

        if ($existing) {
            return $existing;
        }

        $created = CharacteristicValue::query()->create([
            'characteristic_id' => $characteristic->id,
            'value' => $this->translations($this->titleCase($value)),
            'sort_order' => $sort,
            'is_active' => true,
        ]);

        $characteristic->unsetRelation('values');
        $characteristic->load('values');

        return $created;
    }

    private function ensureCategory(string $label, ?int $parentId, ?string $preferredSlug, int $sort): ProductCategory
    {
        $normalized = $this->normalize($label);
        $existing = ProductCategory::query()
            ->get()
            ->first(fn (ProductCategory $category): bool => $this->normalize($this->translationValue($category->title)) === $normalized);

        if ($existing) {
            if ($parentId !== null && (int) $existing->parent_id !== $parentId) {
                $existing->parent_id = $parentId;
                $existing->save();
            }

            return $existing;
        }

        return ProductCategory::query()->create([
            'title' => $this->translations($label),
            'slug' => $preferredSlug ?: $this->uniqueCategorySlug($label),
            'parent_id' => $parentId ?? -1,
            'order' => $sort,
            'is_visible' => true,
        ]);
    }

    private function attachCharacteristicsToCategory(int $categoryId, array $characteristics): void
    {
        foreach ($characteristics as $characteristic) {
            DB::table('bs_category_characteristic')->updateOrInsert(
                [
                    'category_id' => $categoryId,
                    'characteristic_id' => $characteristic->id,
                ],
                [
                    'affects_price' => false,
                    'is_required' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function nextProductSort(int $categoryId): int
    {
        return ((int) Product::query()->where('category_id', $categoryId)->max('sort')) + 1;
    }

    private function productUnitId(string $code): ?int
    {
        return ProductUnit::query()->where('code', $code)->value('id')
            ?: ProductUnit::query()->where('is_default', true)->value('id');
    }

    private function resolvePath(mixed $file): ?string
    {
        if (is_array($file)) {
            $file = reset($file) ?: null;
        }

        if ($file instanceof TemporaryUploadedFile) {
            return $file->getRealPath();
        }

        if (is_string($file) && $file !== '') {
            return Storage::disk('local')->exists($file)
                ? Storage::disk('local')->path($file)
                : (is_file($file) ? $file : null);
        }

        return null;
    }

    private function splitValues(mixed $value): array
    {
        $value = $this->clean($value);

        if ($value === '') {
            return [];
        }

        return collect(preg_split('/[,;.]+/u', $value) ?: [])
            ->map(fn (string $item): string => $this->clean($item))
            ->filter()
            ->unique(fn (string $item): string => $this->normalize($item))
            ->values()
            ->all();
    }

    private function clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    private function normalize(string $value): string
    {
        return mb_strtolower($this->clean($value));
    }

    private function titleCase(string $value): string
    {
        $value = $this->clean($value);

        return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }

    private function translations(string $value): array
    {
        return ['uk' => $value, 'ru' => $value, 'en' => $value];
    }

    private function translationValue(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['uk'] ?? $value['ru'] ?? $value['en'] ?? reset($value) ?: '');
        }

        $decoded = json_decode((string) $value, true);

        if (is_array($decoded)) {
            return (string) ($decoded['uk'] ?? $decoded['ru'] ?? $decoded['en'] ?? reset($decoded) ?: '');
        }

        return (string) $value;
    }

    private function uniqueCategorySlug(string $label): string
    {
        return $this->uniqueSlug(ProductCategory::query(), Str::slug($label) ?: 'category');
    }

    private function uniqueProductSlug(string $label, string $sku): string
    {
        return $this->uniqueSlug(Product::query(), Str::slug($label) ?: 'product-' . $sku);
    }

    private function uniqueSlug($query, string $base): string
    {
        $slug = $base;
        $i = 2;

        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
