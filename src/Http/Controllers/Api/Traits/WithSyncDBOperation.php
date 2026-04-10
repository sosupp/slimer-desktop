<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Api\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait WithSyncDBOperation
{
    public function syncAsDBV3(Request $request)
    {

        DB::transaction(function () use ($request) {

            /*
            |--------------------------------------------------------------------------
            | 🔥 STEP 1: Preload existing DB records (UID → ID)
            |--------------------------------------------------------------------------
            */
            $uidMap = $this->buildUidMap($request->logs);
            $records = $this->preloadUidRecords($uidMap);

            // Normalize to simple array: [table][uid] => id
            $registry = [];

            foreach ($records as $table => $rows) {
                foreach ($rows as $uid => $row) {
                    $registry[$table][$uid] = $row->id;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 🔥 STEP 2: FIRST PASS (Insert/Update WITHOUT relations)
            |--------------------------------------------------------------------------
            */
            foreach ($request->logs as $log) {

                $table = $log['table'];

                if ($log['action'] === 'deleted') {
                    DB::table($table)
                        ->where('uid', $log['model_uid'])
                        ->delete();
                    continue;
                }

                $payload = collect($log['payload'])
                    ->except(['id'])
                    ->merge(['uid' => $log['model_uid']])
                    ->toArray();

                // ❌ Remove all *_ruid before insert
                $payload = collect($payload)
                    ->reject(fn ($v, $k) => str_ends_with($k, '_ruid'))
                    ->toArray();

                DB::table($table)->updateOrInsert(
                    ['uid' => $log['model_uid']],
                    $payload
                );

                // 🔥 Immediately fetch ID and register it
                $id = DB::table($table)
                    ->where('uid', $log['model_uid'])
                    ->value('id');

                $registry[$table][$log['model_uid']] = $id;
            }

            /*
            |--------------------------------------------------------------------------
            | 🔥 STEP 3: SECOND PASS (Resolve relations)
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

        foreach ($logs as $log) {

            $modelClass = $log['model'];
            $payload = $log['payload'] ?? [];

            foreach ($payload as $key => $value) {

                if (!str_ends_with($key, '_ruid') || empty($value)) {
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

                    $uidMap[$table][] = $value;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | 🔥 Normal (use Eloquent relation)
                |--------------------------------------------------------------------------
                */
                $modelInstance = new $modelClass;

                if (!method_exists($modelInstance, $relation)) {
                    continue;
                }

                $relationObj = $modelInstance->$relation();
                $relatedTable = $relationObj->getRelated()->getTable();

                $uidMap[$relatedTable][] = $value;
            }
        }

        // remove duplicates
        foreach ($uidMap as $table => $uids) {
            $uidMap[$table] = array_unique($uids);
        }

        return $uidMap;
    }

    protected function preloadUidRecords(array $uidMap): array
    {
        $records = [];

        foreach ($uidMap as $table => $uids) {

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

        $resolved = [];

        foreach ($payload as $key => $value) {

            if (!str_ends_with($key, '_ruid') || empty($value)) {
                continue;
            }

            $relation = Str::beforeLast($key, '_ruid');
            $typeKey = $relation . '_type';

            /*
            |--------------------------------------------------------------------------
            | 🔥 POLYMORPHIC
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
            | 🔥 NORMAL RELATION (Eloquent)
            |--------------------------------------------------------------------------
            */
            if (method_exists($modelInstance, $relation)) {

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

        return $resolved;
    }
}