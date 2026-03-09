<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conventions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                  ->unique()                        
                  ->constrained('applications')
                  ->onDelete('cascade');

            $table->string('file_path');              // storage path of the PDF
            $table->string('convention_number')->unique(); // e.g. CONV-2026-0001
            $table->date('generated_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conventions');
    }
};