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
            ]);
    
            $logs = $response->json('logs');
    
            $logs = collect($logs)->map(fn ($log) => [
                ...$log,
                'payload' => json_decode($log['payload'], true) ?? [],
            ])->toArray();
    
            if (empty($logs)) {
                return;
            }
    
            $this->syncAsDBV3(new Request([
                'logs' => $logs
            ]));
        }


    }
}

