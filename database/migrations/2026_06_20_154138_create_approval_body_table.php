<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_body', function (Blueprint $table) {
            $table->id('approval_body_id');
            $table->string('approval_name');
        });

        DB::table('approval_body')->insert([
            ['approval_body_id' => 1, 'approval_name' => 'Board of Trustee'],
            ['approval_body_id' => 2, 'approval_name' => 'Admin Council'],
            ['approval_body_id' => 3, 'approval_name' => 'Acad Council'],
            ['approval_body_id' => 4, 'approval_name' => 'RIC Council'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_body');
    }
};