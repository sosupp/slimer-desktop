<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Sosupp\SlimerTenancy\Models\Landlord\Tenant;
use Sosupp\SlimerTenancy\Services\Tenant\TenantManagerService;

class TenantContextController extends Controller
{
    public function __construct(
        public TenantManagerService $tenantManager,
    )
    {

    }

    public function records($tenant)
    {


        try {
            $tenant = Tenant::findOrFail($tenant);
            //code...
            if(!$tenant){
                return response()->json([
                    'message' => 'no tenant found',
                ]);
            }

            if(!$tenant->is_deployed){
                return response()->json([
                    'message' => 'tenant not setup',
                ]);
            }

            $this->tenantManager->setTenant($tenant);

            return response()->json([
                'users' => User::all()->makeVisible([
                    'password', 'two_factor_secret',
                    'two_factor_recovery_codes', 'remember_token',
                    'telegram_token',
                ]),
                'customers' => '',
                'schema' => $tenant,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'no tenant found',
            ]);
        }

    }
}
