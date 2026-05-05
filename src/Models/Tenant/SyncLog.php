<?php

namespace Sosupp\SlimerDesktop\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'model', 'model_id', 'action', 'payload', 'version',
        'transaction_type', 'source', 'synced_at', 'attempts', 'error',
        'status', 'tenant_key', 'model_uid', 'table', 'origin_branch_uid',
        'origin_device_uid',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];
}