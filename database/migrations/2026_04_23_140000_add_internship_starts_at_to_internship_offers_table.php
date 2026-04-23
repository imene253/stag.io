<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_offers', function (Blueprint $table): void {
            $table->date('internship_starts_at')->nullable()->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('internship_offers', function (Blueprint $table): void {
            $table->dropColumn('internship_starts_at');
        });
    }
};
