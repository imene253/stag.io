// database/migrations/xxxx_create_company_profiles_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('company_name');
            $table->string('industry')->nullable();       
            $table->string('location')->nullable();     
            $table->string('website_url')->nullable();
            $table->enum('company_size', [
                '1-10',
                '11-50',
                '51-200',
                '201-500',
                '500+',
            ])->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};