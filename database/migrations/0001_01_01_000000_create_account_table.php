<?php
// 001 — RMS identity: roles, office, account, sessions, subsystems

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condition_details', function (Blueprint $table) {
            $table->increments('key_id');
            $table->boolean('is_sadm')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('can_access_dts')->default(false);
            $table->boolean('can_access_rdp')->default(false);
            $table->boolean('can_access_dcs')->default(false);
            $table->boolean('can_dts_modify_docflow')->default(false);
            $table->boolean('can_sadm_modify_accountlist')->default(false);
            $table->boolean('can_sadm_modify_pass')->default(false);
            $table->boolean('can_sadm_modify_account')->default(false);
            $table->boolean('can_dts_view_all_list')->default(false);
            $table->boolean('can_dts_view_all_archive')->default(false);
        });

        Schema::create('condition_key', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key_name');
            $table->text('key_description')->nullable();
            $table->unsignedInteger('modifier_key');
            $table->dateTime('date_created')->useCurrent();
            $table->dateTime('date_updated')->useCurrent();
            $table->foreign('modifier_key')->references('key_id')->on('condition_details');
        });

        Schema::create('condition_defaults', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('key_id');
            $table->foreign('key_id')->references('id')->on('condition_key');
        });

        Schema::create('office', function (Blueprint $table) {
            $table->increments('id');
            $table->string('office_name')->unique();
            $table->string('office_code', 50)->unique();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('account', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('password');
            $table->unsignedInteger('account_status')->default(1);
            $table->unsignedInteger('account_role')->nullable();
            $table->boolean('account_active')->default(true);
            $table->dateTime('date_created')->useCurrent();
            $table->dateTime('date_updated')->useCurrent();
            $table->foreign('account_status')->references('id')->on('condition_key');
            $table->foreign('account_role')->references('id')->on('condition_key');
        });

        Schema::create('account_details', function (Blueprint $table) {
            $table->unsignedInteger('account_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->unsignedInteger('office_id')->nullable();
            $table->string('email')->unique();
            $table->string('contact_number', 25)->nullable();
            $table->boolean('is_currently_online')->default(false);
            $table->dateTime('last_online_time')->nullable();
            $table->primary('account_id');
            $table->foreign('account_id')->references('id')->on('account')->onDelete('cascade');
            $table->foreign('office_id')->references('id')->on('office')->nullOnDelete();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('account')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('subsystems', function (Blueprint $table) {
            $table->integer('subsystem_id')->primary()->autoIncrement();
            $table->string('subsystem_name', 255)->unique();
            $table->string('subsystem_version', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->index()->useCurrent();
            $table->timestamp('update_at')->index()->useCurrent();
        });

        $defaultDetailsId = DB::table('condition_details')->insertGetId([
            'is_sadm' => false,
            'is_admin' => false,
            'can_access_dts' => false,
            'can_access_rdp' => false,
            'can_access_dcs' => false,
            'can_dts_modify_docflow' => false,
            'can_sadm_modify_accountlist' => false,
            'can_sadm_modify_pass' => false,
            'can_sadm_modify_account' => false,
            'can_dts_view_all_list' => false,
            'can_dts_view_all_archive' => false,
        ], 'key_id');

        $defaultRoleId = DB::table('condition_key')->insertGetId([
            'key_name' => 'Default',
            'key_description' => 'Default condition key with lowest permission',
            'modifier_key' => $defaultDetailsId,
            'date_created' => now(),
            'date_updated' => now(),
        ]);

        DB::table('condition_defaults')->insert(['key_id' => $defaultRoleId]);

        DB::table('subsystems')->insert([
            'subsystem_name' => 'Document Control System',
            'subsystem_version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'update_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subsystems');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('account_details');
        Schema::dropIfExists('account');
        Schema::dropIfExists('office');
        Schema::dropIfExists('condition_defaults');
        Schema::dropIfExists('condition_key');
        Schema::dropIfExists('condition_details');
    }
};
