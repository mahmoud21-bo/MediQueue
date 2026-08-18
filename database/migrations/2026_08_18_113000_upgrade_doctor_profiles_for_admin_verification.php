<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->renameColumn('verified_at', 'reviewed_at');
            $table->renameColumn('verified_by', 'reviewed_by');
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            $table->timestamp('application_submitted_at')->nullable()->after('rejection_reason');
        });

        DB::table('doctor_profiles')->orderBy('id')->each(function (object $profile): void {
            DB::table('doctor_profiles')->where('id', $profile->id)->update([
                'approved_at' => $profile->verification_status === 'approved' ? $profile->reviewed_at : null,
                'application_submitted_at' => $profile->created_at,
            ]);
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->timestamp('application_submitted_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'application_submitted_at']);
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->renameColumn('reviewed_at', 'verified_at');
            $table->renameColumn('reviewed_by', 'verified_by');
        });
    }
};
