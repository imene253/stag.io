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

            $table->string('file_path');              
            $table->string('convention_number')->unique(); 
            $table->date('generated_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conventions');
    }
};