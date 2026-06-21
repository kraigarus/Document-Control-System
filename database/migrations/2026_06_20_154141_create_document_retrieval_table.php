<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_retrieval', function (Blueprint $table) {
            $table->id('retrieval_id');
            $table->foreignId('checklist_id')->nullable()
                  ->constrained('checklist_types', 'checklist_id');
            $table->foreignId('version_id')->nullable()
                  ->constrained('version_type', 'version_id');
            $table->foreignId('request_id')->nullable()
                  ->constrained('document_requests', 'request_id');
            $table->foreignId('doc_type_id')->nullable()
                  ->constrained('doc_types', 'doc_type_id');
            $table->date('doc_retrieval_date_actual')->nullable();
            $table->time('doc_retrieval_time_actual')->nullable();
            $table->date('doc_retrieval_date_file')->nullable();
            $table->time('doc_retrieval_time_file')->nullable();
            $table->time('time_spent')->nullable();
            $table->text('remarks')->nullable();
            $table->string('scanned_retrieval')->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts', 'id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_retrieval');
    }
};