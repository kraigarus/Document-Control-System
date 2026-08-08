<?php
// 012 — document_change_notice

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_change_notice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->nullable()
                  ->constrained('checklist_types');
            $table->foreignId('version_id')->nullable()
                  ->constrained('version_type');
            $table->foreignId('request_id')->nullable()
                  ->constrained('document_requests');
            $table->foreignId('doc_type_id')->nullable()
                  ->constrained('doc_types');
            $table->string('dcn_no', 100)->nullable();
            $table->date('dcn_date')->nullable();
            $table->date('dcn_receipt_date')->nullable();
            $table->time('dcn_receipt_time')->nullable();
            $table->foreignId('office_id')->nullable()
                  ->constrained('offices');
            $table->string('scanned_dcn')->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_change_notice');
    }
};