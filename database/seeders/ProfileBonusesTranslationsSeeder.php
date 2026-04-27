<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class ProfileBonusesTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            'profile.bonuses.title' => ['uk' => 'Бонуси', 'ru' => 'Бонусы', 'en' => 'Bonuses'],
            'profile.bonuses.on_account' => ['uk' => 'На рахунку', 'ru' => 'На счету', 'en' => 'On account'],
            'profile.bonuses.bonuses' => ['uk' => 'Бонусів', 'ru' => 'Бонусов', 'en' => 'Bonuses'],
            'profile.bonuses.rules' => ['uk' => 'Правила нарахування', 'ru' => 'Правила начисления', 'en' => 'Accrual rules'],
            'profile.bonuses.history' => ['uk' => 'Історія', 'ru' => 'История', 'en' => 'History'],
            'profile.bonuses.no_transactions' => ['uk' => 'Немає транзакцій', 'ru' => 'Нет транзакций', 'en' => 'No transactions'],

            'profile.bonuses.jan' => ['uk' => 'Січ', 'ru' => 'Янв', 'en' => 'Jan'],
            'profile.bonuses.feb' => ['uk' => 'Лют', 'ru' => 'Фев', 'en' => 'Feb'],
            'profile.bonuses.mar' => ['uk' => 'Бер', 'ru' => 'Мар', 'en' => 'Mar'],
            'profile.bonuses.apr' => ['uk' => 'Кві', 'ru' => 'Апр', 'en' => 'Apr'],
            'profile.bonuses.may' => ['uk' => 'Тра', 'ru' => 'Май', 'en' => 'May'],
            'profile.bonuses.jun' => ['uk' => 'Чер', 'ru' => 'Июн', 'en' => 'Jun'],
            'profile.bonuses.jul' => ['uk' => 'Лип', 'ru' => 'Июл', 'en' => 'Jul'],
            'profile.bonuses.aug' => ['uk' => 'Сер', 'ru' => 'Авг', 'en' => 'Aug'],
            'profile.bonuses.sep' => ['uk' => 'Вер', 'ru' => 'Сен', 'en' => 'Sep'],
            'profile.bonuses.oct' => ['uk' => 'Жов', 'ru' => 'Окт', 'en' => 'Oct'],
            'profile.bonuses.nov' => ['uk' => 'Лис', 'ru' => 'Ноя', 'en' => 'Nov'],
            'profile.bonuses.dec' => ['uk' => 'Гру', 'ru' => 'Дек', 'en' => 'Dec'],

            'profile.bonuses.mon' => ['uk' => 'Пн', 'ru' => 'Пн', 'en' => 'Mon'],
            'profile.bonuses.tue' => ['uk' => 'Вт', 'ru' => 'Вт', 'en' => 'Tue'],
            'profile.bonuses.wed' => ['uk' => 'Ср', 'ru' => 'Ср', 'en' => 'Wed'],
            'profile.bonuses.thu' => ['uk' => 'Чт', 'ru' => 'Чт', 'en' => 'Thu'],
            'profile.bonuses.fri' => ['uk' => 'Пт', 'ru' => 'Пт', 'en' => 'Fri'],
            'profile.bonuses.sat' => ['uk' => 'Сб', 'ru' => 'Сб', 'en' => 'Sat'],
            'profile.bonuses.sun' => ['uk' => 'Нд', 'ru' => 'Вс', 'en' => 'Sun'],

            'profile.bonuses.purchase' => ['uk' => 'Покупка', 'ru' => 'Покупка', 'en' => 'Purchase'],
            'profile.bonuses.spend' => ['uk' => 'Списання', 'ru' => 'Списание', 'en' => 'Debit'],
            'profile.bonuses.expire' => ['uk' => 'Термін дії сплив', 'ru' => 'Истечение', 'en' => 'Expiration'],
            'profile.bonuses.manual_transaction' => ['uk' => 'Ручна транзакція', 'ru' => 'Ручная транзакция', 'en' => 'Manual transaction'],
            'profile.bonuses.reverse' => ['uk' => 'Скасування', 'ru' => 'Отмена', 'en' => 'Reverse'],
            'profile.bonuses.transaction' => ['uk' => 'Транзакція', 'ru' => 'Транзакция', 'en' => 'Transaction'],
        ];

        foreach ($translations as $slug => $value) {
            SiteText::updateOrCreate(
                ['slug' => $slug],
                [
                    'group' => 'profile',
                    'value' => $value,
                    'description' => 'Profile bonuses page translation',
                ]
            );
        }
    }
}
