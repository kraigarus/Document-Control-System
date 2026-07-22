<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('account_role')->default('staff');
            $table->boolean('account_active')->default(true);
            $table->timestamp('date_created')->nullable();
            $table->timestamp('date_modified')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()
                  ->constrained('accounts')
                  ->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        DB::table('accounts')->insert([
            'email'          => 'admin@admin.dcs',
            'password'       => Hash::make('123123123'),
            'account_role'   => 'admin',
            'account_active' => true,
            'date_created'   => now(),
            'date_modified'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('accounts');
    }
};