<?php
// 027 — program_courses

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                  ->constrained('programs')
                  ->cascadeOnDelete();
            $table->foreignId('semester_id')
                  ->constrained('semesters')
                  ->cascadeOnDelete();
            $table->string('course_name');
            $table->timestamps();

            $table->unique(['program_id', 'semester_id', 'course_name'], 'program_courses_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_courses');
    }
};