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
        // Currently only sync if we’re in local mode
        if (!shouldSync()) return;

        static::created(function ($model) {
            $model->logSync('created');
        });

        // For bi-directional sync this should not be limited to local only
        static::updated(function ($model) {
            if ($model->skipSync) return;
            $model->logSync('updated');
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
            'table' => $this->getTable(),
            'model_id' => $this->getKey(),
            'model_uid' => $this->uid ?? null,
            'action' => $action,
            'payload' => $this->getAttributes(),
            'tenant_key' => config('slimerdesktop.tenant.key'),
            'source' => config('slimerdesktop.app.channel') ?? 'local',

            'origin_branch_uid' => config('slimerdesktop.app.branch_uid') ?? null,
            'origin_device_uid' => config('slimerdesktop.app.device_uid') ?? null,
        ];

        if (!SyncContext::isEnabled()) {
            SyncContext::addToBuffer($data);
            return;
        }

        SyncLogger::store($data);

        if(config('slimerdesktop.app.is_desktop')){
            ProcessSyncLogsJob::dispatch();
        }
    }

    public function dispatchSync()
    {
        // Only sync if we’re in local mode
        if (!shouldSync()) return;

        event(new UpdateRemoteTable(
            class_basename($this),
            $this->getTable(),
            $this->getAttributes(),
            config('slimerdesktop.tenant.key'),
            $this->channelRecord->id,
        ));
    }


}
