// database/migrations/xxxx_create_student_profiles_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

           
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('wilaya')->nullable();

           
            $table->string('university')->nullable();
            $table->string('field_of_study')->nullable();
            $table->string('academic_level')->nullable(); // free text, no enum

         
            $table->json('skills')->nullable();         
            $table->string('portfolio_link')->nullable(); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};