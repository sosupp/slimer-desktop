<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Sosupp\SlimerDesktop\Http\Controllers\Tenant\TenantAwareController;
use Sosupp\SlimerTenancy\Models\Landlord\Tenant;

class RemoteDataForLocalController extends TenantAwareController
{
    public function pull(Request $request)
    {
        // For non-tenant user of the app
        if(!config('slimertenancy.enabled')){
            return $this->getSyncRecords();
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
            return $this->getSyncRecords();
        });

        if($result){
            return $result;
        }

        return response()->json([
            'message' => "failed to get sync records",
            'status' => 'sync_pull_failed'
        ], 500);
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
        ->update(['synced_at' => now()]);

        return response()->json([
            'logs' => $logs
        ]);

    }

}
