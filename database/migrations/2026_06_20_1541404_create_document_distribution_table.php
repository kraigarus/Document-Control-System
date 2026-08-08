<?php
// 014 — document_distribution

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_distribution', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->nullable()
                  ->constrained('checklist_types');
            $table->foreignId('version_id')->nullable()
                  ->constrained('version_type');
            $table->foreignId('request_id')->nullable()
                  ->constrained('document_requests');
            $table->foreignId('doc_type_id')->nullable()
                  ->constrained('doc_types');
            $table->date('doc_distribution_date_actual')->nullable();
            $table->time('doc_distribution_time_actual')->nullable();
            $table->date('doc_distribution_date_file')->nullable();
            $table->time('doc_distribution_time_file')->nullable();
            $table->time('time_spent')->nullable();
            $table->text('remarks')->nullable();
            $table->string('scanned_distribution')->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_distribution');
    }
};