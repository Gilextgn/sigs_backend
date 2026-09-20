<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        // Le rôle propriétaire de la plateforme n'existe pas pour une école.
        return Role::with(['permissions' => fn ($query) => $query->select('permissions.id', 'code', 'label')->where('code', 'not like', 'platform.%')])
            ->where('code', '!=', 'platform_owner')
            ->get();
    }

    public function permissionsCatalog()
    {
        return Permission::where('code', 'not like', 'platform.%')->orderBy('code')->get();
    }
}
