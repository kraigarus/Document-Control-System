<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_courses', function (Blueprint $table) {
            $table->id('course_id');

            $table->foreignId('program_id')
                  ->constrained('programs', 'program_id')
                  ->cascadeOnDelete();

            $table->foreignId('semester_id')
                  ->constrained('semesters', 'semester_id')
                  ->cascadeOnDelete();

            $table->string('course_name');
            $table->timestamps();

            // Prevent the same course being listed twice under the same program+semester
            $table->unique(['program_id', 'semester_id', 'course_name'], 'program_courses_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_courses');
    }
};