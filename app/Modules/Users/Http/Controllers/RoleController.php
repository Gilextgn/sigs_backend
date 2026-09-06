<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Role::with('permissions:id,code,label')->get();
    }

    public function permissionsCatalog()
    {
        return Permission::orderBy('code')->get();
    }
}
