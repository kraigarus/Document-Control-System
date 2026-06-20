<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_types', function (Blueprint $table) {
            $table->id('checklist_id');
            $table->string('checklist_name')->unique();
        });

        DB::table('checklist_types')->insert([
            ['checklist_id' => 1, 'checklist_name' => 'Document Request Form'],
            ['checklist_id' => 2, 'checklist_name' => 'Document Change Notice'],
            ['checklist_id' => 3, 'checklist_name' => 'Masterlist Registration'],
            ['checklist_id' => 4, 'checklist_name' => 'Document Retrieval'],
            ['checklist_id' => 5, 'checklist_name' => 'Document Distribution'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_types');
    }
};