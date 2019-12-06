<?php


namespace TPlus\VoyagerBread\Handles;


use Illuminate\Database\Eloquent\Model;
use TCG\Voyager\Models\Permission;

define('DEFAULT_UESR', 1);

class ROLES {

    /**
     * Set role for admin user
     * @param Model $model
     */
    public static function handle($del, Model $model) {
        $table_name = $model->getTable();

        $permissions = Permission::whereTableName($table_name)->get();

        foreach ($permissions as $permission) {
            $permission->roles()->detach(DEFAULT_UESR);
            $del?:$permission->roles()->attach(DEFAULT_UESR);
        }
    }
}
