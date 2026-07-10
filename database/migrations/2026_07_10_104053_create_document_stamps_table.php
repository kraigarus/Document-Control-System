<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_stamps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_request_id');
            $table->string('file_key', 30);
            $table->string('file_path', 500);
            $table->string('stamp_type', 50);
            $table->string('position', 30)->default('bottom-right');
            $table->boolean('all_pages')->default(true);
            $table->string('certified_by', 255)->nullable();
            $table->string('designation', 255)->nullable();
            $table->unsignedBigInteger('stamped_by')->nullable();
            $table->timestamp('stamped_at');
            $table->timestamps();

            // Unique constraint: one stamp per file per document
            $table->unique(['document_request_id', 'file_key'], 'unique_stamp_per_file');
            $table->index('file_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_stamps');
    }
};