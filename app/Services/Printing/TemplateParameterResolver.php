<?php

namespace App\Services\Printing;

use App\Models\PrintOperationProfile;
use App\Models\PrintTemplate;
use Illuminate\Support\Arr;

class TemplateParameterResolver
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array{params: array<string, mixed>, missing_required: array<int, string>}
     */
    public function resolve(PrintTemplate $template, ?PrintOperationProfile $profile, array $params = [], array $context = []): array
    {
        $resolved = $params;

        foreach ($this->normalizeBindings($profile?->param_bindings) as $binding) {
            $paramKey = trim((string) ($binding['param_key'] ?? ''));
            if ($paramKey === '') {
                continue;
            }

            if (array_key_exists($paramKey, $resolved) && ! $this->isBlank($resolved[$paramKey])) {
                continue;
            }

            $value = $this->resolveBindingValue($binding, $params, $context);
            if (! $this->isBlank($value)) {
                $resolved[$paramKey] = $value;
            }
        }

        $missingRequired = [];

        foreach ($this->normalizeSchema($template->parameters_schema) as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            if (! array_key_exists($key, $resolved) || $this->isBlank($resolved[$key])) {
                $default = $item['default'] ?? null;
                if (! $this->isBlank($default)) {
                    $resolved[$key] = $default;
                }
            }

            $required = (bool) ($item['required'] ?? false);
            if ($required && (! array_key_exists($key, $resolved) || $this->isBlank($resolved[$key]))) {
                $missingRequired[] = $key;
            }
        }

        return [
            'params' => $resolved,
            'missing_required' => $missingRequired,
        ];
    }

    /**
     * @param mixed $schema
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSchema(mixed $schema): array
    {
        if (! is_array($schema)) {
            return [];
        }

        return array_values(array_filter($schema, static fn ($item): bool => is_array($item)));
    }

    /**
     * @param mixed $bindings
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBindings(mixed $bindings): array
    {
        if (! is_array($bindings) || $bindings === []) {
            return [];
        }

        if (! array_is_list($bindings)) {
            $normalized = [];
            foreach ($bindings as $paramKey => $definition) {
                if (! is_array($definition)) {
                    continue;
                }

                $definition['param_key'] = (string) $paramKey;
                $normalized[] = $definition;
            }

            return $normalized;
        }

        return array_values(array_filter($bindings, static fn ($item): bool => is_array($item)));
    }

    /**
     * @param array<string, mixed> $binding
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     */
    private function resolveBindingValue(array $binding, array $params, array $context): mixed
    {
        $enabled = (bool) ($binding['enabled'] ?? true);
        if (! $enabled) {
            return null;
        }

        $sourceType = mb_strtolower(trim((string) ($binding['source_type'] ?? 'context')));

        return match ($sourceType) {
            'fixed' => $binding['fixed_value'] ?? null,
            'params' => Arr::get($params, (string) ($binding['source_path'] ?? '')),
            default => Arr::get($context, (string) ($binding['source_path'] ?? '')),
        };
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
