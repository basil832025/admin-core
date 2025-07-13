<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use App\Models\Blog;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewBlog extends ViewRecord
{
    protected static string $resource = BlogResource::class;
    protected static ?string $navigationLabel = 'Просмотреть блог';
    public function getTitle(): string | Htmlable
    {
        /** @var Post */
        $record = $this->getRecord();

        return $record->title;
    }

    protected function getActions(): array
    {
        return [];
    }
}
