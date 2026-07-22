<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('originators', function (Blueprint $table) {
            $table->id('originator_id');
            $table->string('originator_name')->unique();
            $table->timestamps();
        });

        // Insert 10 Default Originators
        DB::table('originators')->insert([
            ['originator_id' => 1,  'originator_name' => 'Office of the University President', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 2,  'originator_name' => 'Office of the Vice President for Academic Affairs', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 3,  'originator_name' => 'Office of the Vice President for Administration and Finance', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 4,  'originator_name' => 'Quality Assurance Office', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 5,  'originator_name' => 'Office of the University Registrar', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 6,  'originator_name' => 'Guidance and Counseling Office', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 7,  'originator_name' => 'Student Affairs and Services Office', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 8,  'originator_name' => 'Human Resource Management Office', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 9,  'originator_name' => 'Research and Development Office', 'created_at' => now(), 'updated_at' => now()],
            ['originator_id' => 10, 'originator_name' => 'Extension Services Office', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('originators');
    }
};