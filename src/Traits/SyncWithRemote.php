<?php
namespace Sosupp\SlimerDesktop\Traits;

use Illuminate\Support\Facades\Log;
use Sosupp\SlimerDesktop\Events\Remote\UpdateRemoteTable;
use Sosupp\SlimerDesktop\Jobs\ProcessSyncLogsJob;
use Sosupp\SlimerDesktop\Services\SyncContext;
use Sosupp\SlimerDesktop\Services\SyncLogger;

trait SyncWithRemote
{
    use HasChannel, HasUid;

    public $skipSync = false;

    public static function bootSyncWithRemote()
    {
        if(config('slimerdestop.syncs.bidirection') === false){
            return;
        }
        
        static::created(function ($model) {
            $model->logSync('created');
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

            $model->logSync('updated');
            // $model->dispatchSync();
        });

        static::deleted(function ($model) {
            if ($model->skipSync) return;
            $model->logSync('deleted');
        });
    }

    public function logSync(string $action)
    {
        $data = [
            'model' => get_class($this),
            'model_id' => $this->getKey(),
            'uid' => $this->uid ?? null,
            'action' => $action,
            'payload' => $this->getAttributes(),
            'version' => $this->version ?? 1,
        ];

        if (!SyncContext::isEnabled()) {
            SyncContext::addToBuffer($data);
            return;
        }

        SyncLogger::store($data);
        ProcessSyncLogsJob::dispatch();
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
