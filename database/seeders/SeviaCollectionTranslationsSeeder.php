<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class SeviaCollectionTranslationsSeeder extends Seeder
{
    /**
     * Add or update the editable copy displayed on Sevia collection pages.
     */
    public function run(): void
    {
        $collections = [
            'muskusni' => [
                'title' => ['uk' => 'Мускусні', 'ru' => 'Мускусные', 'en' => 'Musky'],
                'capsule' => ['uk' => 'Капсула 01', 'ru' => 'Капсула 01', 'en' => 'Capsule 01'],
                'description' => ['uk' => 'Тихі аромати, які лишаються на светрі, а не заходять у кімнату першими. Білий мускус, кашемірове дерево й амбра — те, що читається як «чисто» і тримається на шкірі весь день.', 'ru' => 'Тихие ароматы, которые остаются на свитере, а не входят в комнату первыми. Белый мускус, кашемировое дерево и амбра — то, что воспринимается как «чисто» и держится на коже весь день.', 'en' => 'Quiet fragrances that stay on your sweater rather than entering the room before you. White musk, cashmere wood, and amber feel clean and stay on the skin all day.'],
                'accord_1' => ['uk' => 'Білий мускус', 'ru' => 'Белый мускус', 'en' => 'White musk'],
                'accord_2' => ['uk' => 'Амбра', 'ru' => 'Амбра', 'en' => 'Amber'],
                'accord_3' => ['uk' => 'Кашмір', 'ru' => 'Кашемир', 'en' => 'Cashmere'],
                'accord_4' => ['uk' => 'Сандал', 'ru' => 'Сандал', 'en' => 'Sandalwood'],
            ],
            'kvitkovi' => [
                'title' => ['uk' => 'Квіткові', 'ru' => 'Цветочные', 'en' => 'Floral'],
                'capsule' => ['uk' => 'Капсула 02', 'ru' => 'Капсула 02', 'en' => 'Capsule 02'],
                'description' => ['uk' => 'Квіткові композиції — від легких і прозорих до глибоких, вечірніх.', 'ru' => 'Цветочные композиции — от лёгких и прозрачных до глубоких, вечерних.', 'en' => 'Floral compositions, from light and transparent to deep and evening-ready.'],
                'accord_1' => ['uk' => 'Ірис', 'ru' => 'Ирис', 'en' => 'Iris'],
                'accord_2' => ['uk' => 'Жасмин', 'ru' => 'Жасмин', 'en' => 'Jasmine'],
                'accord_3' => ['uk' => 'Троянда', 'ru' => 'Роза', 'en' => 'Rose'],
                'accord_4' => ['uk' => 'Півонія', 'ru' => 'Пион', 'en' => 'Peony'],
            ],
            'solodki' => [
                'title' => ['uk' => 'Солодкі', 'ru' => 'Сладкие', 'en' => 'Gourmand'],
                'capsule' => ['uk' => 'Капсула 03', 'ru' => 'Капсула 03', 'en' => 'Capsule 03'],
                'description' => ['uk' => 'Теплі гурманські аромати з м’яким, виразним шлейфом.', 'ru' => 'Тёплые гурманские ароматы с мягким, выразительным шлейфом.', 'en' => 'Warm gourmand fragrances with a soft, distinctive trail.'],
                'accord_1' => ['uk' => 'Ваніль', 'ru' => 'Ваниль', 'en' => 'Vanilla'],
                'accord_2' => ['uk' => 'Карамель', 'ru' => 'Карамель', 'en' => 'Caramel'],
                'accord_3' => ['uk' => 'Пудра', 'ru' => 'Пудра', 'en' => 'Powder'],
                'accord_4' => ['uk' => 'Боби тонка', 'ru' => 'Бобы тонка', 'en' => 'Tonka beans'],
            ],
            'svigi-citrusovi' => [
                'title' => ['uk' => 'Свіжі/Цитрусові', 'ru' => 'Свежие/Цитрусовые', 'en' => 'Fresh/Citrus'],
                'capsule' => ['uk' => 'Капсула 04', 'ru' => 'Капсула 04', 'en' => 'Capsule 04'],
                'description' => ['uk' => 'Легкі, прозорі композиції для щоденного настрою.', 'ru' => 'Лёгкие, прозрачные композиции для ежедневного настроения.', 'en' => 'Light, transparent compositions for an everyday mood.'],
                'accord_1' => ['uk' => 'Бергамот', 'ru' => 'Бергамот', 'en' => 'Bergamot'],
                'accord_2' => ['uk' => 'Лимон', 'ru' => 'Лимон', 'en' => 'Lemon'],
                'accord_3' => ['uk' => 'Неролі', 'ru' => 'Нероли', 'en' => 'Neroli'],
                'accord_4' => ['uk' => 'Зелені ноти', 'ru' => 'Зелёные ноты', 'en' => 'Green notes'],
            ],
            'shkiriani' => [
                'title' => ['uk' => 'Шкіряні', 'ru' => 'Кожаные', 'en' => 'Leather'],
                'capsule' => ['uk' => 'Капсула 05', 'ru' => 'Капсула 05', 'en' => 'Capsule 05'],
                'description' => ['uk' => 'Характерні аромати з глибиною, фактурою та впізнаваним шлейфом.', 'ru' => 'Характерные ароматы с глубиной, фактурой и узнаваемым шлейфом.', 'en' => 'Distinctive fragrances with depth, texture, and a recognisable trail.'],
                'accord_1' => ['uk' => 'Шкіра', 'ru' => 'Кожа', 'en' => 'Leather'],
                'accord_2' => ['uk' => 'Замша', 'ru' => 'Замша', 'en' => 'Suede'],
                'accord_3' => ['uk' => 'Тютюн', 'ru' => 'Табак', 'en' => 'Tobacco'],
                'accord_4' => ['uk' => 'Амбра', 'ru' => 'Амбра', 'en' => 'Amber'],
            ],
            'derevni' => [
                'title' => ['uk' => 'Деревні', 'ru' => 'Древесные', 'en' => 'Woody'],
                'capsule' => ['uk' => 'Капсула 06', 'ru' => 'Капсула 06', 'en' => 'Capsule 06'],
                'description' => ['uk' => 'Теплі й виразні деревні композиції на кожен день і для особливих моментів.', 'ru' => 'Тёплые и выразительные древесные композиции на каждый день и для особых моментов.', 'en' => 'Warm, expressive woody compositions for every day and special moments.'],
                'accord_1' => ['uk' => 'Кедр', 'ru' => 'Кедр', 'en' => 'Cedar'],
                'accord_2' => ['uk' => 'Сандал', 'ru' => 'Сандал', 'en' => 'Sandalwood'],
                'accord_3' => ['uk' => 'Ветивер', 'ru' => 'Ветивер', 'en' => 'Vetiver'],
                'accord_4' => ['uk' => 'Пачулі', 'ru' => 'Пачули', 'en' => 'Patchouli'],
            ],
        ];

        foreach ($collections as $key => $texts) {
            foreach ($texts as $field => $value) {
                $this->save("collections.{$key}.{$field}", $value, "Текст колекції Sevia: {$key}.{$field}");
            }
        }

        $sharedTexts = [
            'collections.breadcrumb' => ['uk' => 'Колекції', 'ru' => 'Коллекции', 'en' => 'Collections'],
            'collections.stats.fragrances' => ['uk' => 'Ароматів', 'ru' => 'Ароматов', 'en' => 'Fragrances'],
            'collections.stats.volume' => ['uk' => 'Об’єм розливу', 'ru' => 'Объём распива', 'en' => 'Decant volume'],
            'collections.stats.volume_value' => ['uk' => '3–30 мл', 'ru' => '3–30 мл', 'en' => '3–30 ml'],
            'collections.accord_label' => ['uk' => 'акорд колекції', 'ru' => 'аккорд коллекции', 'en' => 'collection accord'],
            'collections.other.eyebrow' => ['uk' => 'Далі', 'ru' => 'Далее', 'en' => 'Next'],
            'collections.other.title' => ['uk' => 'Інші капсули', 'ru' => 'Другие капсулы', 'en' => 'Other capsules'],
            'collections.discovery.alt' => ['uk' => 'Discovery 5×3', 'ru' => 'Discovery 5×3', 'en' => 'Discovery 5×3'],
            'collections.discovery.eyebrow' => ['uk' => 'Discovery 5×3', 'ru' => 'Discovery 5×3', 'en' => 'Discovery 5×3'],
            'collections.discovery.title' => ['uk' => 'П’ять ароматів капсули по 3 мл — −15%', 'ru' => 'Пять ароматов капсулы по 3 мл — −15%', 'en' => 'Five capsule fragrances, 3 ml each — −15%'],
            'collections.discovery.cta' => ['uk' => 'Зібрати сет →', 'ru' => 'Собрать сет →', 'en' => 'Build a set →'],
        ];

        foreach ($sharedTexts as $slug => $value) {
            $this->save($slug, $value, 'Статичний текст сторінок колекцій Sevia');
        }
    }

    private function save(string $slug, array $value, string $description): void
    {
        SiteText::updateOrCreate(
            ['slug' => $slug],
            ['group' => 'collections', 'value' => $value, 'description' => $description],
        );
    }
}
