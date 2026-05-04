<?php

namespace Sosupp\SlimerDesktop\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceSyncCursor extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_device_id', 'last_synced_log_id', 'last_synced_at',
        'sync_device_uid',
    ];

    public function device()
    {
        return $this->belongsTo(SyncDevice::class);
    }
}