<?php
namespace Sosupp\SlimerDesktop\Traits;

use Illuminate\Support\Facades\Log;
use Sosupp\SlimerDesktop\Events\Remote\UpdateRemoteTable;
use Sosupp\SlimerDesktop\Interfaces\BranchAware;
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
        $resolvedBranchUid = $this->resolveBranchUid();

        $data = [
            'model' => get_class($this),
            'table' => $this->getTable(),
            'model_id' => $this->getKey(),
            'model_uid' => $this->uid ?? null,
            'action' => $action,
            'payload' => $this->getAttributes(),
            'tenant_key' => config('slimerdesktop.tenant.key'),
            'source' => config('slimerdesktop.app.channel') ?? 'local',

            'branch_uid' => $resolvedBranchUid,
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

    public function branchUid(): ?string
    {
        return $this->resolveBranchUid();
    }

    protected function resolveBranchUid(): ?string
    {
        // Case 1: Explicit model-defined logic wins
        if ($this instanceof BranchAware) {
            return $this->getBranchUid();
        }

        // Case 2: direct branch uid column
        if (isset($this->branch_uid)) {
            return $this->branch_uid ?? null;
        }

        // Case 3: direct branch id column
        if (isset($this->branch_id)) {
            return $this->resolveBranchUidFromId($this->branch_id);
        }

        // Case 4: direct branch relationship
        if (method_exists($this, 'branch')) {
            try {
                return optional($this->branch)->uid ?? optional($this->branch)->id;
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Case 5: fallback → global record
        return null;
    }

    protected function resolveBranchUidFromId(string|int $branchId): ?string
    {
        if (!$branchId) {
            return null;
        }

        static $branchCache = [];

        if (isset($branchCache[$branchId])) {
            return $branchCache[$branchId];
        }

        $branchModel = config('slimerdesktop.models.branch');

        if (!$branchModel) {
            return null;
        }

        $branch = $branchModel::query()
            ->select('id', 'uid')
            ->find($branchId);

        if (!$branch) {
            return null;
        }

        return $branchCache[$branchId] = $branch->uid;
    }

}
