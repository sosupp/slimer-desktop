<?php

namespace Sosupp\SlimerDesktop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Sosupp\SlimerDesktop\Models\Tenant\SyncLog;

class ProcessSyncLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $logs = SyncLog::whereNull('synced_at')->limit(100)->get();

        if ($logs->isEmpty()) return;

        $response = Http::post(config('slimerdesktop.api.base'), [
            'logs' => $logs->map(fn($log) => $log->toArray())
        ]);

        if ($response->successful()) {
            SyncLog::whereIn('id', $logs->pluck('id'))
            ->update(['synced_at' => now()]);
        }
    }
}

