<?php
namespace Sosupp\SlimerDesktop\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Sosupp\SlimerDesktop\Models\Tenant\SyncLog;


class SyncLogger
{
    public static function store(array $data): SyncLog
    {
        $table = $data['table'];
        $payload = $data['payload'] ?? [];

        // dd($data);

        // 🔥 Inject *_uid dynamically
        // $payload = self::autoInjectUids($table, $payload);
        $payload2 = self::dynamicAutoInjectUids($table, $payload);

        // dd($payload, $payload2);

        // Replace payload
        $data['payload'] = $payload2;

        // dd($data, $payload);
        // Log::info("data", [
        //     'data' => $data,
        //     'payload' => $data['payload'],
        // ]);

        // dd($data, $payload);

        // Create/ save log
        return SyncLog::create([
            'model' => $data['model'],
            'table' => $data['table'] ?? null,
            'model_id' => $data['model_id'] ?? null,
            'model_uid' => $data['model_uid'] ?? null,
            'action' => $data['action'],
            'payload' => $data['payload'],
            'version' => $data['version'] ?? 1,
            'transaction_type' => $data['transaction_type'] ?? null,
            'source' => $data['source'] ?? 'local',
        ]);
    }

    protected static function autoInjectUids(string $table, array $payload): array
    {
        // dd($table, $payload);
        foreach ($payload as $key => $value) {

            // Handle *_id fields
            if (str_ends_with($key, '_id') && !empty($value)) {

                // product_id → product
                $relation = Str::beforeLast($key, '_id');

                // 🔥 Check if polymorphic
                $typeKey = $relation . '_type';

                if (isset($payload[$typeKey])) {

                    // ✅ Polymorphic handling
                    $modelClass = $payload[$typeKey];

                    if (!class_exists($modelClass)) {
                        continue;
                    }

                    $modelInstance = new $modelClass;
                    $relatedTable = $modelInstance->getTable();

                    $record = DB::table($relatedTable)
                        ->where('id', $value)
                        ->first();

                    if ($record && isset($record->uid)) {
                        $payload[$relation . '_ruid'] = $record->uid;
                    }

                    continue;
                }

                // ✅ Normal (non-polymorphic) product → products
                $relatedTable = config("slimerdesktop.syncs.table_relations.$table.$key")
                    ?? Str::plural(Str::snake($relation));

                // dd($relatedTable, $relation);

                $record = DB::table($relatedTable)
                    ->where('id', $value)
                    ->first();

                if ($record && isset($record->uid)) {
                    // _ruid represents the related model uid. r stands for relation
                    $payload[$relation . '_ruid'] = $record->uid;
                }
            }
        }

        // dd($payload);

        return $payload;
    }

    protected static function dynamicAutoInjectUids(string $table, array $payload): array
    {
        $configRelations = config("syncs.table_relations.$table", []);

        foreach ($payload as $key => $value) {

            if (!str_ends_with($key, '_id') || empty($value)) {
                continue;
            }

            $relation = Str::beforeLast($key, '_id');

            // 🔥 1. Check config override
            $relationConfig = $configRelations[$key] ?? null;

            /*
            |--------------------------------------------------------------------------
            | ✅ POLYMORPHIC HANDLING
            |--------------------------------------------------------------------------
            */
            $typeColumn = $relationConfig['type_column'] ?? ($relation . '_type');

            // dd($typeColumn);

            if (isset($payload[$typeColumn])) {
                // dd("yes");

                $modelType = $payload[$typeColumn];

                // resolve morphMap if used
                $modelClass = Relation::getMorphedModel($modelType) ?? $modelType;


                if (!class_exists($modelClass)) {
                    continue;
                }

                $modelInstance = new $modelClass;
                $relatedTable = $modelInstance->getTable();

                $record = DB::table($relatedTable)
                    ->where('id', $value)
                    ->first();



                if ($record && isset($record->uid)) {
                    $payload[$relation . '_ruid'] = $record->uid;
                }

                continue;
            }else{

            // dd("es");
                /*
                |--------------------------------------------------------------------------
                | ✅ AUTO-DETECTION FALLBACK
                |--------------------------------------------------------------------------
                */
                $relatedTable = Str::plural(Str::snake($relation));

                // dd($relatedTable, Schema::hasTable($relatedTable));

                if(Schema::hasTable($relatedTable)){
                    $record = DB::table($relatedTable)
                        ->where('id', $value)
                        ->first();

                    if ($record && isset($record->uid)) {
                        $payload[$relation . '_ruid'] = $record->uid;
                    }

                    continue;
                }


            }

            /*
            |--------------------------------------------------------------------------
            | ✅ CONFIG-BASED RELATION
            |--------------------------------------------------------------------------
            */
            if ($relationConfig && isset($relationConfig['table'])) {

                $relatedTable = $relationConfig['table'];

                $record = DB::table($relatedTable)
                    ->where('id', $value)
                    ->first();

                if ($record && isset($record->uid)) {
                    $payload[$relation . '_ruid'] = $record->uid;
                }

                continue;
            }

        }

        // dd($payload);
        return $payload;
    }
}
