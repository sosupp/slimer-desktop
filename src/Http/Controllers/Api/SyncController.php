<?php
namespace Sosupp\SlimerDesktop\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SyncController extends Controller
{
    public function push(Request $request)
    {
        foreach ($request->logs as $log) {
            $modelClass = $log['model'];

            if ($log['action'] === 'deleted') {
                $modelClass::where('id', $log['model_id'])->delete();
                continue;
            }

            $model = $modelClass::find($log['model_id']);

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
}