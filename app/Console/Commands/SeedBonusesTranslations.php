<?php

namespace App\Console\Commands;

use App\Models\SiteText;
use Illuminate\Console\Command;

class SeedBonusesTranslations extends Command
{
    protected $signature = 'translations:seed-bonuses';
    protected $description = 'Добавить переводы для страницы бонусов в bs_site_texts';

    public function handle()
    {
        $translations = [
            // Основные ключи страницы бонусов
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.title',
                'value' => ['uk' => 'Бонусы', 'ru' => 'Бонусы', 'en' => 'Bonuses'],
                'description' => 'Заголовок страницы бонусов',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.on_account',
                'value' => ['uk' => 'На счету', 'ru' => 'На счету', 'en' => 'On account'],
                'description' => 'Текст "На счету"',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.bonuses',
                'value' => ['uk' => 'Бонусов', 'ru' => 'Бонусов', 'en' => 'Bonuses'],
                'description' => 'Слово "Бонусов"',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.rules',
                'value' => ['uk' => 'Правила начисления', 'ru' => 'Правила начисления', 'en' => 'Accrual rules'],
                'description' => 'Ссылка на правила начисления',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.history',
                'value' => ['uk' => 'История', 'ru' => 'История', 'en' => 'History'],
                'description' => 'Заголовок истории транзакций',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.no_transactions',
                'value' => ['uk' => 'Нет транзакций', 'ru' => 'Нет транзакций', 'en' => 'No transactions'],
                'description' => 'Сообщение при отсутствии транзакций',
            ],
            // Месяцы
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.jan',
                'value' => ['uk' => 'Янв', 'ru' => 'Янв', 'en' => 'Jan'],
                'description' => 'Январь (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.feb',
                'value' => ['uk' => 'Фев', 'ru' => 'Фев', 'en' => 'Feb'],
                'description' => 'Февраль (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.mar',
                'value' => ['uk' => 'Мар', 'ru' => 'Мар', 'en' => 'Mar'],
                'description' => 'Март (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.apr',
                'value' => ['uk' => 'Апр', 'ru' => 'Апр', 'en' => 'Apr'],
                'description' => 'Апрель (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.may',
                'value' => ['uk' => 'Май', 'ru' => 'Май', 'en' => 'May'],
                'description' => 'Май (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.jun',
                'value' => ['uk' => 'Июн', 'ru' => 'Июн', 'en' => 'Jun'],
                'description' => 'Июнь (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.jul',
                'value' => ['uk' => 'Июл', 'ru' => 'Июл', 'en' => 'Jul'],
                'description' => 'Июль (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.aug',
                'value' => ['uk' => 'Авг', 'ru' => 'Авг', 'en' => 'Aug'],
                'description' => 'Август (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.sep',
                'value' => ['uk' => 'Сен', 'ru' => 'Сен', 'en' => 'Sep'],
                'description' => 'Сентябрь (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.oct',
                'value' => ['uk' => 'Окт', 'ru' => 'Окт', 'en' => 'Oct'],
                'description' => 'Октябрь (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.nov',
                'value' => ['uk' => 'Ноя', 'ru' => 'Ноя', 'en' => 'Nov'],
                'description' => 'Ноябрь (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.dec',
                'value' => ['uk' => 'Дек', 'ru' => 'Дек', 'en' => 'Dec'],
                'description' => 'Декабрь (сокращение)',
            ],
            // Дни недели
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.mon',
                'value' => ['uk' => 'Пн', 'ru' => 'Пн', 'en' => 'Mon'],
                'description' => 'Понедельник (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.tue',
                'value' => ['uk' => 'Вт', 'ru' => 'Вт', 'en' => 'Tue'],
                'description' => 'Вторник (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.wed',
                'value' => ['uk' => 'Ср', 'ru' => 'Ср', 'en' => 'Wed'],
                'description' => 'Среда (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.thu',
                'value' => ['uk' => 'Чт', 'ru' => 'Чт', 'en' => 'Thu'],
                'description' => 'Четверг (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.fri',
                'value' => ['uk' => 'Пт', 'ru' => 'Пт', 'en' => 'Fri'],
                'description' => 'Пятница (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.sat',
                'value' => ['uk' => 'Сб', 'ru' => 'Сб', 'en' => 'Sat'],
                'description' => 'Суббота (сокращение)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.sun',
                'value' => ['uk' => 'Вс', 'ru' => 'Вс', 'en' => 'Sun'],
                'description' => 'Воскресенье (сокращение)',
            ],
            // Типы транзакций
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.purchase',
                'value' => ['uk' => 'Покупка', 'ru' => 'Покупка', 'en' => 'Purchase'],
                'description' => 'Тип транзакции: Покупка',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.spend',
                'value' => ['uk' => 'Списание', 'ru' => 'Списание', 'en' => 'Debit'],
                'description' => 'Тип транзакции: Списание',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.expire',
                'value' => ['uk' => 'Истечение', 'ru' => 'Истечение', 'en' => 'Expiration'],
                'description' => 'Тип транзакции: Истечение',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.manual_transaction',
                'value' => ['uk' => 'Ручная транзакция', 'ru' => 'Ручная транзакция', 'en' => 'Manual transaction'],
                'description' => 'Тип транзакции: Ручная транзакция',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.reverse',
                'value' => ['uk' => 'Отмена', 'ru' => 'Отмена', 'en' => 'Reverse'],
                'description' => 'Тип транзакции: Отмена',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.bonuses.transaction',
                'value' => ['uk' => 'Транзакция', 'ru' => 'Транзакция', 'en' => 'Transaction'],
                'description' => 'Тип транзакции: Общее',
            ],
        ];

        $this->info('Добавление переводов для страницы бонусов...');

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
            $this->line("✓ Добавлен/обновлен: {$data['slug']}");
        }

        $this->info('✅ Все переводы успешно добавлены!');
        return 0;
    }
}

