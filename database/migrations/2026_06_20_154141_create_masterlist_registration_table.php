<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masterlist_registration', function (Blueprint $table) {
            $table->id('masterlist_id');
            $table->foreignId('checklist_id')->nullable()
                  ->constrained('checklist_types', 'checklist_id');
            $table->foreignId('version_id')->nullable()
                  ->constrained('version_type', 'version_id');
            $table->foreignId('request_id')->nullable()
                  ->constrained('document_requests', 'request_id');
            $table->foreignId('doc_type_id')->nullable()
                  ->constrained('doc_types', 'doc_type_id');
            $table->string('doc_no', 100)->nullable();
            $table->date('doc_receipt_date')->nullable();
            $table->time('doc_receipt_time')->nullable();
            $table->date('doc_registered_date')->nullable();
            $table->time('doc_registered_time')->nullable();
            $table->time('time_spent')->nullable();
            $table->string('doc_title')->nullable();
            $table->date('effectivity_date')->nullable();
            $table->integer('revise_no')->nullable();
            $table->integer('no_pages')->nullable();
            $table->string('originator_name')->nullable();
            $table->date('deadline')->nullable();
            $table->text('brief_purpose')->nullable();
            $table->string('scanned_masterlist')->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts', 'id');
            $table->timestamps();
            $table->enum('stamp_status', [
                'controlled', 'obsolete', 'master_copy', 'reference', 'certified_true_copy'
            ])->default('controlled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masterlist_registration');
    }
};