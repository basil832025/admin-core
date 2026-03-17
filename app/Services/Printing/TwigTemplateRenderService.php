<?php

namespace App\Services\Printing;

use App\Models\PrintTemplate;
use App\Reports\DataProviders\ReportDataProviderInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;

class TwigTemplateRenderService
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array{html: string, datasets: array<string, array<int|string, mixed>>}
     */
    public function renderTemplate(PrintTemplate $template, array $params = [], array $context = []): array
    {
        $datasets = $this->resolveDataSources($template, $params, $context);

        $viewData = [
            'params' => $params,
            'context' => $context,
            'datasets' => $datasets,
            'now' => now(),
        ];

        if (is_array($context)) {
            $viewData = array_merge($viewData, $context);
        }

        $twig = $this->makeTwig((string) $template->template_body);
        $html = $twig->render('tpl', $viewData);

        return [
            'html' => $html,
            'datasets' => $datasets,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<string, array<int|string, mixed>>
     */
    private function resolveDataSources(PrintTemplate $template, array $params, array $context): array
    {
        $sources = $template->data_sources;
        if (! is_array($sources) || $sources === []) {
            return [];
        }

        $datasets = [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $enabled = (bool) ($source['enabled'] ?? true);
            if (! $enabled) {
                continue;
            }

            $key = trim((string) ($source['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $type = mb_strtolower(trim((string) ($source['type'] ?? 'sql')));

            if ($type === 'provider') {
                $datasets[$key] = $this->resolveProviderDataset($source, $params, $context);
                continue;
            }

            $datasets[$key] = $this->resolveSqlDataset($source, $params, $context);
        }

        return $datasets;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    private function resolveSqlDataset(array $source, array $params, array $context): array
    {
        $sql = trim((string) ($source['query'] ?? ''));
        if ($sql === '') {
            return [];
        }

        if (! $this->isSafeSelectQuery($sql)) {
            throw new \RuntimeException('SQL datasource supports SELECT queries only.');
        }

        $bindingNames = $this->extractBindingNames($sql);
        $missing = [];

        foreach ($bindingNames as $bindingName) {
            $hasInParams = $this->hasBindingValue($params, $bindingName);
            $hasInContext = $this->hasBindingValue($context, $bindingName);

            if (! $hasInParams && ! $hasInContext) {
                $missing[] = $bindingName;
            }
        }

        if ($missing !== []) {
            $key = (string) ($source['key'] ?? 'sql');
            throw new \RuntimeException('SQL datasource "' . $key . '" missing required params: ' . implode(', ', $missing));
        }

        $bindings = $this->extractBindings($sql, $params, $context);
        $connection = trim((string) ($source['connection'] ?? ''));
        $db = $connection !== '' ? DB::connection($connection) : DB::connection();
        $rows = $db->select($sql, $bindings);

        return array_map(static fn ($row): array => (array) $row, $rows);
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<int|string, mixed>
     */
    private function resolveProviderDataset(array $source, array $params, array $context): array
    {
        $providerClass = trim((string) ($source['provider_class'] ?? ''));
        if ($providerClass === '') {
            return [];
        }

        if (! $this->isProviderAllowed($providerClass)) {
            throw new \RuntimeException('Provider class is not allowed: ' . $providerClass);
        }

        if (! class_exists($providerClass)) {
            throw new \RuntimeException('Provider class not found: ' . $providerClass);
        }

        $provider = app($providerClass);

        if (! $provider instanceof ReportDataProviderInterface) {
            throw new \RuntimeException('Provider must implement ReportDataProviderInterface.');
        }

        return $provider->resolve($params, $context);
    }

    private function isProviderAllowed(string $providerClass): bool
    {
        $allowedNamespaces = (array) config('printing.allowed_provider_namespaces', []);

        foreach ($allowedNamespaces as $namespace) {
            $namespace = trim((string) $namespace);
            if ($namespace !== '' && str_starts_with($providerClass, $namespace)) {
                return true;
            }
        }

        return false;
    }

    private function isSafeSelectQuery(string $query): bool
    {
        $normalized = mb_strtolower(trim($query));

        if ($normalized === '' || ! str_starts_with($normalized, 'select')) {
            return false;
        }

        if (str_contains($normalized, ';')) {
            return false;
        }

        $forbidden = ['insert ', 'update ', 'delete ', 'drop ', 'alter ', 'truncate ', 'create ', 'grant ', 'revoke '];
        foreach ($forbidden as $word) {
            if (str_contains($normalized, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function extractBindings(string $query, array $params, array $context): array
    {
        $bindingNames = $this->extractBindingNames($query);
        $bindings = [];

        foreach ($bindingNames as $name) {
            $value = Arr::get($params, $name, Arr::get($context, $name));
            $bindings[$name] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $bindings;
    }

    /**
     * @return array<int, string>
     */
    private function extractBindingNames(string $query): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasBindingValue(array $data, string $key): bool
    {
        $marker = new \stdClass;

        return Arr::get($data, $key, $marker) !== $marker;
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

    private function makeTwig(string $templateBody): Environment
    {
        $loader = new ArrayLoader([
            'tpl' => $templateBody,
        ]);

        $policy = new SecurityPolicy(
            ['if', 'for', 'set'],
            ['escape', 'e', 'upper', 'lower', 'length', 'join', 'date', 'number_format', 'default', 'nl2br', 'raw'],
            [],
            [],
            ['range']
        );

        $twig = new Environment($loader, [
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);

        $twig->addExtension(new SandboxExtension($policy, true));

        return $twig;
    }
}
