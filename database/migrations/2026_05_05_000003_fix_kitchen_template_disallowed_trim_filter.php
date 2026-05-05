<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $body = (string) DB::table('bs_print_templates')->where('id', 1)->value('template_body');
        if ($body === '') {
            return;
        }

        // Twig sandbox may disallow the "trim" filter. Replace it with explicit conditional output.
        $needle = "{{ (deliveryDate ~ ' ' ~ deliveryTime)|trim }}";
        if (strpos($body, $needle) === false) {
            return;
        }

        $replacement = <<<'TWIG'
{% if deliveryDate is not empty and deliveryTime is not empty %}
{{ deliveryDate ~ ' ' ~ deliveryTime }}
{% elseif deliveryDate is not empty %}
{{ deliveryDate }}
{% else %}
{{ deliveryTime }}
{% endif %}
TWIG;

        $body = str_replace($needle, $replacement, $body);

        DB::table('bs_print_templates')
            ->where('id', 1)
            ->update([
                'template_body' => $body,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op.
    }
};
