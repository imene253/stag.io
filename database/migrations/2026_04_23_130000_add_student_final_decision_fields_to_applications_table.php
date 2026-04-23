<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->timestamp('selected_at')->nullable()->after('admin_note');
            $table->date('internship_starts_at')->nullable()->after('selected_at');
            $table->date('internship_ends_at')->nullable()->after('internship_starts_at');
        });

        DB::statement(
            "ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','refused','validated','rejected','selected') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','refused','validated','rejected') NOT NULL DEFAULT 'pending'"
        );

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn([
                'selected_at',
                'internship_starts_at',
                'internship_ends_at',
            ]);
        });
    }
};
