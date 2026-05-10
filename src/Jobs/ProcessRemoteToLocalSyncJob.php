<?php

namespace Sosupp\SlimerDesktop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\Settings;
use Sosupp\SlimerDesktop\Http\Controllers\Api\Traits\WithSyncDBOperation;

class ProcessRemoteToLocalSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, WithSyncDBOperation;

    public function handle(): void
    {
        //@todo Get and temp store the ids of logs to be sent back later to remote as synced records
        if(config('slimerdesktop.app.is_desktop')){
            $endpoint = config('slimerdesktop.api.base').'v1/desktop/local/pull';

            $deviceUid = Settings::get('slimer_desktop_device_uid');
            $branchUid = Settings::get('slimer_desktop_branch_uid');
            
            $response = Http::timeout(180)
            ->retry(3)
            ->withToken(remoteSyncToken())
            ->get($endpoint, [
                'tenant' => null,
                'device_uid' => $deviceUid,
                'branch_uid' => $branchUid,
            ]);

            $logs = collect($response->json('logs'))
            ->map(fn ($log) => [
                ...$log,
                // Check if it's already an array; if not, decode the string
                'payload' => is_array($log['payload'])
                    ? $log['payload']
                    : (json_decode($log['payload'], true) ?? []),
            ]);

            if ($logs->isEmpty()) {
                Log::info("log from remote is empty");
                return;
            }

            $this->syncAsDBV3(new Request([
                'logs' => $logs->toArray()
            ]));

            // after local sync success send ack to remote
            $lastProcessedLogId = $logs->last()['id'];

            Log::info("last id", [$lastProcessedLogId]);

            $response = Http::withToken(remoteSyncToken())
            ->timeout(180)
            ->retry(2)
            ->post(
                config('slimerdesktop.api.base') . "v1/desktop/local/ack/remote",
                [
                    'tenant' => null,
                    'device_uid' => $deviceUid,
                    'last_processed_log_id' => $lastProcessedLogId,
                ]
            );

            Log::info('ack res', [$response->getStatusCode(), $response->body()]);
        }


    }
}

