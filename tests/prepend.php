<?php
// Test Framework
require_once('simpletest/autorun.php');

define('BASE_PATH', dirname(__FILE__).'/../');
define('MORM_PATH', BASE_PATH.'orm/');
define('MORM_CONF_PATH', BASE_PATH.'conf/');
define('EXCEPTION_PATH', BASE_PATH.'exception/');
define('MODELS_PATH', BASE_PATH.'models/');
define('GENERATED_MODELS_PATH', BASE_PATH.'models/'); //this one should be writable

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
                          EXCEPTION_PATH => array('MormSqlException'=> true,
                                                  'MormValidateException'=> true,
                                                  'MormNoForeignObjectToLoadException'=> true,
                                                  'NoPrimaryKeySqlException'=> true,
                                                  'MormDuplicateEntryException' => true,
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
    return false;
}
spl_autoload_register('morm_autoloader');

/**
 *
 */
class TestDatabaseManager
{
    private static $instance = NULL;

    private $db = NULL;

    private $bdd_tests = 'morm-test';

    protected function __construct()
    {
        $this->connectDB();
        $this->selectDB();
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function startTransaction()
    {
        $this->queryDB("BEGIN");
    }

    public function rollback()
    {
        $this->queryDB("ROLLBACK");
    }

    public function importSqlFile($file)
    {
        if (!file_exists($file)) {
            $file = TESTSBASE .'/data/'. $file;
        }
        $sql = file_get_contents($file);
        $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $sql);
        foreach ($queries as $query){
            if (strlen(trim($query)) > 0) {
                $this->queryDB($query);
            }
        }
    }

    protected function connectDB()
    {
        $this->db = mysql_connect('localhost', 'root', '');    
        $this->queryDB("SET NAMES 'utf8'");
        $this->cleanDB();
    }

    protected function selectDB()
    {
        if(!mysql_select_db($this->bdd_tests, $this->db)) {
            throw new Exception('error '. mysql_error($this->db));
        }
    }

    public function cleanDB()
    {
        $this->queryDB('DROP DATABASE `'. $this->bdd_tests .'`');
        $this->queryDB('CREATE DATABASE `'. $this->bdd_tests.'`');
    }

    public function queryDB($query)
    {
        if (!mysql_query($query, $this->db))
        {
            throw new Exception($query . ' ' . mysql_error($this->db));
        }
    }
}

/**
 * Extends UnitTestCase of SimpleTest for creating transactions on each testCase
 * and more ....
 */
class MormUnitTestCase extends UnitTestCase
{
    protected $sql;

    public function __construct()
    {
        parent::__construct();
        $this->sql = TestDatabaseManager::getInstance();
    }

    final public function setUp()
    {
        $this->sql->startTransaction();
        $this->mormSetUp();
    }

    final public function tearDown()
    {
        $this->sql->rollback();
        $this->mormTearDown();
    }
    /**
     * For traditionnal setUp and tearDown, please override mormSetUp and mormTearDown
     */
    protected function mormSetUp() {}

    protected function mormTearDown() {}

    protected function createEntry($class, $params)
    {
        $insert = new $class($params);
        $insert->save();
        return $insert;
    }
}

class Authors extends Morm 
{
    public $_table = "authors" ;

    public static function createTable() 
    {
        return 'CREATE TABLE `authors` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                                       `name` VARCHAR( 255 ) NOT NULL
                                        ) ENGINE = InnoDB;';
    }

    public static function dropTable() 
    {
        return 'DROP TABLE `authors`;';
    }
}

class MormTestCaseWithTableAuthors extends MormUnitTestCase
{
    protected function mormSetUp()
    {
        $this->sql->queryDB(Authors::createTable());
    }

    protected function mormTearDown()
    {
        $this->sql->queryDB(Authors::dropTable());
    }
}
