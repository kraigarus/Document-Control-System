<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_records', function (Blueprint $table) {
            $table->id('approval_id');
            $table->foreignId('checklist_id')->nullable()
                  ->constrained('checklist_types', 'checklist_id');
            $table->foreignId('version_id')->nullable()
                  ->constrained('version_type', 'version_id');
            $table->foreignId('request_id')->nullable()
                  ->constrained('document_requests', 'request_id');
            $table->foreignId('doc_type_id')->nullable()
                  ->constrained('doc_types', 'doc_type_id');
            $table->foreignId('approval_body_id')->nullable()
                  ->constrained('approval_body', 'approval_body_id');
            $table->date('approval_date')->nullable();
            $table->string('approval_no', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
    }
};