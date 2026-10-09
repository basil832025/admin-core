<?php

namespace App\Reports\DataProviders;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductsCatalogReportProvider implements ReportDataProviderInterface
{
    public function resolve(array $params, array $context = []): array
    {
        $locale = app()->getLocale();
        $rows = [];

        foreach ($this->fetchProducts((int) ($params['category_id'] ?? 0)) as $root) {
            $products = $root->children->isNotEmpty() ? $root->children : collect([$root]);

            foreach ($products as $product) {
                $category = $product->mainCategory ?? $root->mainCategory
                    ?? $product->categories->first() ?? $root->categories->first();

                $rows[] = [
                    'sku' => (string) ($root->sku ?? ''),
                    'title' => $this->translatedTitle($product, $locale)
                        ?: $this->translatedTitle($root, $locale),
                    'size_volume' => $this->sizeVolume($product, $root, $locale),
                    'price' => (float) ($product->price ?? 0),
                    'category_name' => $category ? $this->translatedTitle($category, $locale) : '',
                ];
            }
        }

        return $rows;
    }

    protected function fetchProducts(int $categoryId): Collection
    {
        $relations = ['mainCategory', 'categories', 'unit',
            'productCharacteristicValues.characteristic', 'productCharacteristicValues.characteristicValue'];

        $query = Product::query()->whereNull('parent_id')
            ->where(fn (Builder $query) => $query->whereNull('is_imported')->orWhere('is_imported', false))
            ->with($relations)
            ->with(['children' => fn ($query) => $query
                ->where(fn (Builder $query) => $query->whereNull('is_imported')->orWhere('is_imported', false))
                ->with($relations)->orderBy('id')])
            ->orderBy('sort')->orderBy('id');

        if ($categoryId > 0) {
            $category = ProductCategory::query()->find($categoryId);
            if (! $category) {
                return collect();
            }

            $ids = array_values(array_unique(array_merge([$categoryId], $category->getDescendantIds())));
            // Filter by the primary category shown in the report, not additional memberships.
            $query->whereIn('category_id', $ids);
        }

        return $query->get();
    }

    private function translatedTitle(Product|ProductCategory $model, string $locale): string
    {
        foreach (array_unique([$locale, 'uk', 'ru', 'en']) as $language) {
            $title = trim((string) $model->getTranslation('title', $language, false));
            if ($title !== '') {
                return $title;
            }
        }

        return trim((string) ($model->short_name ?? $model->slug ?? ''));
    }

    private function sizeVolume(Product $product, Product $root, string $locale): string
    {
        $slugs = ['volume', 'obiem', 'obyem', 'ob-yem', 'size', 'rozmir', 'rozmir-pirogiv',
            'rozmiri-insi', 'vaga-grami', 'vaga-setiv', 'vaga'];

        foreach ([$product, $root] as $source) {
            foreach ($slugs as $slug) {
                foreach ($source->productCharacteristicValues as $row) {
                    if ($row->characteristic?->slug !== $slug) {
                        continue;
                    }

                    $value = trim((string) ($row->value_text ?? ''));
                    if ($value === '' && $row->value_number !== null) {
                        $value = (string) $row->value_number;
                    }
                    if ($value === '' && $row->characteristicValue) {
                        foreach (array_unique([$locale, 'uk', 'ru', 'en']) as $language) {
                            $value = trim((string) $row->characteristicValue->getTranslation('value', $language, false));
                            if ($value !== '') {
                                break;
                            }
                        }
                    }
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return ProductResource::formatPriceUnitLabel($product->unit ? $product : $root, $locale);
    }
}
