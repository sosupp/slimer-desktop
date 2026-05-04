<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Sosupp\SlimerDesktop\Http\Controllers\Api\Traits\WithSyncDBOperation;
use Sosupp\SlimerDesktop\Http\Controllers\Tenant\TenantAwareController;
use Sosupp\SlimerDesktop\Models\Tenant\DeviceSyncCursor;
use Sosupp\SlimerDesktop\Models\Tenant\SyncDevice;
use Sosupp\SlimerTenancy\Models\Landlord\Tenant;

class LocalToRemoteController extends TenantAwareController
{
    use WithSyncDBOperation;

    public function push(Request $request)
    {
        // For non-tenant user of the app
        if(!config('slimertenancy.enabled')){
            $result = $this->syncAsDBV3($request);
            return response()->json(['status' => 'ok']);
        }

        $data = $request->logs;
        $payload = $data['payload'];
        $model = $data['model'];
        $table = $data['table'];
        $tenantKey = $request->tenant;

        // Check for tenant and it should be tenant aware
        $tenant = Tenant::where('subdomain', $tenantKey)->first();

        if (! $tenant) {
            return response()->json([
                'status' => 'tenant_not_found'
            ], 404);
        }

        $result = $this->inTenant($tenant, function() use($request){
            $this->syncAsDBV3($request);
        });

        if($result){
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

    public function registerDevice(Request $request)
    {
        $device = SyncDevice::query()->firstOrCreate(
            ['uid' => $request->uid],
            [
                'branch_id' => $request->branch_id,
                'branch_uid' => $request->branch_id,
                'name' => $request->name,
                'platform' => $request->platform,
            ]
        );

        if($device){
            DeviceSyncCursor::firstOrCreate([
                'sync_device_id' => $device->id,
            ]);

            return response()->json($device);
        }

        return response()->json([
            'message' => 'device not registered'
        ], 500);
    }

    public function single(Request $request, string $table)
    {
        $data = $request->all();
        $payload = $data['payload'];
        $model = $data['model'];

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
        // remove this method or review this code to reflect using uid as in syncAsDB

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

    protected function syncFlow($request)
    {
        // review this code to reflect using uid as in syncAsDB
        foreach ($request->logs as $log) {
            $modelClass = $log['model'];

            if ($log['action'] === 'deleted') {
                $modelClass::where('uid', $log['model_uid'])->delete();
                continue;
            }

            $model = $modelClass::find($log['model_uid']);

            if (!$model) {
                $modelClass::create($log['payload']);
                continue;
            }

            if (($log['version'] ?? 0) > ($model->version ?? 0)) {
                $model->update($log['payload']);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function syncAsDB(Request $request)
    {

        // $allowedTables = ['users', 'orders', 'products']; // adjust

        DB::transaction(function () use ($request) {

            foreach ($request->logs as $log) {

                $table = $log['table'];

                // if (!in_array($table, $allowedTables)) {
                //     continue;
                // }

                if ($log['action'] === 'deleted') {
                    DB::table($table)
                        ->where('uid', $log['model_uid'])
                        ->delete();
                    continue;
                }

                $model = DB::table($table)
                    ->where('uid', $log['model_uid'])
                    ->first();

                $payload = collect($log['payload'])
                    ->except(['id'])
                    ->merge([
                        'uid' => $log['model_uid']
                    ])
                    ->toArray();

                // 🔥 Resolve all *_ruid → *_id dynamically
                try {
                    // $modelClass = $log['model'];
                    // $payload = $this->eloquentResolveForeignKeys($payload, $modelClass);
                    $payload = $this->resolveForeignKeys($payload);
                } catch (\Exception $e) {
                    // Option 1: skip and retry later
                    Log::warning('Sync skipped due to missing dependency', [
                        'error' => $e->getMessage(),
                        'log' => $log
                    ]);
                    continue;
                }

                if (!$model) {
                    DB::table($table)->insert($payload);
                    continue;
                }

                // 🔥 Use version if available, fallback to updated_at
                $incomingVersion = $log['version'] ?? null;
                $currentVersion = $model->version ?? null;

                if (
                    ($incomingVersion && $incomingVersion > $currentVersion) ||
                    (!$incomingVersion && ($log['updated_at'] ?? null) > ($model->updated_at ?? null))
                ) {
                    DB::table($table)
                        ->where('uid', $log['model_uid'])
                        ->update($payload);
                }
            }
        });

        return response()->json(['status' => 'ok']);
    }

    public function syncAsDBV2(Request $request)
    {
        DB::transaction(function () use ($request) {

            // 🔥 Step 1: Build UID map
            $uidMap = $this->buildUidMap($request->logs);

            // 🔥 Step 2: Preload records
            $records = $this->preloadUidRecords($uidMap);

            foreach ($request->logs as $log) {

                $table = $log['table'];
                $modelClass = $log['model'];

                if ($log['action'] === 'deleted') {
                    DB::table($table)
                        ->where('uid', $log['model_uid'])
                        ->delete();
                    continue;
                }

                $model = DB::table($table)
                    ->where('uid', $log['model_uid'])
                    ->first();

                $payload = collect($log['payload'])
                    ->except(['id'])
                    ->merge(['uid' => $log['model_uid']])
                    ->toArray();

                // 🔥 Step 3: Resolve using preloaded data
                $payload = $this->resolveForeignKeysBulk($payload, $modelClass, $records);

                if (!$model) {
                    DB::table($table)->insert($payload);
                    continue;
                }

                $incomingVersion = $log['version'] ?? null;
                $currentVersion = $model->version ?? null;

                if (
                    ($incomingVersion && $incomingVersion > $currentVersion) ||
                    (!$incomingVersion && ($log['updated_at'] ?? null) > ($model->updated_at ?? null))
                ) {
                    DB::table($table)
                        ->where('uid', $log['model_uid'])
                        ->update($payload);
                }
            }
        });

        return response()->json(['status' => 'ok']);
    }

    protected function resolveForeignKeys(array $data)
    {
        foreach ($data as $key => $value) {

            // Detect *_ruid fields
            if (str_ends_with($key, '_ruid') && !empty($value)) {

                // product_ruid → product
                $relation = Str::beforeLast($key, '_ruid');

                $currentTable = $data['table'] ?? null;
                $relatedTable = config("slimerdesktop.syncs.table_relations.$currentTable.$relation")
                    ?? Str::plural(Str::snake($relation));

                // product → products
                $relatedTable = Str::plural(Str::snake($relation));

                $record = DB::table($relatedTable)
                    ->where('uid', $value)
                    ->first();

                if ($record) {
                    $data[$relation . '_id'] = $record->id;
                }

                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function eloquentResolveForeignKeys(array $data, string $modelClass)
    {
        $modelInstance = new $modelClass;

        $resolved = [];

        foreach ($data as $key => $value) {

            // Handle *_ruid
            if (str_ends_with($key, '_ruid') && !empty($value)) {

                $relation = Str::beforeLast($key, '_ruid');

                if (!method_exists($modelInstance, $relation)) {
                    continue;
                }

                $relationObj = $modelInstance->$relation();
                $relatedModel = $relationObj->getRelated();
                $relatedTable = $relatedModel->getTable();

                $record = DB::table($relatedTable)
                    ->where('uid', $value)
                    ->first();

                if ($record) {
                    $resolved[$relation . '_id'] = $record->id;
                } else {
                    Log::warning("Missing dependency: {$relation} ({$value})");
                }

                // ❌ DO NOT carry forward *_ruid
                continue;
            }

            // Keep all other fields
            $resolved[$key] = $value;
        }

        return $resolved;
    }

    protected function resolveForeignKeysBulk(array $payload, string $modelClass, array $records)
    {
        $modelInstance = new $modelClass;

        $resolved = [];

        foreach ($payload as $key => $value) {

            if (!str_ends_with($key, '_ruid') || empty($value)) {
                $resolved[$key] = $value;
                continue;
            }

            $relation = Str::beforeLast($key, '_ruid');

            $typeKey = $relation . '_type';

            /*
            |--------------------------------------------------------------------------
            | 🔥 Polymorphic
            |--------------------------------------------------------------------------
            */
            if (isset($payload[$typeKey])) {

                $modelType = $payload[$typeKey];

                $modelClassResolved = Relation::getMorphedModel($modelType)
                    ?? $modelType;

                if (!class_exists($modelClassResolved)) {
                    continue;
                }

                $table = (new $modelClassResolved)->getTable();

                $record = $records[$table][$value] ?? null;

                if ($record) {
                    $resolved[$relation . '_id'] = $record->id;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 🔥 Normal relation via Eloquent
            |--------------------------------------------------------------------------
            */
            if (method_exists($modelInstance, $relation)) {

                $relationObj = $modelInstance->$relation();
                $relatedTable = $relationObj->getRelated()->getTable();

                $record = $records[$relatedTable][$value] ?? null;

                if ($record) {
                    $resolved[$relation . '_id'] = $record->id;
                }

                continue;
            }

            // fallback: keep original
            // $resolved[$key] = $value;
            Log::warning("Unresolved relation skipped: {$key}");
            continue;
        }

        return $resolved;
    }




}
