<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sosupp\SlimerDesktop\Http\Controllers\Api\LocalToRemoteController;
use Sosupp\SlimerDesktop\Http\Controllers\Api\RemoteDataForLocalController;
use Sosupp\SlimerDesktop\Http\Controllers\Api\SyncController;
use Sosupp\SlimerDesktop\Http\Controllers\Landlord\LandlordContextController;
use Sosupp\SlimerDesktop\Http\Controllers\Tenant\TenantContextController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// using bearer token
Route::get('api/v1/desktop/test', function(){
    return response()->json([
        'status' => 'ok'
    ]);
});

Route::middleware(['remote.verify'])->group(function(){
    Route::prefix('api/v1/desktop')->group(function(){
        // Validate if the tenant unique subdomain exist
        Route::get('tenants/{code}', [LandlordContextController::class, 'check']);

        // create tenant from desktop
        Route::post('tenants', [LandlordContextController::class, 'store']);

        // deploy tenant
        Route::post('tenants/{tenant}/deploy', [
            LandlordContextController::class, 'deploy'
        ]);

        // tenant data (schema context)
        Route::get('records/{tenant}', [TenantContextController::class, 'records']);
        // Route::middleware(['tenant'])->group(function () {
        // });


        // Updates from local to remote
        Route::post('local/to/remote/sync/{table}', [
            LocalToRemoteController::class, 'single'
        ]);

        Route::post('local/push', [LocalToRemoteController::class, 'push']);
        Route::post('register/device', [LocalToRemoteController::class, 'registerDevice']);

        Route::get('local/pull', [RemoteDataForLocalController::class, 'pull']);
        Route::get('local/ack/remote', [RemoteDataForLocalController::class, 'acknowledge']);

        Route::post('sync/push', [SyncController::class, 'push']);
        Route::post('sync/db/push', [SyncController::class, 'pushAsDB']);

    });
});

