<?php

namespace Sosupp\SlimerDesktop\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'model', 'model_id', 'action', 'payload', 'version',
        'transaction_type', 'source', 'synced_at', 'attempts', 'error',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];
}