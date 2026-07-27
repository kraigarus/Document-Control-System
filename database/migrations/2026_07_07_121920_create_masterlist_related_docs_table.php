<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masterlist_related_docs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('masterlist_id');
            $table->unsignedBigInteger('related_doc_id');
            $table->timestamps();

            $table->foreign('masterlist_id')->references('masterlist_id')->on('masterlist_registration')->onDelete('cascade');
            $table->foreign('related_doc_id')->references('request_id')->on('document_requests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masterlist_related_docs');
    }
};