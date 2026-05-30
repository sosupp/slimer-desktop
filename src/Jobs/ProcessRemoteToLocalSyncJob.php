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
use Sosupp\SlimerDesktop\Events\RequiresTableSnapshot;
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

            // do we have last processed log id still pending
            $lastProcessedLogId = session('lastProcessedLogId');

            if($lastProcessedLogId){
                $this->sendAck($deviceUid, $lastProcessedLogId);
            }else{

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
                    // @todo Dispatch another job so user can listen and implement what need to be done
                    $data = $response->json();
                    Log::info("empty remote log", [
                        'data' => $data,
                    ]);

                    if(isset($data['current_sync_id'])){
                        RequiresTableSnapshot::dispatch(
                            $data['current_sync_id'],
                            $data['message'],
                        );

                        session(['lastSyncId' => $data['current_sync_id']]);
                    }

                    return;
                }

                $this->syncAsDBV3(new Request([
                    'logs' => $logs->toArray()
                ]));

                // after local sync success send ack to remote
                $lastProcessedLogId = $logs->last()['id'];

                // put this in section to reference in case api request timeout or failed
                session(['lastProcessedLogId' => $lastProcessedLogId]);

                Log::info("last id", [$lastProcessedLogId]);

                $this->sendAck($deviceUid, $lastProcessedLogId);
            }

        }
    }

    private function sendAck(string|int $deviceUid, string|int $lastProcessedLogId)
    {
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

        if($response->successful()){
            session()->forget('lastProcessedLogId');
        }

        return $response->getStatusCode();
    }
}

