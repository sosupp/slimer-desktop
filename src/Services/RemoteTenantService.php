<?php
namespace Sosupp\SlimerDesktop\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Sosupp\SlimerTenancy\Services\Tenant\TenantCrudService;

class RemoteTenantService
{
    public function create(array $data)
    {
        $validated = [];
        $useShortName = cleanTenantName($data['shortName']);
        // dd($validated, $useShortName, $this->deployed);

        $schema = 'tenant_'.str($useShortName)->slug('_');
        $validated['schema'] = $schema;
        $validated['domain'] = $useShortName.'.'.config('slimertenancy.root.domain');
        $validated['subdomain'] = $useShortName;
        $validated['name'] = $data['businessName'];
        $validated['owner'] = $data['owner'];
        $validated['email'] = $data['email'];
        $validated['phone'] = $data['phone'];
        // dd($validated, $schema, str_replace(' ', '', $useShortName));

        return (new TenantCrudService)->make(
            id: null,
            data: $validated,
        );
    }

    public function deploy($tenant, mixed $models = null)
    {
        // dd($tenant, str($tenant->owner)->explode(' ')->toArray());
        $result = null;

        if($tenant && $tenant->is_deployed === false){
            $schema = $tenant->schema;

            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
            // dd("tenant", $tenant, $schema, DB::connection());
            config([
                'database.connections.tenant.schema' => $schema,
                'database.connections.tenant.search_path' => $schema . ',public',
            ]);

            // Change the default database connection
            config(['database.default' => 'tenant']);

            DB::purge('tenant');
            DB::reconnect('tenant');

            $owner = str($tenant->owner)->explode(' ')->toArray();

            if (!defined('STDIN')) {
                define('STDIN', fopen('php://stdin', 'r'));
            }

            $output = null;

            $result = Artisan::call(
                command: 'app:tenant-migrate',
                parameters: [
                    '--refresh' => true,
                    '--tenant' => $tenant->id,
                    '--owner' => [
                        'firstName' => $owner[0],
                        'lastName' => $owner[1] ?? $owner[0],
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'models' => $models,
                    ]
                ],
                outputBuffer: $output
            );

            // Log::info('deploy', ['result' => $result]);
            if($result == 0){

                $tenant->update(['is_deployed' => true]);

                // Reset the database connection
                config(['database.default' => 'pgsql']);

                DB::purge('tenant');
                DB::reconnect('pgsql');

                return true;

            }

            return false;
        }

        return false;
    }
}
