<?php
// 008 — document_requests

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')
                  ->constrained('version_type');
            $table->foreignId('doc_type_id')
                  ->constrained('doc_types');
            $table->foreignId('sub_type_id')->nullable()
                  ->constrained('doc_types');
            $table->enum('approval_status', ['applicable', 'not_applicable'])->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts');
            $table->foreignId('updated_by')->nullable()
                  ->constrained('accounts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};