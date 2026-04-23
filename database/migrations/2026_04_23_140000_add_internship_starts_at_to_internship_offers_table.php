<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('internship_offers', 'internship_starts_at')) {
            Schema::table('internship_offers', function (Blueprint $table): void {
                $table->date('internship_starts_at')->nullable()->after('deadline');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('internship_offers', 'internship_starts_at')) {
            Schema::table('internship_offers', function (Blueprint $table): void {
                $table->dropColumn('internship_starts_at');
            });
        }
    }
};
