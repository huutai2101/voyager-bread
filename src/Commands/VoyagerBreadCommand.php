<?php

namespace TPlus\VoyagerBread\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use TPlus\VoyagerBread\Handles\DB;
use TPlus\VoyagerBread\Handles\BREAD;
use TPlus\VoyagerBread\Handles\EXAMPLE;
use TPlus\VoyagerBread\Handles\MENUS;
use TPlus\VoyagerBread\Handles\ROLES;
use TCG\Voyager\Database\Schema\SchemaManager;

define('ALL',       'AllModels');
define('EXAM',      'ExampleModel');
define('EXAM_NS',   'App\Models\Examples');
class VoyagerBreadCommand extends Command {

    protected $description = 'Generate DATABASE and BREAD from a model';
    protected $signature = "voyager:bread
        {model=AllModels : Run 'ExampleModel' to watch examples}
        {--ns=App\Models : The namespace of model.}
        {--db : Create or update DB}
        {--del : Delete DB and BREAD}";


    /**
     * Confirm for delete DB, BREAD
     * @param $file_name
     * @return string
     */
    private function confirmDel($db, $file_name) {
        if (!is_string($file_name)) {
            $file_name = $file_name->all();
            $file_name = implode(', ', $file_name);
        }

        $db? $db = ' and DATABASE' : $db = '';
        $question = 'Do you want to delete BREAD'.$db.'? <fg=yellow>'.$file_name.'</>';
        $confirm = $this->confirm($question);
        if ($confirm) return true;
        return false;
    }

    /**
     * Generate DB and BREAD a model
     * @param $db
     * @param $model
     * @throws \Exception
     */
    private function generate($del, $db, $model) {
        //Generate or delete DB and show notifies
        if ($db) {
            $notify = DB::voyagerDB($del, $model);
            $this->line($notify);
            DB::clearNotifies();
        }

        //Generate or delete BREAD and show notifies
        $notify = BREAD::voyagerBread($del, $model);
        $this->line($notify);



        //Set roles for administration
        ROLES::handle($del, $model);

        //Generate menu builder
        MENUS::handle($del, $model);
    }

    /**
     * Generate DB and BREAD all models
     * @param $db
     * @param $ns
     * @throws \Exception
     */
    private function generateAll($del, $db, $ns) {
        $file_system  = new Filesystem();
        $files        = $file_system->files($ns);

        //Get all model class name
        $file_names = collect($files)->map(function ($value, $key) use ($ns, $del){
            //Get list file to show confrim
            $file_name = $value->getRelativePathName();
            $model_class_name = Str::replaceLast('.php', '', $file_name);
            
            //Check table exited or not
            $file_path = $ns.'\\'.$model_class_name;
            $model_class = class_exists($file_path) ? $file_path : false;
            if (!$model_class) return;
            $table_name = app($model_class)->getTable();
            if (!$del || ($del && SchemaManager::tableExists($table_name))) return  $model_class_name;
        })->filter();

        //If not found list model, return
        if ($file_names->isEmpty()) return;

        //Confirm to delete DB or BREAD
        if ($del) {
            $confirm = $this->confirmDel($db, $file_names);
            if (!$confirm) return;
        }

        //Generate each file
        foreach($file_names as $file) {
            $file_path = $ns.'\\'.$file;
            $model_class = class_exists($file_path) ? $file_path : false;
            if (!$model_class) return;

            //Get model
            $model = app($model_class);

            //Generate from model
            $this->generate($del, $db, $model);
        };
    }

    /**
     * Control handle for command prompt
     * @throws \Exception
     */
    public function handle() {
        $model      = $this->argument('model');
        $ns         = $this->option('ns');
        $db         = $this->option('db');
        $del        = $this->option('del');

        switch ($model) {
            case EXAM:
                $del ?: $notify = EXAMPLE::generateExample();
                $del ?: $this->line($notify);
                EXAMPLE::clearNotifies();
                $this->generateAll($del, $db, EXAM_NS);
                break;
            case ALL:
                $this->generateAll($del, $db, $ns);
                break;
            default:
                //Confirm to delete DB or BREAD
                if ($del) {
                    $confirm = $this->confirmDel($db, $model);
                    if (!$confirm) return;
                }

                $model_path = $ns . '\\' . $model;
                $model = app($model_path);
                $this->generate($del, $db, $model);
                break;
        }
    }

}
