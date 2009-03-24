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
    const INI_CONF_FILE = '/conf/morm_conf.ini';

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
        }
        $file_name = SITEBASE.'/include/'.self::getJTClassFile($class_name).'.php';
        if(file_exists($file_name)) 
            require_once $file_name;
        if(class_exists($class_name))
        {
            if(in_array('Morm', class_parents($class_name)))
                return $class_name;
            $class_name = 'm_'.$class_name;
        }
        $file_name = SITEBASE.'/include/generated/'.$class_name.'.class.php';
        if(!file_exists($file_name))
        {
            $tmpl_eclass = <<<Q
<?php
    class %s extends Morm 
    {
        var \$_table = '%s';
    }
?>

Q;
            $r = file_put_contents($file_name, sprintf($tmpl_eclass, $class_name, $table));
        } 
        require_once $file_name;
        if(class_exists($class_name))
        {
            if(in_array('Morm', class_parents($class_name)))
                return $class_name;
            throw new Exception('class '.$class_name.' is not a Morm');
        }
        return $class_name;
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
            self::$_morm_conf = parse_ini_file(SITEBASE.self::INI_CONF_FILE);
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

    /**
     * getJTClassFile 
     *
     * used for SFRJT.
     * tries to find the class_name file using the autoloaders rules
     * 
     * @param string $class_name 
     * @return string relativ path to a file without php extension
     */
    public static function getJTClassFile($class_name)
    {
        $exploded_class = explode('_', $class_name);
        if($exploded_class[0] == 'sfrjt')
        {
            array_shift($exploded_class);
            return 'sfrjt/'.implode('/', $exploded_class);
        }
        else
            return $class_name.".class";
    }
}
