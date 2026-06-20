<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('related_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('masterlist_id')
                  ->constrained('masterlist_registration', 'masterlist_id')
                  ->cascadeOnDelete();
            $table->foreignId('related_doc_id')
                  ->constrained('masterlist_registration', 'masterlist_id')
                  ->cascadeOnDelete();
            $table->unique(['masterlist_id', 'related_doc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('related_documents');
    }
};