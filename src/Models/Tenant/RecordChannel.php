<?php

namespace Sosupp\SlimerDesktop\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class RecordChannel extends Model
{
    protected $fillable = [
        'channel', 'sync_status', 'synced_at',
        'last_error', 'record_id', 'record_type',
    ];

    public function record()
    {
        return $this->morphTo();
    }

    // Custom
    public function markSynced(): void
    {
        $this->update([
            'sync_status' => 'synced',
            'synced_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markSyncing(): void
    {
        $this->update(['sync_status' => 'syncing']);
    }

    public function markFailed(?string $error = null): void
    {
        $this->update([
            'sync_status' => 'failed',
            'last_error' => $error,
        ]);
    }

    public function resetSync(): void
    {
        $this->update([
            'sync_status' => 'pending',
            'synced_at' => null,
            'last_error' => null,
        ]);
    }

}
