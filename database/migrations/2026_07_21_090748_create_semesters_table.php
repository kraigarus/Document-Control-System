<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id('semester_id');
            $table->string('semester_name', 50);
            $table->timestamps();
        });

        // Insert Default Data
        DB::table('semesters')->insert([
            ['semester_name' => '1st Semester', 'created_at' => now(), 'updated_at' => now()],
            ['semester_name' => '2nd Semester', 'created_at' => now(), 'updated_at' => now()],
            ['semester_name' => 'Summer', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
