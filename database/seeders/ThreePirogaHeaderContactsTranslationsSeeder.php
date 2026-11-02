<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class ThreePirogaHeaderContactsTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        if (config('project.name') !== '3piroga') {
            $this->command?->warn('Header contacts translations are intended for 3piroga only.');

            return;
        }

        $translations = [
            'header.contacts.title' => [
                'uk' => 'Наші телефони',
                'ru' => 'Наши телефоны',
                'en' => 'Our phone numbers',
            ],
            'header.contacts.hint' => [
                'uk' => 'Телефонуйте, допоможемо із замовленням',
                'ru' => 'Звоните, поможем с заказом',
                'en' => 'Call us for help with your order',
            ],
            'header.contacts.close' => [
                'uk' => 'Закрити',
                'ru' => 'Закрыть',
                'en' => 'Close',
            ],
        ];

        foreach ($translations as $slug => $defaults) {
            $row = SiteText::query()->firstOrNew(['slug' => $slug]);
            $values = $row->exists ? $row->getTranslations('value') : [];

            // Preserve translations edited in the admin; fill only missing locales.
            foreach ($defaults as $locale => $text) {
                if (trim((string) ($values[$locale] ?? '')) === '') {
                    $values[$locale] = $text;
                }
            }

            $row->fill([
                'group' => $row->group ?: 'header',
                'value' => $values,
                'description' => $row->description ?: '3piroga: mobile header contacts dialog',
            ]);

            // Saving also clears the site text cache through SiteText model events.
            $row->save();
        }
    }
}
