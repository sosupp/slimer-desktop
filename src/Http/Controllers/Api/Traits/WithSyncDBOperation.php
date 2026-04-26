<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Api\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

trait WithSyncDBOperation
{
    public function syncAsDBV3(Request $request)
    {

        DB::transaction(function () use ($request) {

            /*
            |--------------------------------------------------------------------------
            | STEP 1: Preload existing DB records (UID to ID)
            |--------------------------------------------------------------------------
            */
            $uidMap = $this->buildUidMap($request->logs);
            $records = $this->preloadUidRecords($uidMap);

            Log::info('before', [$uidMap, $records]);

            // Normalize to simple array: [table][uid] => id
            $registry = [];

            foreach ($records as $table => $rows) {
                foreach ($rows as $uid => $row) {
                    $registry[$table][$uid] = $row->id;
                }
            }

            Log::info('registry', [$registry]);

            /*
            |--------------------------------------------------------------------------
            | STEP 2: FIRST PASS (Insert/Update WITHOUT relations)
            |--------------------------------------------------------------------------
            */
            foreach ($request->logs as $log) {
                $table = $log['table'];

                if ($log['action'] === 'deleted') {
                    DB::table($table)->where('uid', $log['model_uid'])->delete();
                    continue;
                }

                $payload = collect($log['payload'])
                    ->except(['id'])
                    ->merge(['uid' => $log['model_uid']])
                    ->toArray();

                // Generic Resolution: Try to fill ID columns if RUID is already known
                foreach ($payload as $key => $value) {
                    if (str_ends_with($key, '_ruid') && !empty($value)) {
                        $relationName = Str::beforeLast($key, '_ruid'); // e.g. "branch_id" or "author"
                        Log::info('relationName', [$relationName]);

                        // 1. Determine the target table for this relation
                        // We can look through the registry to find which table contains this UID
                        foreach ($registry as $targetTable => $uids) {
                            if (isset($uids[$value])) {


                                // 2. Map back to the actual DB column name
                                // If the key was 'branch_id_ruid', the column is 'branch_id'
                                // If the key was 'author_ruid', the column is usually 'author_id'
                                $dbColumn = str_ends_with($relationName, '_id')
                                    ? $relationName
                                    : $relationName . '_id';

                                $payload[$dbColumn] = $uids[$value];

                                Log::info('registry lookup', [
                                    'target table' => $targetTable,
                                    'uids' => $uids,
                                    'uid value' => $uids[$value],
                                    'db col' => $dbColumn,
                                    'payload' => $payload
                                ]);
                                break;
                            }
                        }
                    }
                }

                // Now remove all *_ruid keys so they don't crash the DB insert
                $cleanPayload = collect($payload)
                    ->reject(fn ($v, $k) => str_ends_with($k, '_ruid'))
                    ->toArray();

                Log::info('before insert', [
                    'payload' => $payload,
                    'clean payload' => $cleanPayload,
                    'the log' => $log,
                ]);

                DB::table($table)->updateOrInsert(
                    ['uid' => $log['model_uid']],
                    $cleanPayload
                );

                // Register the ID for future logs in the same request
                $id = DB::table($table)->where('uid', $log['model_uid'])->value('id');
                $registry[$table][$log['model_uid']] = $id;
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 3: SECOND PASS (Resolve relations)
            |--------------------------------------------------------------------------
            */
             foreach ($request->logs as $log) {

                if ($log['action'] === 'deleted') {
                    continue;
                }

                $table = $log['table'];
                $modelClass = $log['model'];

                $payload = collect($log['payload'])
                    ->merge(['uid' => $log['model_uid']])
                    ->toArray();

                $resolved = $this->resolveForeignKeysFromRegistry(
                    $payload,
                    $modelClass,
                    $registry
                );

                if (empty($resolved)) {
                    continue;
                }

                DB::table($table)
                    ->where('uid', $log['model_uid'])
                    ->update($resolved);
            }
        });

        return response()->json(['status' => 'ok']);
    }

    protected function buildUidMap(array $logs): array
    {
        $uidMap = [];

        Log::info('before uidmap', [$logs]);

        foreach ($logs as $log) {

            $modelClass = $log['model'];
            $payload = $log['payload'] ?? [];

            foreach ($payload as $key => $value) {

                Log::info('check', [$key, $value]);

                if (!str_ends_with($key, '_ruid') || empty($value)) {
                    continue;
                }

                $relation = Str::beforeLast($key, '_ruid');

                // Extract and convert to camelCase (e.g., platform_admin -> platformAdmin)
                $relation = Str::camel(Str::beforeLast($key, '_ruid'));

                // Add this logic
                if (str_ends_with($relation, '_id')) {
                    $relation = Str::beforeLast($relation, '_id');
                }

                $typeKey = Str::snake($relation) . '_type';

                Log::info('relation and type key', [$relation, $typeKey]);

                /*
                |--------------------------------------------------------------------------
                | Polymorphic
                |--------------------------------------------------------------------------
                */
                if (isset($payload[$typeKey])) {

                    Log::info('yes type key', [$payload[$typeKey]]);

                    $modelType = $payload[$typeKey];

                    $modelClassResolved = Relation::getMorphedModel($modelType)
                        ?? $modelType;

                    if (!class_exists($modelClassResolved)) {
                        continue;
                    }

                    $table = (new $modelClassResolved)->getTable();

                    $uidMap[$table][] = $value;

                    Log::info('has uid', [$uidMap]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Normal (use Eloquent relation)
                |--------------------------------------------------------------------------
                */
                $modelInstance = new $modelClass;

                Log::info('use eloquent rel', [$modelInstance]);

                // $relation = 'platformAdmins';

                if (!method_exists($modelInstance, $relation)) {
                    Log::info('model rel method not', [$modelInstance]);
                    continue;
                }

                $relationObj = $modelInstance->$relation();
                $relatedTable = $relationObj->getRelated()->getTable();

                $uidMap[$relatedTable][] = $value;

                Log::info('uid after el', [$relationObj, $relatedTable, $uidMap]);
            }
        }

        // remove duplicates
        foreach ($uidMap as $table => $uids) {
            $uidMap[$table] = array_unique($uids);
        }

        Log::info('after uidmap', $uidMap);

        return $uidMap;
    }

    protected function preloadUidRecords(array $uidMap): array
    {
        $records = [];

        foreach ($uidMap as $table => $uids) {
            Log::info('table n uid', [$table, $uids]);

            $rows = DB::table($table)
                ->whereIn('uid', $uids)
                ->get()
                ->keyBy('uid');

            $records[$table] = $rows;
        }


        return $records;
    }

    protected function resolveForeignKeysFromRegistry(array $payload, string $modelClass, array $registry)
    {
        $modelInstance = new $modelClass;

        Log::info('mode instance', [$modelInstance]);

        $resolved = [];

        foreach ($payload as $key => $value) {

            if (!str_ends_with($key, '_ruid') || empty($value)) {
                continue;
            }

            $relation = Str::beforeLast($key, '_ruid');
            $typeKey = $relation . '_type';

            /*
            |--------------------------------------------------------------------------
            | POLYMORPHIC
            |--------------------------------------------------------------------------
            */
            if (isset($payload[$typeKey])) {

                $modelType = $payload[$typeKey];

                $modelClassResolved = Relation::getMorphedModel($modelType)
                    ?? $modelType;

                if (!class_exists($modelClassResolved)) {
                    continue;
                }

                $relatedTable = (new $modelClassResolved)->getTable();

                $id = $registry[$relatedTable][$value] ?? null;

                if ($id) {
                    $resolved[$relation . '_id'] = $id;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | NORMAL RELATION (Eloquent)
            |--------------------------------------------------------------------------
            */
            if (method_exists($modelInstance, $relation)) {
                Log::info('mo exist', [$modelInstance, $relation]);

                $relationObj = $modelInstance->$relation();
                $relatedTable = $relationObj->getRelated()->getTable();

                $id = $registry[$relatedTable][$value] ?? null;

                if ($id) {
                    $resolved[$relation . '_id'] = $id;
                }

                continue;
            }

            // Optional fallback (safe guess)
            $relatedTable = Str::plural(Str::snake($relation));
            $id = $registry[$relatedTable][$value] ?? null;

            if ($id) {
                $resolved[$relation . '_id'] = $id;
            }
        }

        Log::info('resolver', [
            'payload' => $payload,
            'resolved' => $resolved,
        ]);

        return $resolved;
    }

}
