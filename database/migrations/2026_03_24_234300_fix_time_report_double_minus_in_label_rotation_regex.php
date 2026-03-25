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

        $updated = preg_replace_callback(
            '/rotate\(-\{\{\s*\((rec_m[1-5]|del_m[1-5])\s*-\s*90\)\|number_format\(2, \'\\.\', \'\'\)\s*\}\}deg\)/',
            static function (array $matches): string {
                $token = (string) ($matches[1] ?? '');
                if ($token === '') {
                    return (string) ($matches[0] ?? '');
                }

                return "rotate({{ (90 - {$token})|number_format(2, '.', '') }}deg)";
            },
            $body,
        );

        if (! is_string($updated) || $updated === $body) {
            return;
        }

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'template_body' => $updated,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
