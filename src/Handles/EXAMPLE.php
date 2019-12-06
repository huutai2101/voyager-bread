<?php

namespace TPlus\VoyagerBread\Handles;

use Illuminate\Filesystem\Filesystem;

define('SEE_INFO', 0);
define('CREAT', 1);
class EXAMPLE {

    private static $notifies = '';
    private static $modelExamples = ['Formfield', 'Service', 'Company', 'Employee'];
    
    public static function generateExample() {
        //Create a new Examples folder
        $filesystem = new Filesystem();

        //Create Models folder
        $path = app_path('Models');
        if (!$filesystem->exists($path)) mkdir($path, 0700);

        //Create Examples folder
        $path = app_path('Models\Examples');
        if (!$filesystem->exists($path)) mkdir($path, 0700);

        foreach (self::$modelExamples as $fileName) {
            //Check file exist or not
            $filePath = $path . '\\' . $fileName.'.php';
            $check = file_exists($filePath);
            if ($check) continue;

            //Copy file from package to model
            $file = $filesystem->get(__DIR__ . '/../Models/Examples/' . $fileName.'.exam');
            $file = self::decodeContent($file);
            $filesystem->put($filePath, $file);
            self::notify(CREAT,$fileName);
        }
        self::notify(SEE_INFO,'');
        return self::$notifies;
    }

    /**
     * Decode file .exam
     * @param $file
     * @return string
     */
    private static function decodeContent($file) {
        $content = '';
        preg_match_all('!\d+!', $file, $arrChar);
        foreach ($arrChar[0] as $ascii) {
            $ascii = (int)$ascii;
            $content .= chr($ascii);
        }
        return $content;
    }

    /**
     * Set notifies for Command Prompt
     * @param $type
     * @param $table_name
     */
    private static function notify($type, $file_name) {
        !self::$notifies ?: self::$notifies .= PHP_EOL;
        switch ($type) {
            case CREAT:
                self::$notifies .= '<fg=magenta>Example model <fg=green>' .$file_name. '</> has generated.</>';
                break;
            case SEE_INFO:
                self::$notifies .= '<fg=white>Please check \'App\Models\Examples\' to see more details.</>';
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
