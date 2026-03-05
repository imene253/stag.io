// database/migrations/xxxx_create_applications_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            // ─── Relations ─────────────────────────────────────
            $table->foreignId('student_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->foreignId('offer_id')
                  ->constrained('internship_offers')
                  ->onDelete('cascade');

            // ─── Status Flow ───────────────────────────────────
            // pending → accepted/refused (by company)
            // accepted → validated/rejected (by admin)
            $table->enum('status', [
                'pending',    
                'accepted',   
                'refused',    
                'validated',  // admin validated → generates PDF
                'rejected',   // admin rejected
            ])->default('pending');

            // ─── Optional cover letter ─────────────────────────
            $table->text('cover_letter')->nullable();

            // ─── Admin notes ───────────────────────────────────
            $table->text('admin_note')->nullable();

            // ─── Unique: one application per student per offer ─
            $table->unique(['student_id', 'offer_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};