<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Api;

use App\Http\Controllers\TenantAwareController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Sosupp\SlimerTenancy\Models\Landlord\Tenant;

class LocalToRemoteController extends TenantAwareController
{
    public function single(Request $request, string $table)
    {
        $data = $request->all();
        $payload = $data['payload'];
        $model = $data['model'];

        Log::info("payload", [
            'tenant' => $data['tenant'],
            'table' => $table,
            'model' => $model,
            'data' => $payload
        ]);

        // For non-tenant user of the app
        if(!config('slimertenancy.enabled')){
            return $this->updateFlow($table, $payload);
        }

        // Check for tenant and it should be tenant aware
        $tenant = Tenant::where('subdomain', $data['tenant'])->first();

        if (! $tenant) {
            return response()->json([
                'status' => 'tenant_not_found'
            ], 404);
        }

        $result = $this->inTenant($tenant, function() use($table, $payload){
            $this->updateFlow($table, $payload); 
        });

        if($result){
            Log::info("{$table} record synced", ['id' => $payload['uid']]);

            return response()->json([
                'message' => "{$table} Record synced successfully",
                'uid' => $payload['uid'],
                'status' => 'success'
            ]);
        }

        return response()->json([
            'message' => "{$table} record failed to sync",
            'uid' => $payload['uid'],
            'status' => 'sync_failed'
        ], 500);

    }

    protected function updateFlow(string $table, $payload): string
    {
        // 1️⃣ Validate that the table exists
            if (!Schema::hasTable($table)) {
                return response()->json([
                    'message' => "Unknown table: {$table}",
                    'uid' => $payload['uid'],
                    'status' => 'unknown_table'
                ], 404);
            }

            // 2️⃣ Find and Detect conflict first
            $record = DB::table($table)
            ->where('uid', $payload['uid'])
            ->first();

            if ($record && isset($payload['updated_at'])) {
                $localTime = Carbon::parse($payload['updated_at']);
                if ($localTime->lessThan($record->updated_at)) {
                    // @todo Register into a conflicts table providing json data for local and remote
                    return response()->json([
                        'message' => 'Skipped: remote record newer than local update',
                        'uid' => $payload['uid'],
                        'status' => 'conflict',
                    ], 409);
                }
            }

            //  3️⃣ We use DB::table for two reasons here: prevent this record going back
            // to the record_channels and not all tables will have models.
            return DB::table("{$table}")
            ->updateOrInsert(
                [
                    'uid' => $payload['uid']
                ],
                $payload
            );
    }

}
