<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'vutog_group')
            ->first(['id', 'template_body']);

        if (! $template) {
            return;
        }

        $body = (string) ($template->template_body ?? '');
        if ($body === '') {
            return;
        }

        $body = str_replace(
            '<div style="width:220px;height:220px;margin:0 auto;">',
            '<div style="width:460px;height:460px;margin:0 auto;">',
            $body,
        );

        $body = str_replace(
            "{{ chart_donut_png(chart.values, chart.colors, chart.total|number_format(0, '.', ''))|e }}",
            "{{ chart_donut_png(chart.values, chart.colors, chart.total|number_format(0, '.', ''), 460)|e }}",
            $body,
        );

        $body = str_replace(
            'style="display:block;width:220px;height:220px;"',
            'style="display:block;width:460px;height:460px;"',
            $body,
        );

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
