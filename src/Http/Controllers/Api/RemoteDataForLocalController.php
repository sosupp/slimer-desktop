<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Sosupp\SlimerDesktop\Http\Controllers\Tenant\TenantAwareController;
use Sosupp\SlimerDesktop\Models\Tenant\SyncDevice;
use Sosupp\SlimerDesktop\Models\Tenant\SyncLog;
use Sosupp\SlimerTenancy\Models\Landlord\Tenant;

class RemoteDataForLocalController extends TenantAwareController
{
    public function pull(Request $request)
    {
        // For non-tenant user of the app
        if(!config('slimertenancy.enabled')){
            return $this->getSyncRecordsV2($request);
        }

        $data = $request->logs;
        $tenantKey = $request->tenant;

        // Check for tenant and it should be tenant aware
        $tenant = Tenant::where('subdomain', $tenantKey)->first();

        if (! $tenant) {
            return response()->json([
                'status' => 'tenant_not_found'
            ], 404);
        }

        $result = $this->inTenant($tenant, function() use($request){
            return $this->getSyncRecordsV2($request);
        });

        if($result){
            return $result;
        }

        return response()->json([
            'message' => "failed to get sync records",
            'status' => 'sync_pull_failed'
        ], 500);
    }

    public function acknowledge(Request $request)
    {
        $device = SyncDevice::where('uid', $request->device_uid)->firstOrFail();

        $device->cursor()->update([
            'last_synced_log_id' => $request->last_processed_log_id,
            'last_synced_at' => now()
        ]);

        return response()->json([
            'message' => 'sync acknowledged'
        ]);
    }

    protected function getSyncRecords()
    {
        $logs = DB::table('sync_logs')
        ->where('source', 'remote')
        ->whereNull('synced_at')
        ->orderBy('id') // important
        ->limit(500)
        ->get();

        // this could have been done after local updates and sends another api request.
        // for now we immediately mark the selected logs as synced
        DB::table('sync_logs')
        ->whereIn('id', $logs->pluck('id'))
        ->update([
            'synced_at' => now(),
            'status' => 'synced'
        ]);

        return response()->json([
            'logs' => $logs
        ]);

    }

    protected function getSyncRecordsV2(Request $request)
    {
        $device = SyncDevice::query()->where('uid', $request->device_uid)
        ->firstOrFail();

        $cursor = $device->cursor;

        $logs = SyncLog::query()
        ->where('id', '>', $cursor->last_synced_log_id)
        ->where(function ($query) use ($device) {
            $query->whereNull('origin_device_id')
            ->orWhere('origin_device_id', '!=', $device->id);
        })
        ->orderBy('id')
        ->limit(200)
        ->get();

        return response()->json([
            'logs' => $logs
        ]);
    }

}
