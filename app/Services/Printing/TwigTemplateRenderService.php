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
use Twig\TwigFunction;

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
            ['range', 'chart_donut_png']
        );

        $twig = new Environment($loader, [
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);

        $twig->addFunction(new TwigFunction('chart_donut_png', function (mixed $values, mixed $colors, mixed $centerText = null, mixed $size = 220): string {
            return $this->buildDonutChartPngDataUri($values, $colors, $centerText, $size);
        }));

        $twig->addExtension(new SandboxExtension($policy, true));

        return $twig;
    }

    private function buildDonutChartPngDataUri(mixed $values, mixed $colors, mixed $centerText = null, mixed $size = 220): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return '';
        }

        $numbers = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $num = (float) $value;
            $numbers[] = $num > 0 ? $num : 0.0;
        }

        if ($numbers === []) {
            return '';
        }

        $palette = [];
        foreach (is_array($colors) ? $colors : [] as $color) {
            $hex = trim((string) $color);
            if ($hex !== '') {
                $palette[] = $hex;
            }
        }

        if ($palette === []) {
            $palette = ['#4f81bd', '#c0504d', '#9bbb59', '#8064a2', '#4bacc6'];
        }

        $sizePx = max(120, min(800, (int) $size));
        $img = imagecreatetruecolor($sizePx, $sizePx);
        if (! $img) {
            return '';
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);

        $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $transparent);
        imagealphablending($img, true);

        $cx = (int) ($sizePx / 2);
        $cy = (int) ($sizePx / 2);
        $diameter = (int) round($sizePx * 0.636);
        $sum = array_sum($numbers);
        if ($sum <= 0) {
            $sum = 1.0;
        }

        $start = -90.0;
        $segments = [];
        foreach ($numbers as $index => $value) {
            if ($value <= 0) {
                continue;
            }

            $angle = 360.0 * ($value / $sum);
            $end = $start + $angle;

            $hex = $palette[$index % count($palette)];
            [$r, $g, $b] = $this->hexToRgb($hex);
            $color = imagecolorallocate($img, $r, $g, $b);

            imagefilledarc(
                $img,
                $cx,
                $cy,
                $diameter,
                $diameter,
                (int) round($start),
                (int) round($end),
                $color,
                IMG_ARC_PIE
            );

            $segments[] = [
                'start' => $start,
                'end' => $end,
                'percent' => ($value / $sum) * 100,
            ];

            $start = $end;
        }

        $hole = imagecolorallocate($img, 255, 255, 255);
        $innerDiameter = (int) round($sizePx * 0.345);
        imagefilledellipse($img, $cx, $cy, $innerDiameter, $innerDiameter, $hole);

        $stroke = imagecolorallocate($img, 226, 232, 240);
        imageellipse($img, $cx, $cy, $innerDiameter, $innerDiameter, $stroke);

        $text = trim((string) ($centerText ?? ''));
        if ($text !== '') {
            $textColor = imagecolorallocate($img, 51, 65, 85);
            $font = 3;
            $textWidth = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontheight($font);
            imagestring(
                $img,
                $font,
                (int) round($cx - $textWidth / 2),
                (int) round($cy - $textHeight / 2),
                $text,
                $textColor
            );
        }

        $labelTextColor = imagecolorallocate($img, 31, 41, 55);
        $labelFill = imagecolorallocate($img, 255, 255, 255);
        $labelBorder = imagecolorallocate($img, 203, 213, 225);
        $labelFont = 3;
        $labelRadius = $sizePx * 0.318;

        foreach ($segments as $segment) {
            $percent = (float) ($segment['percent'] ?? 0);
            if ($percent < 4.0) {
                continue;
            }

            $mid = (((float) $segment['start']) + ((float) $segment['end'])) / 2.0;
            $rad = deg2rad($mid);
            $x = (int) round($cx + cos($rad) * $labelRadius);
            $y = (int) round($cy + sin($rad) * $labelRadius);

            $label = (string) round($percent).'%';
            $labelWidth = imagefontwidth($labelFont) * strlen($label);
            $labelHeight = imagefontheight($labelFont);

            $padX = 4;
            $padY = 2;
            $left = $x - (int) round($labelWidth / 2) - $padX;
            $top = $y - (int) round($labelHeight / 2) - $padY;
            $right = $left + $labelWidth + ($padX * 2);
            $bottom = $top + $labelHeight + ($padY * 2);

            imagefilledrectangle($img, $left, $top, $right, $bottom, $labelFill);
            imagerectangle($img, $left, $top, $right, $bottom, $labelBorder);
            imagestring($img, $labelFont, $left + $padX, $top + $padY, $label, $labelTextColor);
        }

        ob_start();
        imagepng($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        if ($binary === '') {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [79, 129, 189];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
