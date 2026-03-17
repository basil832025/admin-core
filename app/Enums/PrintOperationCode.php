<?php

namespace App\Enums;

enum PrintOperationCode: string
{
    case KitchenWorkReceipt = 'kitchen_work_receipt';
    case ClientReceipt = 'client_receipt';
    case LogisticReceipt = 'logistic_receipt';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::KitchenWorkReceipt->value => 'Робочий чек кухні',
            self::ClientReceipt->value => 'Чек для клієнта',
            self::LogisticReceipt->value => 'Чек для логіста',
        ];
    }
}
