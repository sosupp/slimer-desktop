<?php
namespace Sosupp\SlimerDesktop\Traits;

use Illuminate\Support\Facades\Log;
use Sosupp\SlimerDesktop\Events\Remote\UpdateRemoteTable;

trait SyncWithRemote
{
    use HasChannel, HasUid;

    public $skipSync = false;

    public static function bootSyncWithRemote()
    {
        static::creating(function ($model) {
            $model->forceFill(['channel' => config('slimerdesktop.app.channel')]);
        });

        static::created(function ($model) {
            // Make 100% sure ID exists
            if (!$model->getKey()) {
                $model->refresh();
            }

            // Perform HasChannel logic first
            // Add channel to table: Only create if one doesn’t exist
            if (!$model->channelRecord()->exists()) {
                $model->channelRecord()->create([
                    'channel' => config('slimerdesktop.app.channel'),
                ]);
            }

            $model->dispatchSync();
        });

        // For bi-directional sync this should not be limited to local only
        static::updated(function ($model) {
            // Avoid updates as a result of pivot actions
            if($model->skipSync){
                return;
            }

            // When record changes locally, mark it for re-sync
            if (config('slimerdesktop.app.channel') === 'local') {
                $model->channelRecord?->resetSync();
            }

            $model->dispatchSync();
        });
    }

    public function dispatchSync()
    {
        // Only sync if we’re in local mode
        if (config('slimerdesktop.app.channel') !== 'local') return;

        Log::info('sync call', [
            'records' => $this->getAttributes(),
        ]);

        event(new UpdateRemoteTable(
            class_basename($this),
            $this->getTable(),
            $this->getAttributes(),
            config('slimerdesktop.tenant.key'),
            $this->channelRecord->id,
        ));
    }


}
