<?php

namespace Sosupp\SlimerDesktop\Listeners\Remote;

use DateTime;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sosupp\SlimerDesktop\Events\Remote\UpdateRemoteTable;
use Sosupp\SlimerDesktop\Models\Tenant\RecordChannel;

class RemoteTableListener implements ShouldHandleEventsAfterCommit, ShouldDispatchAfterCommit, ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 4;
    /**
     * Create the event listener.
     */
    public function __construct()
    {

    }


    /**
     * Handle the event.
     */
    public function handle(UpdateRemoteTable $event): void
    {
        $channel = RecordChannel::find($event->channelId);

        if (! $channel) {
            Log::error('Channel record not found for sync', [
                'channel_id' => $event->channelId,
                'model' => $event->model,
            ]);
            return;
        }

        if ($channel->sync_status === 'synced') return;

        try {
            $response = $this->handleWithApiEndpoint($event);

            // if error or not_found revert of update record_channel status
            if(!$response->successful()){
                // log error - info developer
                throw new \Exception($response->body());
            }

            $feedback = $response->json();
            $channel->markSynced();

        } catch (\Throwable $e) {
            $channel->markFailed($e->getMessage());
            throw $e; // let queue retry
        }


    }

    public function retryUnitl(): DateTime
    {
        return now()->addMinutes(3);
    }

    private function handleWithApiEndpoint($event)
    {
        $url = config('slimerdesktop.api.base')
            . "local/to/remote/sync/{$event->table}";

        $payload = [
            'model' => $event->model,
            'payload' => $event->payload,
            'tenant' => $event->tenant,
        ];

        $token = remoteSyncToken();
        Log::info('token', [$token]);

        return Http::withToken($token)
        ->post($url, $payload);

    }


}
