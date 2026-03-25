<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'sales_receiving_delivery_time_analysis')
            ->first(['id', 'template_body']);

        if (! $template) {
            return;
        }

        $body = (string) ($template->template_body ?? '');
        if ($body === '') {
            return;
        }

        foreach (['rec_m1', 'rec_m2', 'rec_m3', 'rec_m4', 'rec_m5', 'del_m1', 'del_m2', 'del_m3', 'del_m4', 'del_m5'] as $token) {
            $body = str_replace(
                'rotate(-{{ ('.$token.' - 90)|number_format(2, \'\.\', \'\') }}deg)',
                'rotate({{ (90 - '.$token.')|number_format(2, \'\.\', \'\') }}deg)',
                $body,
            );
        }

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'template_body' => $body,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
