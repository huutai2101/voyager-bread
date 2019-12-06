<?php

namespace TPlus\VoyagerBread\Handles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;

define('FAILED', 0);
define('SUCCESS', 1);
define('DELETE', 2);
define('CONTROLLER_NS', 'App\Http\Controllers\\');
define('BREAD_DEFAULT', '11111');
define('BREAD_CUSTOM', '01001');
define('COLS_DEFAULT', ['id', 'created_at', 'updated_at']);
class BREAD {

    /**
     * Get data in voyagerBread
     * @param Model $model
     * @return array
     */
    private static function getVoyagerBread(Model $model) {
        if (method_exists($model, 'voyagerBread')) {
            $voyagerBread = $model->voyagerBread();
            return $voyagerBread;
        }
        return [];
    }

    /**
     * Delete old data of bread of DataType and DataRow
     * @param $table_name
     */
    private static function deleteOldBread($table_name) {
        $oldBread = DataType::where('name', $table_name)->first();
        if ($oldBread != null) {
            DataRow::where('data_type_id', $oldBread->id)->delete();
            $oldBread->delete();
        }
    }

    /**
     * Create a new Bread (new DataType)
     * @param Model $model
     * @param $voyagerBread
     * @throws \Exception
     */
    private static function createNewBread(Model $model, $voyagerBread) {
        $dataBread = new DataType();
        $dataBread->updateDataType(self::getData($model, $voyagerBread), true);
    }

    /**
     * Get slug
     * @param $table_name
     * @return mixed
     */
    private static function slug($table_name) {
        return str_replace('_','-',$table_name);
    }

    /**
     * Get singular
     * @param $table_name
     * @return string
     */
    private static function singular($table_name) {
        $singular = Str::singular($table_name);
        return self::plural($singular);
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
     * Get controller
     * @param $table_name
     * @return string
     */
    private static function controller($model_class, $table_name) {
        //Don't create controller for example
        if (Str::contains($model_class, 'Examples')) return '';
        return CONTROLLER_NS.self::singular($table_name).'Controller';
    }

    /**
     * Get general data of BREAD
     * @param Model $model
     * @param $voaygerBread
     * @return array
     */
    private static function getGeneralData(Model $model, $voyagerBread) {
        $table_name = $model->getTable();
        $model_class = get_class($model);

        //General data request
        return [
            'generate_permissions'  => 1,
            'server_side'           => 1,
            'name'                  => $table_name,
            'slug'                  => $voyagerBread['slug'] ?? self::slug($table_name),
            'display_name_singular' => $voyagerBread['singular'] ?? self::singular($table_name),
            'display_name_plural'   => $voyagerBread['plural'] ?? self::plural($table_name),
            'model_name'            => $model_class,
            'controller'            => $voyagerBread['controller'] ?? '',
            'icon'                  => $voyagerBread['icon'] ?? '',
            'policy_name'           => $voyagerBread['policy_name'] ?? '',
            'relationships'         => [],
        ];
    }

    /**
     * Generate array BREAD
     * @param $bread
     * @return array
     */
    private static function generateBread($bread) {
        !strlen($bread)<5 ?: $bread = BREAD_DEFAULT;
        return collect([
            'browse' => (int) $bread[0],
            'read'   => (int) $bread[1],
            'edit'   => (int) $bread[2],
            'add'    => (int) $bread[3],
            'delete' => (int) $bread[4],
        ])->filter()->toArray();
    }

    /**
     * Get fields data
     * @param $voyagerBread
     * @return mixed
     */
    private static function getFields($voyagerBread) {
        //Get general fields
        $dataFields = collect($voyagerBread['fields'] ?? [])->map(function ($field, $name) {
            //Get bread;
            in_array($name, COLS_DEFAULT) ? $bread = $field['bread'] ?? BREAD_CUSTOM : $bread = $field['bread'] ?? BREAD_DEFAULT;

            //Extract bread
            $extract_bread = self::generateBread($bread);

            //Remove $field['bread']
            if ($field['bread'] ?? '') unset($field['bread']);
            return array_merge($field, $extract_bread);
        })->filter();

        //Get fields from relationships
        $dataRelationships = collect($voyagerBread['relationships'] ?? [])->map(function ($field, $name) {
                if ($field['type'] == 'belongsTo') {
                    $bread = $field['bread'] ?? BREAD_DEFAULT;
                    $extract_bread = self::generateBread($bread);
                    return array_merge(['title' => $field['title'] ?? $name . ' (No Title)'], $extract_bread);
                }
                return null;
        })->filter();

        //Merge data
        $fields = $dataFields->merge($dataRelationships);

        //Put/update id, created_at and updated_at
        $fields->prepend(self::updateICU($fields, 'id'), 'id');
        $fields->put('created_at', self::updateICU($fields, 'created_at'));
        $fields->put('updated_at', self::updateICU($fields, 'updated_at'));

        return $fields;
    }

    /**
     * Get realationship
     * @param $table_name
     * @param $rel_table_name
     * @param $type
     * @return string
     */
    private static function getRef($table_name, $rel_table_name, $type) {
        return Str::singular($table_name) . '_' . strtolower($type) . '_' . Str::singular($rel_table_name) . '_relationship';
    }

    /**
     * Fill data to generate into DataRow
     * @param $table_name
     * @param $voyagerBread
     * @param $fields
     * @param $data
     */
    private static function fillDataRequest($table_name, $voyagerBread, $fields, &$data) {
        //Add data model to data request
        foreach ($fields as $name => $value) {
            $data["field_browse_{$name}"]           = $value['browse'] ?? null;
            $data["field_read_{$name}"]             = $value['read'] ?? null;
            $data["field_edit_{$name}"]             = $value['edit'] ?? null;
            $data["field_add_{$name}"]              = $value['add'] ?? null;
            $data["field_delete_{$name}"]           = $value['delete'] ?? null;
            $data['field_required_' . $name]        = $value['required'] ?? null;
            $data['field_' . $name]                 = $name;
            $data['field_input_type_' . $name]      = $value['field_type'] ?? 'string';
            $data['field_details_' . $name]         = empty($value['options']) ? '{}' : json_encode($value['options']);
            $data['field_display_name_' . $name]    = $value['title'];
            $data['field_order_' . $name] = 0;
        }

        //Fill relations
        foreach (($voyagerBread['relationships'] ?? []) as $name => $value) {
            $rel_model_ns    = !Str::contains($value['model'], '\\') ? 'App\\Models\\' . $value['model'] : $value['model'];
            $rel_table_name  = app($rel_model_ns)->getTable();
            $relationship    = self::getRef($table_name, $rel_table_name, $value['type']);
            $bread           = $value['bread'] ?? BREAD_DEFAULT;
            $bread           = self::generateBread($bread);
            
            //General for relationship
            $data['relationships'][] = $relationship;
            $data["field_browse_{$relationship}"]                    = $bread['browse'] ?? null;
            $data["field_read_{$relationship}"]                      = $bread['read'] ?? null;
            $data["field_add_{$relationship}"]                       = $bread['add'] ?? null;
            $data["field_edit_{$relationship}"]                      = $bread['edit'] ?? null;
            $data["field_delete_{$relationship}"]                    = $bread['delete'] ?? null;
            $data['relationship_type_' . $relationship]              = $value['type'];
            $data['relationship_column_belongs_to_' . $relationship] = $name;
            $data['relationship_column_' . $relationship]            = $name;
            $data['relationship_model_' . $relationship]             = $rel_model_ns;
            $data['relationship_table_' . $relationship]             = $rel_table_name;
            $data['relationship_key_' . $relationship]               = $value['key'] ?? 'id';
            $data['relationship_label_' . $relationship]             = $value['label'] ?? 'id';
            $data['relationship_pivot_table_' . $relationship]       = $value['pivot_table'] ?? '';
            if ($value['type'] == 'belongsToMany') {
                $data['relationship_pivot_table_' . $relationship]   = Str::singular($table_name) . '_' . $rel_table_name;
            }
            $data['relationship_pivot_' . $relationship]             = !empty($data['relationship_pivot_table_' . $relationship]);
            $data['field_required_' . $relationship]                 = $value['required'] ?? 0;
            $data['field_' . $relationship]                          = $relationship;
            $data['field_input_type_' . $relationship]               = 'relationship';
            $data['field_display_name_' . $relationship]             = $value['title'] ?? $name . ' (No title)';
            $data['field_order_' . $relationship]                    = 0;

            if ($morph_name = $value['morph_name'] ?? '') $data['relationship_morph_name_' . $relationship] = $morph_name;
            if ($options = $value['options'] ?? []) $data['relationship_options_' . $relationship] = $options;
            if ($value['type'] == 'belongsTo') {
                $data['field_' . $name] = $name;
                $data['field_add_' . $name] = 1;
                $data['field_edit_' . $name] = 1;
            }
        }

        $data = collect($data)->filter(function () {
            return !null;
        })->toArray();
    }

    /**
     * Get data to generate
     * @param Model $model
     * @param $voyagerBread
     * @return array
     */
    private static function getData(Model $model, $voyagerBread) {
        //Get table name
        $table_name = $model->getTable();
        $model_class = get_class($model);

        //General data request
        $data = self::getGeneralData($model, $voyagerBread);

        //Get fields data from model
        $fields = self::getFields($voyagerBread);

        //Add data model to data request
        self::fillDataRequest($table_name, $voyagerBread, $fields, $data);

        return $data;
    }

    /**
     * Main hand
     * @param Model $model
     * @return string
     * @throws \Exception
     */
    public static function voyagerBread($del, Model $model) {
        $table_name = $model->getTable();

        //Get voyager bread info form model
        $voyagerBread = self::getVoyagerBread($model);
        if (!$voyagerBread) return '';

        //Delete old bread (DataTypes and DataRows)
        self::deleteOldBread($table_name);
        if ($del) return self::notify(DELETE,$table_name);

        //Check table in database
        if (!SchemaManager::tableExists($table_name)) return self::notify(FAILED,$table_name);


        //Create new bread
        self::createNewBread($model, $voyagerBread);

        //Return notify
        return self::notify(SUCCESS,$table_name);
    }

    /**
     * Default bread for id, created_at, updated_at
     * @param $fields
     * @param $key
     * @return array
     */
    private static function updateICU($fields, $key) {
        $arr = $fields->get($key) ?? [];
        return array_merge($arr, [
            'title'         => $key == 'id' ? 'ID' : ($key == 'created_at' ? 'Created At' : 'Updated At'),
            'type'          => 'int',
            'field_type'    => $key == 'id' ? 'number' : 'timestamp',
            'read'          => $arr['read'] ?? 1,
            'delete'        => $arr['delete'] ?? 1,
        ]);
    }

    /**
     * Set notifies for Command Prompt
     * @param $type
     * @param $table_name
     * @return string
     */
    private static function notify($type, $table_name) {
        switch ($type) {
            case SUCCESS:
                $notify = '<fg=white>BREAD <fg=green>' . $table_name . '</> has generated.</>';
                break;
            case FAILED:
                $notify = '<fg=red>Not found TABLE\'s <fg=yellow>' . $table_name. '</> </>';
                break;
            case DELETE:
                $notify = '<fg=white>DELETE bread <fg=yellow>' . $table_name. '</> success!</>';
                break;
            default:
                $notify = '';
        }
        return $notify;
    }
}
