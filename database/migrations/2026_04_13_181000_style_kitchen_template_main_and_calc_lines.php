<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'receipt_kitchen_default')
            ->first(['id', 'template_body']);

        if (! $template) {
            return;
        }

        $body = (string) $template->template_body;

        // Главные позиции — жирным (кол-во + название).
        $oldMainBlock = <<<'TWIG'
<div>
<strong>
{{ item.qty|default(0) }}
x </strong>
{{ size != '' ? (title ~ ' (' ~ size ~ ')') : title }}

</div>
TWIG;

        $newMainBlock = <<<'TWIG'
<div>
<strong>{{ item.qty|default(0) }}x {{ size != '' ? (title ~ ' (' ~ size ~ ')') : title }}</strong>
</div>
TWIG;

        if (str_contains($body, $oldMainBlock)) {
            $body = str_replace($oldMainBlock, $newMainBlock, $body);
        }

        // Компоненты калькуляции — курсивом и без жирного.
        $body = str_replace(
            '<div style="padding-left:3mm;white-space:pre-line;">',
            '<div style="padding-left:3mm;white-space:pre-line;font-style:italic;font-weight:400;">',
            $body
        );

        DB::table('bs_print_templates')
            ->where('id', $template->id)
            ->update([
                'template_body' => $body,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Не откатываем автоматически, чтобы не терять ручные правки шаблона.
    }
};
