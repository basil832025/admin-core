<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('id', 1)
            ->first(['id', 'template_body', 'data_sources']);

        if (! $template) {
            return;
        }

        $body = (string) ($template->template_body ?? '');

        // 1) Update the "Доставка" line to UA date format and add "Час видачi".
        // Replace the entire delivery block if it exists.
        $needle = '<div class="k-delivery-line">';
        $pos = strpos($body, $needle);

        if ($pos !== false) {
            $endPos = strpos($body, '</div>', $pos);
            if ($endPos !== false) {
                // Expand to include nested content: find the closing </div> of the delivery block.
                // We know the block contains another <div> wrapper, so take the next closing </div> as well.
                $endPos2 = strpos($body, '</div>', $endPos + 6);
                if ($endPos2 !== false) {
                    $endPos = $endPos2 + 6;
                } else {
                    $endPos = $endPos + 6;
                }

                $replacement = <<<'TWIG'
{% set deliveryDate = order.date_order is not empty ? (order.date_order|date('d.m.Y')) : '' %}
{% set deliveryTime = order.time_order is not empty ? (order.time_order|date('H:i')) : '' %}
{% set issueTime = order.time_issue is not empty ? (order.time_issue|date('H:i')) : '' %}

<div class="k-delivery-line">
<strong>Доставка:</strong>
<span class="k-delivery-box">{{ (deliveryDate ~ ' ' ~ deliveryTime)|trim }}</span>
</div>

<div class="k-issue-line">
<strong>Час видачі:</strong>
<span class="k-delivery-box">{{ issueTime|default('-') }}</span>
</div>
TWIG;

                $body = substr($body, 0, $pos)
                    . $replacement
                    . substr($body, $endPos);
            }
        }

        // 2) Ensure the SQL datasource for datasets.order selects o.time_issue.
        $sourcesRaw = $template->data_sources;
        $sources = is_string($sourcesRaw) ? json_decode($sourcesRaw, true) : null;
        if (! is_array($sources)) {
            $sources = [];
        }

        foreach ($sources as &$source) {
            if (! is_array($source)) {
                continue;
            }
            if (($source['key'] ?? null) !== 'order') {
                continue;
            }
            $query = (string) ($source['query'] ?? '');
            if ($query === '') {
                continue;
            }
            if (strpos($query, 'o.time_issue') === false) {
                $query = str_replace("  o.time_order,\n", "  o.time_order,\n  o.time_issue,\n", $query);
                $source['query'] = $query;
            }
        }
        unset($source);

        DB::table('bs_print_templates')
            ->where('id', 1)
            ->update([
                'template_body' => $body,
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op: this migration edits a template in-place.
    }
};
