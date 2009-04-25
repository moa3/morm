<?php
/**
 * This file is just a helper made so you can play with Morm as fast as possible.
 * It is not meant for production use. 
 */

/**
 * Conf constants 
 */

//paths
define('BASE_PATH', dirname(__FILE__).'/');
define('MORM_PATH', BASE_PATH.'orm/');
define('MORM_CONF_PATH', BASE_PATH.'conf/');
define('EXCEPTION_PATH', BASE_PATH.'exception/');
define('MODELS_PATH', BASE_PATH.'models/');
define('GENERATED_MODELS_PATH', BASE_PATH.'models/'); //this one should be writable

//set here your database connection informations
define('DB_HOST', 'localhost:/tmp/mysqld.sock');
define('DB_NAME', 'footrep_dev');
define('DB_USER', NULL);
define('DB_PASSWORD', NULL);

/**
 * Morm Autoloader 
 */
function morm_autoloader($class)
{
    $morm_classes = array(MORM_PATH => array('Morm'=> true,
                                             'MormConf'=> true,
                                             'Mormons'=> true,
                                             'MovableMorm'=> true,
                                             'SqlBuilder'=> true,
                                             'SqlTools'=> true,
                                             'TableDesc'=> true,
                                             'FieldDesc'=> true,
                                             'MormAttachement'=> true,
                                            ),
                          EXCEPTION_PATH => array('MormValidateException'=> true,
                                                  'MormNoForeignObjectToLoadException'=> true,
                                                 ),
                         );
    /**
     * is the required class a Morm class ? 
     */
    foreach($morm_classes as $path => $classes)
    {
        if(isset($classes[$class]))
        {
            require_once($path.$class.'.php');
            return true;
        }
    }
    /**
     * ok, maybe it's a model 
     */
    $class_path = MODELS_PATH.$class.'.php';
    if(file_exists($class_path))
    {
        require_once($class_path);
        return true;
    }
    /**
     * well let's try to generate it 
     */
    $generated_class_path = GENERATED_MODELS_PATH.$class.'.php';
    MormConf::generateMorm($class);
    if(file_exists($generated_class_path))
    {
        require_once($generated_class_path);
        return true;
    }
    /**
     * I'm sorry, I did the best I could, but I could not load your class 
     */
    return false;
}

/**
 * DB connection 
 */

function db_connect()
{
    $db = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);

    if (!$db) {
        echo _("Error: DB connexion failed (check error log).\n");
        exit;
    }

    if (!mysql_select_db(DB_NAME, $db)) {
        printf(_("Error: %s (check error log).\n"),mysql_error($db));
        exit;
    }

//    "SET NAMES 'utf8'",
//    "SET lc_time_names = 'fr_FR'",
//    if(isset($to_load['load_queries']) && is_array($to_load['load_queries']))
//        foreach($to_load['load_queries'] as $query)
//            SqlTools::sqlQuery($query);
}

spl_autoload_register('morm_autoloader');
db_connect();
