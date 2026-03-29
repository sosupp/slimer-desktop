<?php
namespace Sosupp\SlimerDesktop\Services;

use Sosupp\SlimerDesktop\Models\Tenant\SyncLog;


class SyncLogger
{
    public static function store(array $data): SyncLog
    {
        return SyncLog::create([
            'model' => $data['model'],
            'model_id' => $data['model_id'] ?? null,
            'action' => $data['action'],
            'payload' => $data['payload'],
            'version' => $data['version'] ?? 1,
            'transaction_type' => $data['transaction_type'] ?? null,
            'source' => $data['source'] ?? 'local',
        ]);
    }
}