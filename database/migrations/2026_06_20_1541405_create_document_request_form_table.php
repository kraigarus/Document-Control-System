<?php
// 010 — document_request_form

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_request_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->nullable()
                  ->constrained('checklist_types');
            $table->foreignId('version_id')->nullable()
                  ->constrained('version_type');
            $table->foreignId('request_id')->nullable()
                  ->constrained('document_requests');
            $table->foreignId('doc_type_id')->nullable()
                  ->constrained('doc_types');
            $table->string('drf_no', 100)->nullable();
            $table->date('drf_date')->nullable();
            $table->date('drf_receipt_date')->nullable();
            $table->time('drf_receipt_time')->nullable();
            $table->string('doc_title')->nullable();
            $table->string('scanned_drf')->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_form');
    }
};