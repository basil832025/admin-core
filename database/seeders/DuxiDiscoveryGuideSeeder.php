<?php

namespace Database\Seeders;

use App\Models\Pages;
use Illuminate\Database\Seeder;
use RuntimeException;

class DuxiDiscoveryGuideSeeder extends Seeder
{
    public function run(): void
    {
        $connection = (new Pages())->getConnection();

        if (config('project.name') !== 'duxi'
            || $connection->getDriverName() !== 'mysql'
            || ! in_array($connection->getDatabaseName(), ['duxi', 'xfizwzzz_sevia'], true)) {
            throw new RuntimeException('This seeder requires the DUXI project and an approved DUXI MySQL database.');
        }

        Pages::query()->firstOrCreate(['slug' => 'discovery-53-how-it-works'], [
            'title' => ['uk' => 'Discovery 5 × 3 мл — як зібрати свій парфумерний сет'],
            'meta_description' => ['uk' => 'Як обрати п’ять ароматів по 3 мл, зібрати свій Discovery і отримати знижку 15% на весь сет.'],
            'status' => 'published',
            'content' => ['uk' => <<<'HTML'
<p>Обери п’ять різних ароматів по 3 мл і отримай <strong>−15% на весь сет</strong>. Склад Discovery ти визначаєш самостійно. Можеш порівняти аромати улюбленого бренду або зібрати в одному наборі композиції, які давно хотілося спробувати.</p>
<p>Або ж створи свій парфумерний гардероб на сезон. Наприклад, обери п’ять улюбленців на зиму й чергуй їх під настрій. Разом дешевше.</p>
<h2>Як зібрати сет</h2>
<ol>
    <li><strong>Обери п’ять ароматів.</strong> На сторінці Discovery натискай «Додати» біля ароматів, які хочеш спробувати. Лічильник покаже, скільки ще потрібно обрати.</li>
    <li><strong>Перевір склад і ціну.</strong> У нижній панелі видно вибрані аромати й вартість зі знижкою. Щоб замінити аромат, прибери його із сету й додай інший.</li>
    <li><strong>Додай сет у кошик.</strong> Кнопка стане доступною, щойно обереш усі п’ять ароматів. Знижка застосовується автоматично, без промокоду. Якщо перед оформленням захочеш щось замінити, натисни «Змінити склад» у кошику.</li>
</ol>
<h2>Від чого залежить ціна</h2>
<p>Вартість сету залежить від обраних ароматів. До суми цін п’яти позицій по 3 мл застосовується знижка 15%. Наприклад, якщо разом вони коштують 1 000 грн, ціна сету становитиме <strong>850 грн</strong>.</p>
<p>Спробуй аромати в різні дні й дай кожному час розкритися на шкірі. Так легше зрозуміти, до якого хочеться повернутися.</p>
HTML],
        ]);
    }
}
