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

        $updated = preg_replace('/\{% if rec_p[1-5] >= 4 %\}.*?\{% endif %\}/s', '', $body);
        if (is_string($updated)) {
            $updated = preg_replace('/\{% if del_p[1-5] >= 4 %\}.*?\{% endif %\}/s', '', $updated);
        }

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
