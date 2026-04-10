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
        // check for internet connection

        // dd("we will process");

        $endpoint = config('slimerdesktop.api.base').'v1/desktop/local/pull';

        $response = Http::withToken(remoteSyncToken())
        ->get($endpoint, [
            'tenant' => null,
        ]);

        $logs = $response->json('logs');

        if (empty($logs)) {
            return;
        }

        //@todo Get and temp store the ids of logs to be sent back later to remote as synced records


        $this->syncAsDBV3(new Request([
            'logs' => $logs
        ]));

    }
}

