<?php

namespace App\Support\TemplatePages;

use Illuminate\Support\Str;

class TemplatePageRegistry
{
    public function all(): array
    {
        $templatesPath = $this->templatesPath();

        if (! is_dir($templatesPath)) {
            return [];
        }

        $templates = [];

        foreach (glob($templatesPath . '/*/template.blade.php') ?: [] as $templateFile) {
            $template = basename(dirname($templateFile));
            $templates[$template] = [
                'key' => $template,
                'label' => Str::headline($template),
                'view' => $this->viewName($template),
                'schema' => $this->schemaPath($template),
                'preview' => $this->previewPath($template),
            ];
        }

        ksort($templates);

        return $templates;
    }

    public function options(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $template, string $key): array => [$key => $template['label']])
            ->all();
    }

    public function has(string $template): bool
    {
        return isset($this->all()[$template]);
    }

    public function viewName(string $template): string
    {
        return 'front.' . config('project.theme', '3piroga') . "::page-templates.{$template}.template";
    }

    public function schema(string $template, array $locales, string $defaultLocale): array
    {
        $schemaPath = $this->schemaPath($template);

        if (! is_file($schemaPath)) {
            return [];
        }

        $schema = require $schemaPath;

        if (is_callable($schema)) {
            return $schema($locales, $defaultLocale);
        }

        return is_array($schema) ? $schema : [];
    }

    private function frontendPackage(): string
    {
        $project = (string) config('project.name', '3piroga');

        return (string) config("projects.local.{$project}.frontend_package", "frontend-{$project}");
    }

    private function templatesPath(): string
    {
        return base_path("packages/{$this->frontendPackage()}/resources/views/page-templates");
    }

    private function schemaPath(string $template): string
    {
        return base_path("packages/{$this->frontendPackage()}/resources/page-schemas/{$template}.php");
    }

    private function previewPath(string $template): ?string
    {
        $path = base_path("packages/{$this->frontendPackage()}/resources/views/page-templates/{$template}/preview.jpg");

        return is_file($path) ? $path : null;
    }
}
