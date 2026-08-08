<?php
// 015 — distribution_offices

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
                  ->constrained('document_distribution')
                  ->cascadeOnDelete();
            $table->foreignId('office_id')
                  ->constrained('offices');
            $table->integer('copies')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_offices');
    }
};
