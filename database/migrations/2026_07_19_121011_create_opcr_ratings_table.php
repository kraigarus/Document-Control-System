<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_ratings', function (Blueprint $table) {
            $table->id();
            $table->integer('request_id');
            $table->string('sub_type');
            $table->decimal('rating_q', 5, 2)->nullable();
            $table->decimal('rating_e', 5, 2)->nullable();
            $table->decimal('rating_t', 5, 2)->nullable();
            $table->decimal('rating_a', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'sub_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_ratings');
    }
};