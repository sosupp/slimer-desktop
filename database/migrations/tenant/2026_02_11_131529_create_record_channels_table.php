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
        if(!Schema::hasTable('record_channels')){
            Schema::create('record_channels', function (Blueprint $table) {
                $table->id();
                $table->morphs('record'); // record_id, record_type
                $table->string('channel')->default('remote');
    
                $table->enum('sync_status', [
                    'pending', 'syncing', 'synced', 'failed'
                ])->default('pending');
    
                $table->timestamp('synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
    
                $table->unique(['record_id', 'record_type'], 'record_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_channels');
    }
};
