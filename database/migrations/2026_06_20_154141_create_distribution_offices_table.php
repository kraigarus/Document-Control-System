<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')
                  ->constrained('document_distribution', 'distribution_id')
                  ->cascadeOnDelete();
            $table->foreignId('office_id')
                  ->constrained('offices', 'office_id');
            $table->integer('copies')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_offices');
    }
};