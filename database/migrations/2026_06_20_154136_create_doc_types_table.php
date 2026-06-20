<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_types', function (Blueprint $table) {
            $table->id('doc_type_id');
            $table->foreignId('parent_id')->nullable()
                  ->constrained('doc_types', 'doc_type_id')
                  ->cascadeOnDelete();
            $table->string('doc_type_name');
        });

        DB::table('doc_types')->insert([
            ['doc_type_id' => 1, 'parent_id' => null, 'doc_type_name' => 'internal'],
            ['doc_type_id' => 2, 'parent_id' => null, 'doc_type_name' => 'internal forms'],
            ['doc_type_id' => 3, 'parent_id' => null, 'doc_type_name' => 'external'],
            ['doc_type_id' => 4, 'parent_id' => null, 'doc_type_name' => 'forms'],
            ['doc_type_id' => 5, 'parent_id' => null, 'doc_type_name' => 'logbooks'],
            ['doc_type_id' => 6, 'parent_id' => 1, 'doc_type_name' => 'Manuals/Policy'],
            ['doc_type_id' => 7, 'parent_id' => 1, 'doc_type_name' => 'Quality Objectives'],
            ['doc_type_id' => 8, 'parent_id' => 1, 'doc_type_name' => 'FMEA'],
            ['doc_type_id' => 9, 'parent_id' => 1, 'doc_type_name' => 'Work Instructions'],
            ['doc_type_id' => 10, 'parent_id' => 1, 'doc_type_name' => 'Curriculum'],
            ['doc_type_id' => 11, 'parent_id' => 2, 'doc_type_name' => 'Syllabi'],
            ['doc_type_id' => 12, 'parent_id' => 2, 'doc_type_name' => 'TOS/Rubrics'],
            ['doc_type_id' => 13, 'parent_id' => 2, 'doc_type_name' => 'Preventive Maintenance Plan'],
            ['doc_type_id' => 14, 'parent_id' => 2, 'doc_type_name' => 'Faculty Profile'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_types');
    }
};