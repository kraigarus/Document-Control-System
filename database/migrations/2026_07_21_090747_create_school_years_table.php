<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id('school_year_id');
            $table->string('school_year', 50)->unique();
            $table->timestamps();
        });

        // Insert Default Data
        DB::table('school_years')->insert([
            ['school_year' => '2025-2026', 'created_at' => now(), 'updated_at' => now()],
            ['school_year' => '2026-2027', 'created_at' => now(), 'updated_at' => now()],
            ['school_year' => '2027-2028', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};