<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       foreach(PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value]);
       }
       foreach(RoleEnum::cases() as $role) {
            $permissions = $this->getRolesPermissions($role);
            $roleModel = Role::firstOrCreate(['name' => $role->value]);
            $roleModel->syncPermissions($permissions);
       }
    }

    private function getRolesPermissions(RoleEnum $role) 
    {
        return match($role->value) {
            RoleEnum::ADMIN->value => [
                PermissionEnum::PRODUCT_VIEW->value,
                PermissionEnum::PRODUCT_CREATE->value,
                PermissionEnum::PRODUCT_UPDATE->value,
                PermissionEnum::PRODUCT_DELETE->value,
            ],
            RoleEnum::CUSTOMER->value => [
                PermissionEnum::PRODUCT_VIEW->value,
            ],
        };
    }

}
