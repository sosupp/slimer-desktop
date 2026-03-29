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
        if(!Schema::hasTable('sync_logs')){
            Schema::create('sync_logs', function (Blueprint $table) {
                $table->id();
                $table->string('model');
                $table->unsignedBigInteger('model_id')->nullable();
                $table->uuid('model_uid')->nullable();
                $table->string('action');
                $table->json('payload');
                $table->unsignedInteger('version')->default(1);
                $table->string('transaction_type')->nullable();
                $table->string('source')->default('local');
                $table->integer('attempts')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->enum('status', [
                    'pending', 'syncing', 'synced', 'failed'
                ])->default('pending');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
