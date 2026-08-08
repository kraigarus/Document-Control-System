<?php
// 004 — approval_body

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_body', function (Blueprint $table) {
            $table->id();
            $table->string('approval_name');
        });

        DB::table('approval_body')->insert([
            ['id' => 1, 'approval_name' => 'Board of Trustees'],
            ['id' => 2, 'approval_name' => 'Admin Council'],
            ['id' => 3, 'approval_name' => 'Acad Council'],
            ['id' => 4, 'approval_name' => 'RIC Council'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_body');
    }
};