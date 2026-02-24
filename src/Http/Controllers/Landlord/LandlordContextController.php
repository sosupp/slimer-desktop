<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Sosupp\SlimerDesktop\Services\RemoteTenantService;
use Sosupp\SlimerTenancy\Models\Landlord\Tenant;
use Sosupp\SlimerTenancy\Traits\WithTenantAware;

class LandlordContextController extends Controller
{
    use WithTenantAware;

    public function check($code)
    {
        $tenant = Tenant::where('subdomain', $code)->first();

        if (! $tenant) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $models = $this->inTenant($tenant, function(){
            return [
                'users' => DB::table('users')
                    ->get()->map(fn ($r) => (array) $r)    ->toArray(),

                'model_has_roles' => DB::table('model_has_roles')
                    ->get()->map(fn ($r) => (array) $r)->toArray(),

                'model_has_permissions' => DB::table('model_has_permissions')
                    ->get()->map(fn ($r) => (array) $r)->toArray(),
            ];
        });

        return response()->json([
            'status' => $tenant->status,
            'schema' => $tenant->schema,
            'is_deployed' => $tenant->is_deployed,
            'models' => $models,
        ]);
    }

    public function store(Request $request)
    {
        // return response()->json([
        //     'status' => 'test',
        //     'data' => $request->input()
        // ], 202);

        // We can queue also on remote
        // DeployTenantJob::dispatch($tenant->id);
        $remoteTenant = (new RemoteTenantService);

        $tenant = $remoteTenant->create(
            $request->input()
        );

        // We need users, model_has_roles (and some few important records created first on local desktop)
        $models = $request->input('models');

        $deployed = $remoteTenant->deploy($tenant, $models);

        return response()->json([
            'status' => 'queued',
            'data' => [
                'tenant' => $tenant,
                'deployed' => $deployed,
            ]
        ], 202);
    }

    public function deploy(Tenant $tenant)
    {
        // return response()->json([
        //     'status' => 'test',
        //     'data' => $tenant,
        //     'connection' => config('database.connections')
        // ]);

        if ($tenant->is_deployed) {
            return response()->json([
                'status' => 'already'
            ]);
        }

        // alt: dispatch a job (DeployTenantJob)
        $result = (new RemoteTenantService)->deploy($tenant);

        return response()->json([
            'message' => $result ? 'success' : 'failed'
        ]);



    }



}
