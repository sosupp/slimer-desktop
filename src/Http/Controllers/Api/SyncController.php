<?php
namespace Sosupp\SlimerDesktop\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function push(Request $request)
    {
        Log::info('sync', [$request->logs]);

        foreach ($request->logs as $log) {
            $modelClass = $log['model'];

            if ($log['action'] === 'deleted') {
                $modelClass::where('uid', $log['model_uid'])->delete();
                continue;
            }

            $model = $modelClass::where('uid', $log['model_uid']);

            if (!$model) {
                $modelClass::create($log['payload']);
                continue;
            }

            if (($log['updated_at']) > ($model->updated_at)) {
                $model->update($log['payload']);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function pushAsDB(Request $request)
    {
        Log::info('sync', [$request->logs]);

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
}
