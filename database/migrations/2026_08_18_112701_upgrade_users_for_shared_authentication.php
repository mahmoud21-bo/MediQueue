<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->softDeletes();
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $parts = preg_split('/\s+/', trim($user->name), 2);

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] ?: 'Legacy',
                'last_name' => $parts[1] ?? '',
                'phone' => '+999'.str_pad((string) $user->id, 12, '0', STR_PAD_LEFT),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
            $table->string('phone', 20)->nullable(false)->change();
            $table->dropColumn(['name', 'remember_token']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->rememberToken();
        });

        DB::table('users')->update([
            'name' => DB::raw("TRIM(CONCAT(first_name, ' ', last_name))"),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'first_name', 'last_name', 'phone', 'is_active', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
