<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        // 1) таблица групп (создастся только если её ещё нет)
        if (! Schema::hasTable('bs_site_text_groups')) {
            Schema::create('bs_site_text_groups', function (Blueprint $t) {
                $t->id();
                $t->string('slug')->unique();
                $t->json('title')->nullable();
                $t->string('description')->nullable();
                $t->unsignedInteger('position')->default(0);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }

        // 2) колонка group_id (добавим, только если её нет)
        if (! Schema::hasColumn('bs_site_texts', 'group_id')) {
            Schema::table('bs_site_texts', function (Blueprint $t) {
                $t->unsignedBigInteger('group_id')->nullable()->after('id');
            });
        }

        // 3) внешний ключ (проверка через information_schema)
        $fkExists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'bs_site_texts')
            ->where('COLUMN_NAME', 'group_id')
            ->where('REFERENCED_TABLE_NAME', 'bs_site_text_groups')
            ->exists();

        if (! $fkExists) {
            Schema::table('bs_site_texts', function (Blueprint $t) {
                $t->foreign('group_id')
                    ->references('id')
                    ->on('bs_site_text_groups')
                    ->nullOnDelete();
            });
        }

        // 4) перенос уникальных строковых групп в новую таблицу и связывание
        $groups = DB::table('bs_site_texts')->select('group')->distinct()->pluck('group')
            ->filter(fn ($g) => filled($g))->values();

        foreach ($groups as $i => $g) {
            // upsert по slug — чтобы не дублировать при повторном запуске
            DB::table('bs_site_text_groups')->updateOrInsert(
                ['slug' => $g],
                [
                    'title'      => json_encode(['uk' => $g], JSON_UNESCAPED_UNICODE),
                    'position'   => $i + 1,
                    'active'     => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $gid = DB::table('bs_site_text_groups')->where('slug', $g)->value('id');
            DB::table('bs_site_texts')->where('group', $g)->update(['group_id' => $gid]);
        }
    }

    public function down(): void
    {
        // сначала уберём FK, потом колонку (если существуют)
        // имя FK может отличаться, поэтому снимаем все FK с колонки group_id
        $hasGroupId = Schema::hasColumn('bs_site_texts', 'group_id');

        if ($hasGroupId) {
            // попытка снять FK «в лоб» (если имя сгенерировано Eloquent)
            Schema::table('bs_site_texts', function (Blueprint $t) {
                try { $t->dropForeign(['group_id']); } catch (\Throwable $e) {}
            });

            // затем удалить колонку
            Schema::table('bs_site_texts', function (Blueprint $t) {
                $t->dropColumn('group_id');
            });
        }

        // никаких dropIndex('group') — мы его не создавали в up()
        Schema::dropIfExists('bs_site_text_groups');
    }
};
