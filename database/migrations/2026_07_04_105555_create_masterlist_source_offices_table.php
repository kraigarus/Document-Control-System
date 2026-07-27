<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masterlist_source_offices', function (Blueprint $table) {
            $table->id('masterlist_office_id');

            $table->foreignId('masterlist_id')
                ->constrained('masterlist_registration', 'masterlist_id')
                ->onDelete('cascade');

            $table->foreignId('office_id')
                ->nullable()
                ->constrained('offices', 'office_id')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('masterlist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masterlist_source_offices');
    }
};