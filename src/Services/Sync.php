<?php
namespace Sosupp\SlimerDesktop\Services;

use Illuminate\Support\Facades\DB;
use Sosupp\SlimerDesktop\Jobs\ProcessSyncLogsJob;

class Sync
{
    public static function batch(string $type, callable $callback)
    {
        SyncContext::disable();
        
        try {
            DB::beginTransaction();

            $callback();

            $logs = SyncContext::flush();
            DB::commit();

            DB::afterCommit(function () use ($logs, $type) {
                foreach ($logs as $log) {
                    $log['transaction_type'] = $type;
                    SyncLogger::store($log);
                }

                ProcessSyncLogsJob::dispatch();
            });

        } catch (\Throwable $e) {
            DB::rollBack();
            SyncContext::enable();
            throw $e;
        }

        SyncContext::enable();
    }

    public static function pivot(string $model, array $payload)
    {
        $payload['model'] = $model;
        $payload['action'] = 'attached';
        SyncLogger::store($payload);
    }
}

