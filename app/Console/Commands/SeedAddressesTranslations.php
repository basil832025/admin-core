<?php

namespace App\Console\Commands;

use App\Models\SiteText;
use Illuminate\Console\Command;

class SeedAddressesTranslations extends Command
{
    protected $signature = 'translations:seed-addresses';
    protected $description = 'Добавить переводы для страницы адресов доставки в bs_site_texts';

    public function handle()
    {
        $translations = [
            // Профиль адресов - основные ключи
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.title',
                'value' => ['uk' => 'Адреса доставки', 'ru' => 'Адреса доставки', 'en' => 'Delivery Addresses'],
                'description' => 'Заголовок страницы адресов доставки',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.edit_title',
                'value' => ['uk' => 'Редагувати адресу', 'ru' => 'Редактировать адрес', 'en' => 'Edit Address'],
                'description' => 'Заголовок формы редактирования адреса',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.create_title',
                'value' => ['uk' => 'Додати адресу', 'ru' => 'Добавить адрес', 'en' => 'Add Address'],
                'description' => 'Заголовок формы создания адреса',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.edit',
                'value' => ['uk' => 'Редагувати', 'ru' => 'Редактировать', 'en' => 'Edit'],
                'description' => 'Кнопка редактирования',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.delete',
                'value' => ['uk' => 'Видалити', 'ru' => 'Удалить', 'en' => 'Delete'],
                'description' => 'Кнопка удаления',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.delete_confirm',
                'value' => ['uk' => 'Ви впевнені, що хочете видалити цю адресу?', 'ru' => 'Вы уверены, что хотите удалить этот адрес?', 'en' => 'Are you sure you want to delete this address?'],
                'description' => 'Подтверждение удаления адреса',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.empty',
                'value' => ['uk' => 'У вас немає збережених адресів', 'ru' => 'У вас нет сохраненных адресов', 'en' => 'You have no saved addresses'],
                'description' => 'Сообщение при отсутствии адресов',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.add',
                'value' => ['uk' => 'Додати новий адрес', 'ru' => 'Добавить новый адрес', 'en' => 'Add New Address'],
                'description' => 'Кнопка добавления нового адреса',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.update',
                'value' => ['uk' => 'Оновити', 'ru' => 'Обновить', 'en' => 'Update'],
                'description' => 'Кнопка обновления',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.save',
                'value' => ['uk' => 'Зберегти', 'ru' => 'Сохранить', 'en' => 'Save'],
                'description' => 'Кнопка сохранения',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.cancel',
                'value' => ['uk' => 'Скасувати', 'ru' => 'Отменить', 'en' => 'Cancel'],
                'description' => 'Кнопка отмены',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.success_added',
                'value' => ['uk' => 'Адреса успішно додана', 'ru' => 'Адрес успешно добавлен', 'en' => 'Address successfully added'],
                'description' => 'Сообщение об успешном добавлении адреса',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.success_updated',
                'value' => ['uk' => 'Адреса успішно оновлена', 'ru' => 'Адрес успешно обновлен', 'en' => 'Address successfully updated'],
                'description' => 'Сообщение об успешном обновлении адреса',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.addresses.success_deleted',
                'value' => ['uk' => 'Адреса успішно видалена', 'ru' => 'Адрес успешно удален', 'en' => 'Address successfully deleted'],
                'description' => 'Сообщение об успешном удалении адреса',
            ],
            // Форма адреса - поля
            [
                'group' => 'address',
                'slug' => 'address.form.city',
                'value' => ['uk' => 'Місто', 'ru' => 'Город', 'en' => 'City'],
                'description' => 'Поле формы: Город',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.street',
                'value' => ['uk' => 'Вулиця', 'ru' => 'Улица', 'en' => 'Street'],
                'description' => 'Поле формы: Улица',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.house',
                'value' => ['uk' => 'Дім', 'ru' => 'Дом', 'en' => 'House'],
                'description' => 'Поле формы: Дом',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.apartment',
                'value' => ['uk' => 'Квартира', 'ru' => 'Квартира', 'en' => 'Apartment'],
                'description' => 'Поле формы: Квартира',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.intercom',
                'value' => ['uk' => 'Домофон', 'ru' => 'Домофон', 'en' => 'Intercom'],
                'description' => 'Поле формы: Домофон',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.floor',
                'value' => ['uk' => 'Поверх', 'ru' => 'Этаж', 'en' => 'Floor'],
                'description' => 'Поле формы: Этаж',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.porch',
                'value' => ['uk' => 'Під\'їзд', 'ru' => 'Подъезд', 'en' => 'Entrance'],
                'description' => 'Поле формы: Подъезд',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.type',
                'value' => ['uk' => 'Тип адреси', 'ru' => 'Тип адреса', 'en' => 'Address Type'],
                'description' => 'Поле формы: Тип адреса',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.type_select',
                'value' => ['uk' => 'Оберіть тип', 'ru' => 'Выберите тип', 'en' => 'Select Type'],
                'description' => 'Плейсхолдер выбора типа адреса',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.note',
                'value' => ['uk' => 'Примітка', 'ru' => 'Примечание', 'en' => 'Note'],
                'description' => 'Поле формы: Примечание',
            ],
            [
                'group' => 'address',
                'slug' => 'address.form.private_house',
                'value' => ['uk' => 'Це приватний будинок', 'ru' => 'Это частный дом', 'en' => 'This is a private house'],
                'description' => 'Чекбокс: Частный дом',
            ],
            // Типы адресов
            [
                'group' => 'address',
                'slug' => 'address.type.home',
                'value' => ['uk' => 'Дім', 'ru' => 'Дом', 'en' => 'Home'],
                'description' => 'Тип адреса: Дом',
            ],
            [
                'group' => 'address',
                'slug' => 'address.type.work',
                'value' => ['uk' => 'Робота', 'ru' => 'Работа', 'en' => 'Work'],
                'description' => 'Тип адреса: Работа',
            ],
            [
                'group' => 'address',
                'slug' => 'address.type.friends',
                'value' => ['uk' => 'Друзі', 'ru' => 'Друзья', 'en' => 'Friends'],
                'description' => 'Тип адреса: Друзья',
            ],
            // Части адреса (префиксы)
            [
                'group' => 'address',
                'slug' => 'address.parts.street_prefix',
                'value' => ['uk' => 'вулиця', 'ru' => 'ул.', 'en' => 'st.'],
                'description' => 'Префикс улицы',
            ],
            [
                'group' => 'address',
                'slug' => 'address.parts.house_short',
                'value' => ['uk' => 'д.', 'ru' => 'д.', 'en' => 'bld.'],
                'description' => 'Сокращение для дома',
            ],
            [
                'group' => 'address',
                'slug' => 'address.parts.apartment_short',
                'value' => ['uk' => 'кв. ', 'ru' => 'кв. ', 'en' => 'apt. '],
                'description' => 'Сокращение для квартиры',
            ],
        ];

        $this->info('Добавление переводов для адресов доставки...');

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

