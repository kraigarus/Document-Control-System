<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_request_form', function (Blueprint $table) {
            $table->id('drf_id');
            $table->foreignId('checklist_id')->nullable()
                  ->constrained('checklist_types', 'checklist_id');
            $table->foreignId('version_id')->nullable()
                  ->constrained('version_type', 'version_id');
            $table->foreignId('request_id')->nullable()
                  ->constrained('document_requests', 'request_id');
            $table->foreignId('doc_type_id')->nullable()
                  ->constrained('doc_types', 'doc_type_id');
            $table->string('drf_no', 100)->nullable();
            $table->date('drf_date')->nullable();
            $table->date('drf_receipt_date')->nullable();
            $table->time('drf_receipt_time')->nullable();
            $table->foreignId('office_id')->nullable()
                  ->constrained('offices', 'office_id');
            $table->string('doc_title')->nullable();
            $table->string('scanned_drf')->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts', 'id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_form');
    }
};