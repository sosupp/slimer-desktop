<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if(!Schema::hasTable('sync_devices')){
            Schema::create('sync_devices', function (Blueprint $table) {
                $table->id();

                $table->foreignId('branch_id');
                $table->string('branch_uid')->unique()->nullable(); // unique device identifier

                $table->string('uid')->unique(); // unique device identifier
                $table->string('name')->nullable();

                $table->string('platform')->nullable(); // desktop/mobile/tablet
                $table->timestamp('last_seen_at')->nullable();

                $table->boolean('is_active')->default(true);

                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_devices');
    }
};
