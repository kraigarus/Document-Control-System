<?php
// 006 — checklist_types

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_types', function (Blueprint $table) {
            $table->id();
            $table->string('checklist_name')->unique();
        });

        DB::table('checklist_types')->insert([
            ['id' => 1, 'checklist_name' => 'Document Request Form'],
            ['id' => 2, 'checklist_name' => 'Document Change Notice'],
            ['id' => 3, 'checklist_name' => 'Masterlist Registration'],
            ['id' => 4, 'checklist_name' => 'Document Retrieval'],
            ['id' => 5, 'checklist_name' => 'Document Distribution'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_types');
    }
};