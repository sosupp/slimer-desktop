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
        // check for internet connection

        // dd("we will process");

        $logs = SyncLog::whereNull('synced_at')
        ->where('source', 'local')
        ->limit(100)
        ->get();

        if ($logs->isEmpty()) return;

        $endpoint = config('slimerdesktop.api.base').'v1/desktop/local/push';
        // $endpoint = config('slimerdesktop.api.base').'v1/desktop/sync/db/push';
        // $endpoint = config('slimerdesktop.api.base').'v1/desktop/sync/push';
        $response = Http::withToken(remoteSyncToken())
        ->post($endpoint, [
            'logs' => $logs->map(fn($log) => $log->toArray()),
            'tenant' => null,
        ]);

        if ($response->successful()) {
            SyncLog::whereIn('id', $logs->pluck('id'))
            ->update([
                'synced_at' => now(),
                'status' => 'synced',
            ]);
        }
    }
}

