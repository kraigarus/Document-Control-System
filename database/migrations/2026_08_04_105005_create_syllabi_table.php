<?php
// 029 — syllabi

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')
                  ->constrained('document_requests')
                  ->cascadeOnDelete();
            $table->foreignId('doc_type_id')
                  ->nullable()
                  ->constrained('doc_types')
                  ->nullOnDelete();
            $table->foreignId('college_id')->nullable()
                  ->constrained('colleges')
                  ->nullOnDelete();
            $table->foreignId('program_id')->nullable()
                  ->constrained('programs')
                  ->nullOnDelete();
            $table->foreignId('semester_id')->nullable()
                  ->constrained('semesters')
                  ->nullOnDelete();
            $table->foreignId('school_year_id')->nullable()
                  ->constrained('school_years')
                  ->nullOnDelete();
            $table->foreignId('course_id')
                  ->constrained('program_courses')
                  ->cascadeOnDelete();
            $table->boolean('is_available')->default(false);
            $table->integer('no_copies')->default(1);
            $table->integer('no_pages')->nullable();
            $table->date('date_received')->nullable();
            $table->time('time_received')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
