<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('bs_clients', function (Blueprint $t) {
            if (!Schema::hasColumn('bs_clients','email_verified_at')) $t->timestamp('email_verified_at')->nullable()->after('email');
            if (!Schema::hasColumn('bs_clients','phone_verified_at')) $t->timestamp('phone_verified_at')->nullable()->after('phone');

            if (!Schema::hasColumn('bs_clients','provider_name')) $t->string('provider_name')->nullable()->after('password');
            if (!Schema::hasColumn('bs_clients','provider_id'))   $t->string('provider_id')->nullable()->index()->after('provider_name');

            if (!Schema::hasColumn('bs_clients','remember_token')) $t->rememberToken();
        });
    }
    public function down(): void {
        Schema::table('bs_clients', function (Blueprint $t) {
            $t->dropColumn(['email_verified_at','phone_verified_at','provider_name','provider_id','remember_token']);
        });
    }
};
