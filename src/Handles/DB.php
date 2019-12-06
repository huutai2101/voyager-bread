<?php

namespace TPlus\VoyagerBread\Handles;

use Doctrine\DBAL\Schema\SchemaException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use TCG\Voyager\Database\DatabaseUpdater;
use TCG\Voyager\Database\Schema\SchemaManager;

define('DB_CREATE',     1);
define('DB_UPDATE',     2);
define('DB_PIVOT',      3);
define('DB_DELETE',     4);

class DB {

    private static $notifies = '';
    private static $table = [];
    private static $cols = ['id', 'created_at', 'updated_at'];


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
     * Generate columns of fields
     * @param $voyagerBread
     * @return \Illuminate\Support\Collection
     */
    private static function getColumnFields($voyagerBread) {
        return collect($voyagerBread['fields'] ?? [])->map(function ($data, $name) {
            if (in_array($name, self::$cols)) return '';
            $type = $data['type'] ?? 'string';
            $type != 'int' ?: $type = 'integer';
            $length = $data['length'] ?? ($type == 'string' ? 255 : ($type == 'integer' ? 10 : null));
            return [
                'name'      => $name,
                'oldName'   => $data['oldName'] ?? $name,
                'type'      => ['name' => $type],
                'length'    => $length,
                'notnull'   => $data['required'] ?? 0,
                'unsigned'  => $data['unsigned'] ?? 0,
                'default'   => $data['default'] ?? null,
            ];
        });
    }

    /**
     * Generate columns of relationships
     * If there is a table relationship, then create the first one
     * @param $voyagerBread
     * @return \Illuminate\Support\Collection
     */
    private static function getColumnRelationships($voyagerBread) {
        return collect($voyagerBread['relationships'] ?? [])->map(function ($data, $name) {
            if ($data['type'] == 'belongsTo') {
                //Create database relationship first, use recursion
                $ns_model_rel = !Str::contains($data['model'], '\\') ? 'App\\Models\\' . $data['model'] : $data['model'];
                $model_rel = app($ns_model_rel);
                $table_rel_name = $model_rel->getTable();
                if (!SchemaManager::tableExists($table_rel_name)) {
                    self::voyagerDB($model_rel);
                    array_push(self::$table, $table_rel_name);
                }
                return [
                    'name'      => $name,
                    'oldName'   => $data['oldName'] ?? $name,
                    'type'      => ['name' => 'integer'],
                    'length'    => 10,
                    'notnull'   => $data['required'] ?? 0,
                    'unsigned'  => 1,
                ];
            }
            return null;
        });
    }

    /**
     * Generate column of id auto increment
     * @return array
     */
    private static function id() {
        return [
            'name'          => 'id',
            'oldName'       => 'id',
            'type'          => ['name' => 'integer'],
            'length'        => 10,
            'notnull'       => 1,
            'autoincrement' => 1,
            'extra'         => 'auto_increment',
            'unsigned'      => 1,
        ];
    }

    /**
     * Generate column of create at
     * @return array
     */
    private static function createdAt() {
        return [
            'name'      => 'created_at',
            'oldName'   => 'created_at',
            'type'      => ['name' => 'datetime'],
        ];
    }

    /**
     * Generate column of update at
     * @return array
     */
    private static function updatedAt() {
        return [
            'name'      => 'updated_at',
            'oldName'   => 'updated_at',
            'type'      => ['name' => 'datetime'],
        ];
    }

    /**
     * Add general id, created_at, updated_at
     * @param Collection $fields
     * @param Collection $relationships
     * @return array|Collection
     */
    private static function addICU(Collection $fields, Collection $relationships) {
        //Merge columns
        $columns = $fields->merge($relationships)->filter();

        //Add id, created_at, updated_at to columns
        $columns = $columns->prepend(self::id())
                           ->push(self::createdAt())
                           ->push(self::updatedAt())
                           ->values()->all();

        return $columns;
    }

    /**
     * Get indexes for table
     * @param $voyagerBread
     * @param $table_name
     * @return mixed
     */
    private static function getIndexes($voyagerBread, $table_name) {
        //Get indexes from relationships (belongsTo)
        $indexes = collect($voyagerBread['relationships'] ?? [])->map(function ($data, $name) {
            if ($data['type'] == 'belongsTo') {
                return [
                    'columns'       => [$name],
                    'isPrimary'     => 0,
                    'isUnique'      => 0,
                ];
            }
            return null;
        })->filter();

        //Push general indexes
        $indexes = $indexes->push([
                    'name'          => 'primary',
                    'columns'       => ['id'],
                    'type'          => 'PRIMARY',
                    'isPrimary'     => 1,
                    'isUnique'      => 1,
                    'isComposite'   => 0,
                    'table'         => $table_name,
        ]);

        //Get data
        $indexes = $indexes->values()->all();
        return $indexes;
    }

    /**
     * Get foreign keys for table
     * @param $voyagerBread
     * @param Model $model
     * @return Collection
     */
    private static function getForeignKeys($voyagerBread, Model $model) {
        $table_name = $model->getTable();

        //Get foreign keys
        $foreignKeys = collect($voyagerBread['relationships'] ?? [])->map(function ($data, $name) use ($model, $table_name) {
            if ($data['type'] == 'belongsTo') {
                $ns_model_rel = !Str::contains($data['model'], '\\') ? 'App\\Models\\' . $data['model'] : $data['model'];
                $model_rel = app($ns_model_rel);
                $table_rel_name = $model_rel->getTable();

                return [
                    'name'              => $table_name . '_' . $table_rel_name . '_fk',
                    'localName'         => $table_name,
                    'foreignTable'      => $table_rel_name,
                    'localColumns'      => [$name],
                    'foreignColumns'    => [$data['key'] ?? 'id'],
                ];
            }
            return null;
        })->filter();

        $foreignKeys = $foreignKeys->values()->all();

        return $foreignKeys;
    }

    /**
     * Generate table data into database
     * @param $table
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private static function generateTable($table) {
        $table_name = $table['name'];
        if (SchemaManager::tableExists($table_name)) {
            DatabaseUpdater::update($table);
            self::notify(DB_UPDATE,$table_name);
        } else {
            SchemaManager::createTable($table);
            self::notify(DB_CREATE,$table_name);
        }
    }

    /**
     * Delete table with relationship
     * @param  Model  $model        [description]
     * @param  [type] $voyagerBread [description]
     * @return [type]               [description]
     */
    private static function deleteTable($table_name) {
        //Disable foreign key first
        Schema::disableForeignKeyConstraints();

        //Drop
        Schema::dropIfExists($table_name);

        //Show notification
        self::notify(DB_DELETE,$table_name);
    }
        

    /**
     * Get data pivot table
     * @param $voyagerBread
     * @param Model $model
     */
    private static function generatePivotTable($voyagerBread, Model $model) {
        collect($voyagerBread['relationships'] ?? [])->each(function ($data, $name) use ($model) {
            if ($data['type'] == 'belongsToMany') {
                $ns_model_rel = !Str::contains($data['model'], '\\') ? 'App\\Models\\' . $data['model'] : $data['model'];
                $model_rel = app($ns_model_rel);
                $table_rel_name = $model_rel->getTable();
                $table_name = $model->getTable();

                $local_key = $model->getForeignKey();
                $foreign_key = $model_rel->getForeignKey();
                $pivot_table = $data['pivot_table'] ?? (Str::singular($table_name) . '_' . $table_rel_name);

                self::createPivotTable($pivot_table, $local_key, $foreign_key);
            }
        });
    }

    /**
     * Create pivot table into database
     * @param $pivot_table
     * @param $local_key
     * @param $foreign_key
     */
    private static function createPivotTable($pivot_table, $local_key, $foreign_key) {
        if (!$pivot_table || SchemaManager::tableExists($pivot_table)) return;

        //Create columns
        $columns = [
            $local_key => [
                'name'      => $local_key,
                'oldName'   => $local_key,
                'type'      => ['name' => 'integer'],
                'notnull'    => 1,
            ],
            $foreign_key => [
                'name'      => $foreign_key,
                'oldName'   => $foreign_key,
                'type'      => ['name' => 'integer'],
                'notnull'   => 1,
            ],
        ];

        //Create table
        SchemaManager::createTable([
            'name'          => $pivot_table,
            'columns'       => $columns,
            'indexes'       => [
                [
                    'name'          => 'primary',
                    'columns'       => array_keys($columns),
                    'type'          => 'PRIMARY',
                    'isPrimary'     => 1,
                    'isUnique'      => 1,
                    'isComposite'   => 1,
                    'table'         => $pivot_table,
                ]
            ],
            'primaryKeyName'=> 'primary',
            'foreignKeys'   => [],
            'options'       => [],
        ]);
        self::notify(DB_PIVOT,$pivot_table);
    }

    /**
     * Main handle for Generate Database
     * @param Model $model
     * @return string
     */
    public static function voyagerDB($del, Model $model) {
        //Get table name
        $table_name = $model->getTable();
        if (in_array($table_name, self::$table)) return '';
        
        //Get voyager bread info form model
        $voyagerBread = self::getVoyagerBread($model);
        if (!$voyagerBread) return '';


        //Check if delete table
        if ($del) {
            self::deleteTable($table_name);
            return self::$notifies;
        }

        

        //Get columns form fields
        $fields = self::getColumnFields($voyagerBread);

        //Get columns form relationships
        $relationships = self::getColumnRelationships($voyagerBread);

        //Push general columns
        $columns = self::addICU($fields, $relationships);

        //Get indexes
        $indexes = self::getIndexes($voyagerBread, $table_name);

        //Get foreignKeys
        $foreignKeys = self::getForeignKeys($voyagerBread, $model);

        //Create a table data
        $table = [
            'name'              => $table_name,
            'oldName'           => $table_name,
            'columns'           => $columns,
            'indexes'           => $indexes,
            'primaryKeyName'    => 'primary',
            'foreignKeys'       => $foreignKeys,
            'options'           => [],
        ];

        //Generate table into database
        try {
            self::generateTable($table);
        } catch (SchemaException $e) {}


        //Generate pivot table
        self::generatePivotTable($voyagerBread, $model);
        return self::$notifies;
    }

    /**
     * Set notifies for Command Prompt
     * @param $type
     * @param $table_name
     */
    private static function notify($type, $table_name) {
        !self::$notifies ?: self::$notifies .= PHP_EOL;
        switch ($type) {
            case DB_UPDATE:
                self::$notifies .= '<fg=yellow>TABLE <fg=cyan>' . $table_name . '</> has updated.</>';
                break;
            case DB_CREATE:
                self::$notifies .= '<fg=green>TABLE <fg=blue>' . $table_name . '</> has created.</>';
                break;
            case DB_PIVOT:
                self::$notifies .= '<fg=magenta>TABLE <fg=white>' . $table_name . '</> has created.</>';
                break;
            case DB_DELETE:
                self::$notifies .= '<fg=magenta>DELETE table <fg=yellow>' . $table_name . '</> success! </>';
                break;
        }
    }

    /**
     * Clear notifies
     */
    public static function clearNotifies() {
        self::$notifies = '';
    }
}
