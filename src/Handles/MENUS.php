<?php


namespace TPlus\VoyagerBread\Handles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use TCG\Voyager\Models\MenuItem;
use TCG\Voyager\Models\Permission;

define('DEFAUL_MENU', 1);
define('TARGET','_self');

class MENUS {
    /**
     * Get slug
     * @param $table_name
     * @return mixed
     */
    private static function slug($table_name) {
        return str_replace('_','-',$table_name);
    }

    /**
     * Get plural
     * @param $table_name
     * @return string
     */
    private static function plural($table_name) {
        $plural = str_replace('_',' ',$table_name);
        return ucwords($plural);
    }

    /**
     * Create menu builder
     * @param Model $model
     */
    public static function handle($del, Model $model) {
        $table_name = $model->getTable();

        $slug = self::slug($table_name);
        $plural = self::plural($table_name);

        $menu_item = MenuItem::whereTitle($plural)->get()->first();

        if (!is_null($menu_item)) {
            !$del?:$menu_item->delete();
            return;
        }

        $del?: MenuItem::create([
            'menu_id'   => DEFAUL_MENU,
            'title'     => $plural,
            'url'       => '/admin/'.$slug,
            'target'    => TARGET,
            'order'     => self::order(),
        ]);
    }

    /**
     * Get max other
     * @return int|mixed
     */
    public static function order() {
        $max_order = MenuItem::all()->max('order');
        return $max_order + 1;
    }
}
