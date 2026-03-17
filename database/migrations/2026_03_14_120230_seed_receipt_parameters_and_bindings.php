<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $paramsSchema = [
            [
                'key' => 'order_id',
                'label' => 'ID замовлення',
                'type' => 'number',
                'required' => true,
                'default' => '',
            ],
        ];

        DB::table('bs_print_templates')
            ->whereIn('code', ['receipt_kitchen_default', 'receipt_client_default', 'receipt_logistic_default'])
            ->update([
                'parameters_schema' => json_encode($paramsSchema, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $bindings = [
            [
                'param_key' => 'order_id',
                'source_type' => 'context',
                'source_path' => 'order.id',
                'enabled' => true,
            ],
        ];

        DB::table('bs_print_operation_profiles')
            ->whereIn('operation_code', ['kitchen_work_receipt', 'client_receipt', 'logistic_receipt'])
            ->update([
                'param_bindings' => json_encode($bindings, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('bs_print_operation_profiles')
            ->whereIn('operation_code', ['kitchen_work_receipt', 'client_receipt', 'logistic_receipt'])
            ->update([
                'param_bindings' => null,
                'updated_at' => now(),
            ]);
    }
};
