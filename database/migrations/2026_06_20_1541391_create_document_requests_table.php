<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('version_id')
                  ->constrained('version_type', 'version_id');
            $table->foreignId('doc_type_id')
                  ->constrained('doc_types', 'doc_type_id');
            $table->foreignId('sub_type_id')->nullable()
                  ->constrained('doc_types', 'doc_type_id');
            $table->enum('approval_status', ['applicable', 'not_applicable'])->nullable();
            $table->foreignId('created_by')
                  ->constrained('accounts', 'id');
            $table->foreignId('updated_by')->nullable()
                  ->constrained('accounts', 'id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};