<?php
/**
 *
 * Various conf for Morm, especially autoload methods
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class MormConf
{
    /**
     * cache for the morm_conf.ini file
     */
    static private $_morm_conf;

    /**
     * relativ path to the morm_conf.ini file 
     */
    const INI_CONF_FILE = 'morm_conf.ini';

    /**
     *  separator used for the generated SQL aliases
     *  @todo move sowhere else (probably in SQLTools)
     */
    const MORM_SEPARATOR = '_|_';

    /**
     * generateMormClass
     *
     * more like a model autoloader than a generator
     * tries to bind the given class_name on an existing class either by looking 
     * in the morm_conf.ini file or in the __autoloader.
     * If no class is found, tries to generate a class extending Morm.
     * 
     * @todo refactor this a bit
     * @param string $class_name 
     * @access public
     * @return found or generated class name
     */
    public static function generateMormClass ($class_name)
    {
        $class_name = self::isInConf($class_name) ? self::$_morm_conf[$class_name] : $class_name;
        $table = $class_name;
        if(class_exists($class_name))
        {
            if(in_array('Morm', class_parents($class_name)))
                return $class_name;
            $class_name = 'm_'.$class_name;
        }
        self::generateMorm($class_name, $table);
        $file_name = GENERATED_MODELS_PATH.$class_name.'.php';
        if (file_exists($file_name)) 
        {
            require_once $file_name;
            if(class_exists($class_name))
                {
                    if(in_array('Morm', class_parents($class_name)))
                        return $class_name;
                    throw new MormSqlException('class '.$class_name.' is not a Morm');
                }
            return $class_name;
        }
        return NULL;
    }

    public static function generateMorm($class_name, $table = NULL)
    {
        $file_name = GENERATED_MODELS_PATH.$class_name.'.php';
        $table = is_null($table) ? self::CamelCaseToLower($class_name) : $table;
        $class_name = self::LowerToCamel($class_name);
        if(!file_exists($file_name))
        {
            $tmpl_eclass = <<<Q
<?php
    class %s extends Morm 
    {
        protected \$_table = '%s';
    }
?>

Q;
            file_put_contents($file_name, sprintf($tmpl_eclass, $class_name, $table));
        } 
    }

    /**
     * Converts 'MyPrettyRabbit' into 'my_pretty_rabbit'
     *
     * @param   String  $str    String to convert
     * @return  String
     */
    public static function CamelCaseToLower($str = '')
    {
        if ( empty($str) ) return $str;
        return strtolower(implode('_', array_filter(preg_split('/([A-Z][a-z]*)/', $str, -1, PREG_SPLIT_DELIM_CAPTURE))));
    }

    /**
     * Convert 'my_pretty_rabbit' into 'MyPrettyRabbit'
     *
     * @param   String  $str    String to convert
     * @return  String
     */
    public static function LowerToCamel($str = '')
    {
        if ( empty($str) ) return $str;
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }

    /**
     * getIniConf 
     *
     * cache and return the parsed morm_conf.ini file
     * 
     * @return array parsed ini file
     */
    public static function getIniConf()
    {
        if(!isset(self::$_morm_conf))
        {
            self::$_morm_conf = parse_ini_file(MORM_CONF_PATH.self::INI_CONF_FILE);
        }
        return self::$_morm_conf;
    }

    /**
     * isInConf 
     *
     * looks for the given class name in the morm_conf.ini file
     * 
     * @param string $class_name 
     * @return boolean
     */
    public static function isInConf($class_name)
    {
        self::getIniConf();
        return isset(self::$_morm_conf[$class_name]);
    }

}
