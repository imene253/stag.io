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
            $table->string('student_number')->unique()->nullable();
            $table->string('department')->nullable();
            $table->integer('year_of_study')->nullable();
            $table->text('bio')->nullable();
            $table->string('github_link')->nullable();
            $table->string('portfolio_link')->nullable();
            $table->json('skills')->nullable(); // ["React", "Java", "Python"]
            $table->string('wilaya')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};