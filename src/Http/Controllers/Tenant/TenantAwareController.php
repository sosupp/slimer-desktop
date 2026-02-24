<?php

namespace Sosupp\SlimerDesktop\Http\Controllers\Tenant;

use Sosupp\SlimerDesktop\Http\Controllers\Controller;
use Sosupp\SlimerTenancy\Traits\WithTenantAware;



class TenantAwareController extends Controller
{
    use WithTenantAware;
}
