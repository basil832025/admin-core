<?php

namespace App\Services\SiteTemplates;

use App\Models\SiteTemplateOverride;
use App\Services\HeaderContacts;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;

class SiteTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $key, string $fallbackView, array $data = []): ViewContract|Response
    {
        $data = $this->withSharedFrontendData($data);

        $override = SiteTemplateOverride::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $override || blank($override->override_body)) {
            return view($fallbackView, $data);
        }

        $html = Blade::render((string) $override->override_body, $data, deleteCachedView: true);

        return response($html);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withSharedFrontendData(array $data): array
    {
        if (array_key_exists('headerLocation', $data)) {
            return $data;
        }

        $slug = config('site.header_location_slug', '3pie');
        $header = app(HeaderContacts::class)->buildBySlug($slug);

        return array_merge([
            'headerPhones' => $header['phones'] ?? collect(),
            'headerPhonePrimary' => $header['primary'] ?? null,
            'headerLocation' => $header['location'] ?? null,
            'headerSchedule' => $header['schedule'] ?? collect(),
        ], $data);
    }
}
