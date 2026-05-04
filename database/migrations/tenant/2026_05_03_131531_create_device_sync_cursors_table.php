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
        if(!Schema::hasTable('device_sync_cursors')){
            Schema::create('device_sync_cursors', function (Blueprint $table) {
                $table->id();

                $table->foreignId('sync_device_id');
                $table->string('sync_device_uid')->nullable();

                $table->unsignedBigInteger('last_synced_log_id')->default(0);

                $table->timestamp('last_synced_at')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_sync_cursors');
    }
};
