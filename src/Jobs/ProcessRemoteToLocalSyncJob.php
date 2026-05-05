<?php

namespace Sosupp\SlimerDesktop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Sosupp\SlimerDesktop\Http\Controllers\Api\Traits\WithSyncDBOperation;

class ProcessRemoteToLocalSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, WithSyncDBOperation;

    public function handle(): void
    {
        //@todo Get and temp store the ids of logs to be sent back later to remote as synced records
        if(config('slimerdesktop.app.is_desktop')){
            $endpoint = config('slimerdesktop.api.base').'v1/desktop/local/pull';
    
            $response = Http::withToken(remoteSyncToken())
            ->get($endpoint, [
                'tenant' => null,
                'device_uid' => config('slimerdesktop.app.device_uid'),
            ]);
    
            $logs = $response->json('logs');
    
            $logs = collect($logs)->map(fn ($log) => [
                ...$log,
                // Check if it's already an array; if not, decode the string
                'payload' => is_array($log['payload']) 
                    ? $log['payload'] 
                    : (json_decode($log['payload'], true) ?? []),
            ])->toArray();
    
            if (empty($logs)) {
                return;
            }
    
            $this->syncAsDBV3(new Request([
                'logs' => $logs
            ]));

            $logs = collect($response->json('logs'))
            ->map(fn ($log) => [
                ...$log,
                // Check if it's already an array; if not, decode the string
                'payload' => is_array($log['payload']) 
                    ? $log['payload'] 
                    : (json_decode($log['payload'], true) ?? []),
            ]);

            if ($logs->isEmpty()) {
                return;
            }

            $this->syncAsDBV3(new Request([
                'logs' => $logs->toArray()
            ]));

            // after local sync success send ack to remote
            $lastProcessedLogId = $logs->last()['id'];

            Http::withToken(remoteSyncToken())
            ->post(
                config('slimerdesktop.api.base') . "v1/desktop/local/ack/remote",
                [
                    'tenant' => null,
                    'device_uid' => config('slimerdesktop.app.device_uid'),
                    'last_processed_log_id' => $lastProcessedLogId,
                ]
            );
        }


    }
}

