<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drf_offices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('drf_id')
                  ->constrained('document_request_form', 'drf_id')
                  ->cascadeOnDelete();

            // Foreign Key to Offices
            $table->foreignId('office_id')
                  ->constrained('offices', 'office_id')
                  ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drf_offices');
    }
};