<?php
namespace Sosupp\SlimerDesktop\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sosupp\SlimerDesktop\Models\Tenant\SyncLog;


class SyncLogger
{
    public static function store(array $data): SyncLog
    {
        $table = $data['table'];
        $payload = $data['payload'] ?? [];

        // 🔥 Inject *_uid dynamically
        $payload = self::autoInjectUids($table, $payload);

        // Replace payload
        $data['payload'] = $payload;

        // dd($data, $payload);
        Log::info("data", [
            'data' => $data,
            'payload' => $data['payload'],
        ]);

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
        foreach ($payload as $key => $value) {

            if (str_ends_with($key, '_id') && !empty($value)) {

                // product_id → product
                $relation = Str::beforeLast($key, '_id');

                // product → products
                $relatedTable = config("slimerdesktop.syncs.table_relations.$table.$key")
                    ?? Str::plural(Str::snake($relation));

                $record = DB::table($relatedTable)
                    ->where('id', $value)
                    ->first();

                if ($record && isset($record->uid)) {
                    // _ruid represents the related model uid. r stands for relation
                    $payload[$relation . '_ruid'] = $record->uid;
                }
            }
        }

        return $payload;
    }
}
