<?php

namespace App\Services;

use App\Models\Shop\Prro;

class PrroConfigService
{
    public function getForCashalot(): array
    {
        $record = Prro::query()
            ->where('is_active', true)
            ->where('use_for_liqpay', true)
            ->latest('id')
            ->first();

        return [
            'numfiscal' => trim((string) ($record?->prro_number ?? config('cashalot.numfiscal', ''))),
            'certificate' => trim((string) ($record?->certificate_base64 ?? config('cashalot.certificate', ''))),
            'key' => trim((string) ($record?->key_base64 ?? config('cashalot.key', ''))),
            'password' => (string) ($record?->key_password ?? config('cashalot.password', '')),
            'source' => $record ? 'prro' : 'config',
        ];
    }
}
