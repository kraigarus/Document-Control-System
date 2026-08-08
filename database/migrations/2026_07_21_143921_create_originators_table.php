<?php
// 026 — originators

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('originators', function (Blueprint $table) {
            $table->id();
            $table->string('originator_name')->unique();
            $table->timestamps();
        });

        DB::table('originators')->insert([
            ['id' => 1,  'originator_name' => 'Juan Dela Cruz',    'created_at' => now(), 'updated_at' => now()],
            ['id' => 2,  'originator_name' => 'Maria De Jesus',    'created_at' => now(), 'updated_at' => now()],
            ['id' => 3,  'originator_name' => 'John Doe',          'created_at' => now(), 'updated_at' => now()],
            ['id' => 4,  'originator_name' => 'Mark Zuckerberg',   'created_at' => now(), 'updated_at' => now()],
            ['id' => 5,  'originator_name' => 'Stepanie Maslow',   'created_at' => now(), 'updated_at' => now()],
            ['id' => 6,  'originator_name' => 'George Field',      'created_at' => now(), 'updated_at' => now()],
            ['id' => 7,  'originator_name' => 'Mccoy Roi',         'created_at' => now(), 'updated_at' => now()],
            ['id' => 8,  'originator_name' => 'Troy George',       'created_at' => now(), 'updated_at' => now()],
            ['id' => 9,  'originator_name' => 'Rain Gerarnd',      'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'originator_name' => 'Troy Husley',       'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('originators');
    }
};