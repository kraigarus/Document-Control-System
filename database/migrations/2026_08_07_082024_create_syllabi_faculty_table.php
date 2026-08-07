<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabi_row_faculty', function (Blueprint $table) {
            $table->id('syllabi_row_faculty_id');

            $table->foreignId('syllabi_id')
                  ->constrained('syllabi', 'syllabi_id')
                  ->cascadeOnDelete();

            // Nullable so a free-typed name (not in the faculties master list) still works
            $table->foreignId('faculty_id')->nullable()
                  ->constrained('faculties', 'faculty_id')
                  ->nullOnDelete();

            // Always stored, even for free text, so display never depends on the FK existing
            $table->string('faculty_name');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi_row_faculty');
    }
};