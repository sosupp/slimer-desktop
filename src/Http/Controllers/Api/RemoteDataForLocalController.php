<?php

namespace App\Http\Controllers\Api\Remote;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class RemoteDataForLocalController extends Controller
{
    public function handle($model, $key = null)
    {
        $data = match ($model) {
            'platformAdmins' => DB::table('platform_admins')->get(),
            'branchAdmin' => DB::table('branch_platform_admin')->get(),
            'roles' => DB::table('roles')->get(),
            'permissions' => DB::table('permissions')->get(),
            'rolesPermissions' => DB::table('role_has_permissions')->get(),
            'modelPermissions' => DB::table('model_has_permissions')->get(),
            'modelRoles' => DB::table('model_has_roles')->get(),
            'products' => DB::table('products')->get(),
            'productOptions' => DB::table('product_options')->get(),
            'productUnits' => DB::table('product_units')->get(),
            'branches' => DB::table('branches')->get(),
            'activities' => DB::table('activity_log')->get(),
            'priceHistories' => DB::table('price_histories')->get(),
            'adminables' => DB::table('adminables')->get(),

            // based on selected branch
            'branchProducts' => DB::table('branch_product')
            ->where('branch_id', $key)
            ->get(),

            'stockHistories' => DB::table('stock_histories')
            ->where('branch_id', $key)
            ->get(),

            'orders' => DB::table('orders')
            ->where('branch_id', $key)->get(),

            'payments' => DB::table('order_payments')
            ->join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->where('orders.branch_id', $key)
            ->select('order_payments.*')
            ->get(),

            'order_products' => DB::table('order_products')
            ->join('orders', 'order_products.order_id', '=', 'orders.id')
            ->where('orders.branch_id', $key)
            ->select('order_products.*')
            ->get(),

            'sale' => DB::table('sales')
            ->where('branch_id', $key)
            ->get(),

            'sale_return' => DB::table('sale_returns')
            ->join('sales', 'sale_returns.sale_id', '=', 'sales.id')
            ->where('sales.branch_id', $key)
            ->select('sale_returns.*')
            ->get(),

        };

        return response([
            $model => $data
        ]);
    }

    public function initialData()
    {
        $data = [
            'roles' => DB::table('roles')->get(),
            'platform_admins' => DB::table('platform_admins')->get(),
            'branch_platform_admin' => DB::table('branch_platform_admin')->get(),
            'permissions' => DB::table('permissions')->get(),
            'role_has_permissions' => DB::table('role_has_permissions')->get(),
            'model_has_permissions' => DB::table('model_has_permissions')->get(),
            'model_has_roles' => DB::table('model_has_roles')->get(),
            'products' => DB::table('products')->get(),
            'product_units' => DB::table('product_units')->get(),
            'product_options' => DB::table('product_options')->get(),
            'branches' => DB::table('branches')->get(),
            'price_histories' => DB::table('price_histories')->get(),
        ];

        return response($data);
    }

    public function branchData($branchId)
    {
        return response([
            // based on selected branch
            'branch_product' => DB::table('branch_product')
            ->where('branch_id', $branchId)
            ->get(),

            'stock_histories' => DB::table('stock_histories')
            ->where('branch_id', $branchId)
            ->get(),

            'orders' => DB::table('orders')
            ->where('branch_id', $branchId)->get(),

            'order_payments' => DB::table('order_payments')
            ->join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->where('orders.branch_id', $branchId)
            ->select('order_payments.*')
            ->get(),

            'order_products' => DB::table('order_products')
            ->join('orders', 'order_products.order_id', '=', 'orders.id')
            ->where('orders.branch_id', $branchId)
            ->select('order_products.*')
            ->get(),

            'sales' => DB::table('sales')
            ->where('branch_id', $branchId)
            ->get(),

            'sale_returns' => DB::table('sale_returns')
            ->join('sales', 'sale_returns.sale_id', '=', 'sales.id')
            ->where('sales.branch_id', $branchId)
            ->select('sale_returns.*')
            ->get(),
        ]);
    }

    public function single($table, $branchId = null)
    {
        $data = [];

        DB::table($table)
        ->orderBy('id')
        ->chunk(500, function ($rows) use (&$data) {
            foreach ($rows as $row) {
                $data[] = (array) $row; // Convert object to array
            }
        });

        return response([$table => $data]);

        return response([
            $table => DB::table($table)
            ->when($branchId !== null, function($query) use($branchId){
                $query->where('branch_id', $branchId)->get();
            })->get()
        ]);
    }
}
