// database/migrations/xxxx_create_internship_offers_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
            
            $table->string('title');                        
            $table->text('description');                  
            $table->string('domain');                     
            $table->string('location');                   
            $table->enum('type', [
                'présentiel',
                'télétravail',
                'hybride',
            ])->default('présentiel');
            $table->enum('duration_unit', [
                'weeks',
                'months',
            ])->default('months');
            $table->integer('duration_value');              // e.g. 2 (months) or 6 (weeks)
            $table->json('required_skills')->nullable();    // ["React", "Node.js"]
            $table->enum('status', [
                'open',
                'closed',
            ])->default('open');
            $table->date('deadline')->nullable();           // application deadline

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_offers');
    }
};