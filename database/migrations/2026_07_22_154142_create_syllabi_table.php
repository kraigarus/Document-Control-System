<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabi', function (Blueprint $table) {
            $table->id('syllabi_id');

            // Primary Document Request Relationship
            $table->foreignId('request_id')
                  ->constrained('document_requests', 'request_id')
                  ->cascadeOnDelete();

            // Syllabi Context Foreign Keys
            $table->foreignId('college_id')->nullable()->constrained('colleges', 'college_id')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs', 'program_id')->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters', 'semester_id')->nullOnDelete();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years', 'school_year_id')->nullOnDelete();

            // Course Info (Step 1)
            $table->string('course_name')->nullable();
            $table->boolean('syllabi_availability')->default(false);
            $table->integer('no_copies')->nullable();
            $table->string('originator')->nullable();
            $table->integer('no_pages')->nullable();
            $table->date('date_received')->nullable();
            $table->time('time_received')->nullable();

            // DRF (Step 2)
            $table->boolean('drf_availability')->default(false);
            $table->string('drf_no', 100)->nullable();
            $table->date('drf_date')->nullable();
            $table->date('drf_received_date')->nullable();
            $table->string('scanned_drf')->nullable();

            // Registration (Step 3)
            $table->boolean('registered')->default(false);
            $table->date('date_of_registration')->nullable();
            $table->time('time_of_registration')->nullable();
            $table->integer('time_spent')->nullable();

            // Standard Laravel created_at & updated_at
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};