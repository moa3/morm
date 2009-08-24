<?php
// locale action
setlocale(LC_NUMERIC, 'C');

/**
 * Morm 
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class Morm 
{
    /**
     * @access public
     * @var string
     */
    var $_table;

    /**
     * @access public
     */
    private $_original;

    /**
     * @access private
     */
    private $_fields;

    /**
     * @access private
     */
    private $_errors;

    /**
     * @access protected
     * @var array
     */
    protected $_foreign_keys = array();

    /**
     * @access private
     */
    private $_foreign_values;

    /**
     * @access private
     */
    private $_foreign_object;

    /**
     * @access protected
     * @var array
     */
    protected $_has_many = array();

    protected $_filters = array();

    /**
     * @access private
     */
    private $_foreign_mormons;

    /**
     * @access private
     */
    private $_associated_mormons = null;

    /**
     * @access public
     */
    var $_columns;

    /**
     * @access public
     */
    protected $_pkey = null;

    /**
     * @access protected
     * @var array
     */
    protected $mandatory_errors = array();

    /**
     * @access protected
     * @var array
     */
    protected $type_errors = array();

    /**
     * @access protected
     * @var array
     */
    protected $access_level = array( 
                                   'read' => array(),
                                   'write' => array(),
                                   );

    /**
     * sti_field 
     * 
     * default STI field is 'type'
     *
     * @var string
     */
    protected $sti_field = 'type';

    /**
     * _plugin
     *
     * list of loaded plugins
     *
     * @access protected
     * @var array
     */
    protected static $_plugin = array();

    /**
     * _plugin_method
     *
     * list of methods loaded through plugins
     *
     * @access protected
     * @var array
     */
    protected static $_plugin_method = array();

    /**
     * _plugin_options
     *
     * plugins constructors options
     *
     * @access protected
     * @var array
     */
    protected static $_plugin_options = array();

    /**
     * Constructor. 
     * 
     * load model from 
     * - primary key (can be an array if the primary key is made of mutliple 
     * fields)
     * - array of values (typically from a mysql_fetch_assoc)
     *
     * @param mixed $to_load
     */
    public function __construct ($to_load = null)
    {
        //set the primary key name for the table if there's one
        $this->setPKey();
        if(!is_null($to_load))
        {
            if(is_array($to_load))
            {
                if(is_array($this->_pkey) && count(array_diff(array_keys($to_load),$this->_pkey)) == 0)
                    $this->loadByPKey($to_load);
                else
                    $this->loadFromArray($to_load);
            }
            else if(!is_null($this->_pkey) && !$this->isEmpty($to_load))
                $this->loadByPKey($to_load);
        }
    }

    /**
     * __set 
     * 
     * if $name is a field, set $this->_fields[$name]
     * else just acts as a normal setter
     *
     * @param mixed $name 
     * @param mixed $value 
     * @return void
     */
    public function __set($name, $value)
    {
        if($this->isField($name))
            $this->_fields[$name] = $value;
        else
            $this->$name = $value;
    }

    public function __get ($name)
    {
        if($this->isField($name))
            return isset($this->_fields[$name]) ? $this->_fields[$name] : NULL ;
        if($this->isForeignTable($name))
            return $this->getForeignObject($this->getForeignKeyFromTable($name));
        if($this->isForeignAlias($name))
            return $this->getForeignObject($this->getForeignKeyFromAlias($name));
        if($this->isForeignMormons($name))
            return $this->getManyForeignObjects($name);
        /**
         * if nothing worked before, try to see if the method called get<CamelCased($name)> exists
         * if it does, call it and return the result
         */
        $method_name = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if(method_exists($this, $method_name))
            return $this->$method_name();
        else
            return $this->$name;
    }

    public function __call ($method, $args)
    {
        // Check plugins
        if(self::$_plugin_method[$method])
        {
            $plugin_class = self::$_plugin_method[$method];
            $plugin_options = self::$_plugin_options[$plugin_class];
            $plugin = new $plugin_class ($this, $plugin_options);
            if(is_callable(array($plugin, $method)))
                return call_user_func_array(array($plugin, $method), $args);
        }
    }

    /**
     * isNew 
     * 
     * true if $this->_original[pkey] is not set 
     *
     * @return boolean
     */
    public function isNew()
    {
        if(is_array($this->_pkey))
        {
            foreach($this->_pkey as $key)
            {
                if(!isset($this->_original[$key]))
                    return true;
            }
            return false;
        }
        return !isset($this->_original[$this->_pkey]);
    }


    /**
     * save 
     *
     * if model is new, inserts a row in the database
     * if model is not new, tries to update the row
     *
     * @uses Morm::validate()
     * @uses SqlTools::sqlQuery()
     * @throws MormValidateException through Morm::validateException()
     * @throws MormSqlException through SqlTools::sqlQuery()
     * @param boolean $validate
     * @return boolean
     */
    public function save ($validate=true)
    {
        $valid = $validate ? $this->validate() : true;
        if($valid)
        {
            $this->castFields();
            if(!$this->isNew())
            {      
                if(count($this->fieldsToUpdate()) > 0)
                {
                    return SqlTools::sqlQuery($this->createUpdateSql());
                }

                return true;
            }
            else
            {
                $sql = $this->createInsertSql();
                $ret = SqlTools::sqlQuery($sql);
                if($ret && $this->hasAutoIncrement())
                {
                    $autoincrement_field = $this->table_desc->getAutoIncrementField();
                    $this->_fields[$autoincrement_field] = mysql_insert_id();
                    $this->_original[$autoincrement_field] = $this->_fields[$autoincrement_field];
                }
                return $ret;
            }
        }
        return false;
    }


    public function unJoin($alias_or_table, $foreign_key_value)
    {
        //@todo create specific exception
        if(!$this->isForeignMormons($alias_or_table))
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
        if(isset($this->_has_many[$alias_or_table]['using']))
        {
            $keys = array_keys($this->_has_many[$alias_or_table]['using']);
            $join_table = $keys[0];
            $dummy_id = array($this->_has_many[$alias_or_table]['using'][$join_table]['key'] => $this->{$this->_pkey}, 
                              $this->_has_many[$alias_or_table]['key'] => $foreign_key_value);//@fixme this is not the right way to get the foreign key name
            $class_name = MormConf::generateMormClass($join_table);
            $dummy = new $class_name($dummy_id);
            $dummy->delete();
        }
        else
        {
            $join_table = $this->getForeignMormonsTable($alias_or_table);
            $class_name = MormConf::generateMormClass($join_table);
            $join_table = $this->getForeignMormonsTable($alias_or_table);
            $dummy = new $class_name($foreign_key_value);
            //what should we do here ? Set to zero is certainly not a godd idea
            $dummy->{$this->_has_many[$alias_or_table]['key']} = 0;
            $dummy->save();
        }
    }

    public function joinWithMorm($alias_or_table, $foreign_key_value)
    {
        //@todo create specific exception
        if(!$this->isForeignMormons($alias_or_table))
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
        if(isset($this->_has_many[$alias_or_table]['using']))
        {
            $keys = array_keys($this->_has_many[$alias_or_table]['using']);
            $join_table = $keys[0];
            $to_set = array($this->_has_many[$alias_or_table]['using'][$join_table]['key'] => $this->{$this->_pkey}, 
                              $this->getForeignKeyFromUsingTable($join_table) => $foreign_key_value);//@fixme this is not the right way to get the foreign key name
            $class_name = MormConf::generateMormClass($join_table);
            $dummy = new $class_name();
            $dummy->setFromArray($to_set);
            $dummy->save();
        }
        else
        {
            $class_name = MormConf::generateMormClass($join_table);
            $join_table = $this->getForeignMormonsTable($alias_or_table);
            $dummy = new $class_name($foreign_key_value);
            $dummy->{$this->_has_many[$alias_or_table]['key']} = $this->{$this->_pkey};
            $dummy->save();
        }
    }

    public function isFilter ($filter)
    {
        if(isset($this->_filters[$filter]))
            return $this->_filters[$filter];
        return false;
    }
    
    public function getFilter ($filter_name)
    {
        if($this->isFilter($filter_name))
            return $this->_filters[$filter_name];
    }

    /**
     * update 
     *
     * alias on save
     *
     * @param boolean $validate
     *   
     * @return boolean
     */
    public function update ($validate = true)
    {
        return $this->save($validate);
    }

    /**
     * delete 
     *
     * delete row corresponding to the models pkey
     * 
     * @return boolean
     */
    public function delete ($cascading_delete = false)
    {
        if($cascading_delete !== false)
        {
            $has_many_to_delete = $cascading_delete === true ? array_keys($this->_has_many): $cascading_delete;
            foreach($has_many_to_delete as $to_delete)
            {
                foreach($this->getManyForeignObjects($to_delete) as $to_unjoin)
                {
                    $this->unJoin($to_delete, $to_unjoin->{$to_unjoin->_pkey});
                }
            }
        }
        return SqlTools::sqlQuery($this->createDeleteSql());
    }

    /**
     * loadByPKey 
     * 
     * called by __construct
     * fills _fields and _original arrays with row's values
     *
     * @access private
     * @throws NoPrimaryKeySqlException
     * @param mixed $pkey 
     * @return void
     */
    private function loadByPKey ($pkey)
    {
        $rs = SqlTools::sqlQuery("select * from `".$this->_table."` ".$this->createIdentifyingWhereSql($pkey)); 
        if($rs && mysql_num_rows($rs) > 0) 
        {
            $this->_original = mysql_fetch_assoc($rs);
            foreach($this->table_desc as $field => $field_desc)
            {
                settype($this->_original[$field], $field_desc->php_type);
            }
            $this->_fields = $this->_original; 
        }
        else
            throw new NoPrimaryKeySqlException($pkey, $this->_table);
    }

    /**
     * loadFromArray
     *
     * called by __construct
     * fills _fields and _original arrays with row's values
     * load Foreign Objects associated to this if needed
     * 
     * @access private
     * @param array $array 
     * @return void
     */
    private function loadFromArray ($array)
    {
        $foreign_to_load = array();
        foreach($array as $field => $value)
        {
            $matches = explode(MormConf::MORM_SEPARATOR, $field);
            if($matches[0] != $field)
            {
                if($this->isForeignTable($matches[1]))
                {
                    $f_key = $this->getForeignKeyFromTable($matches[1]);
                    $foreign_to_load[$f_key][$matches[2]] = $value;
                }
                else if($matches[1] == $this->_table)
                    $field = $matches[2];
            }
            if($this->isField($field))
            {
                $field_desc = $this->getFieldDesc($field);
                $this->_original[$field] = $value;
                settype($this->_original[$field], $field_desc->php_type);
            }
        }   
        foreach($foreign_to_load as $f_key => $to_load)
            $this->loadForeignObject($f_key, $to_load);
        $this->_fields = $this->_original; 
    }

    /**
     * associateWithMormons 
     * 
     * @param Mormons $mormons
     * @return void
     */
    public function associateWithMormons(Mormons $mormons)
    {
        if(!is_null($this->_associated_mormons)) throw new Exception("A model can only be associated with one Mormons instance");
        $this->_associated_mormons = $mormons;
    }

    /**
     * loadFromMormons 
     * 
     * @todo documentation
     * @param array $array 
     * @return void
     */
    public function loadFromMormons ($array)
    {
        $foreign_to_load = array();
        $foreign_mormons_to_load = array();
        foreach($array as $field => $value)
        {
            $matches = explode(MormConf::MORM_SEPARATOR, $field);
            if($matches[0] != $field)
            {
                if($this->isForeignTable($matches[1]))
                {
                    $f_key = $this->getForeignKeyFromTable($matches[1]);
                    $foreign_to_load[$f_key][$matches[2]] = $value;
                }
                else if($this->isForeignMormons($matches[1]))
                {
                    $foreign_mormons_to_load[$matches[1]][$matches[2]] = $value;
                }
                else if($matches[1] == $this->_table)
                {
                    $field_desc = $this->getFieldDesc($matches[2]);
                    $this->_original[$matches[2]] = $value;
                    settype($this->_original[$matches[2]], $field_desc->php_type);
                    $this->_fields[$matches[2]] = $this->_original[$matches[2]]; 
                }
            }
            if($this->isField($field))
            {
                $field_desc = $this->getFieldDesc($field);
                $this->_original[$field] = $value;
                settype($this->_original[$field], $field_desc->php_type);
                $this->_fields[$field] = $this->_original[$field]; 
            }
        }   
        foreach($foreign_to_load as $f_key => $to_load)
            $this->loadForeignObject($f_key, $to_load);
        $this->_fields = $this->_original; 
    }

    /**
     * setFromArray 
     *
     * sets the models values using a hash
     * the hash does not need to have every field values set.
     * 
     * @param array $array
     * @return void
     */
    public function setFromArray ($array)
    {
        foreach($array as $field => $value)
        {
            if($this->isField($field))
                $this->$field = $value;
        }   
    }

    /**
     * isForeignKey 
     *
     * check if the given field has been declared as a foreign key
     * 
     * @param string $field 
     * @return boolean
     */
    public function isForeignKey ($field)
    {
        if($this->isField($field))
        {
            return isset($this->_foreign_keys[$field]);
        }
        else
            return false;
    }

    /**
     * isForeignClassFromField 
     * 
     * check if the given field has been declared as a class_from_field
     * that means that the class to load for that field is the value of that 
     * field in the row
     * typically used for an (object_type, object_id) couple used for social 
     * actions 
     *
     * @param string $field 
     * @return boolean
     */
    public function isForeignClassFromField ($field)
    {
        foreach($this->_foreign_keys as $key => $val)
            if(isset($val['class_from_field']) && $val['class_from_field'] == $field)
                return true;
        return false;
    }

    /**
     * isForeignTable 
     *
     * check if the given table name has been declared as a foreign table.
     * Either with "table" key in the foreign keys declaration
     * or through a "class_from_field" key if the corresponding field has been 
     * set 
     * 
     * @param string $table
     * @return boolean
     */
    public function isForeignTable ($table)
    {
        foreach($this->_foreign_keys as $key => $val)
        {
            if(isset($val['table']) && $val['table'] == $table)
                return true;
            //FIXME check differently
            //else if(isset($val['class_from_field']) && !empty($this->_fields[$val['class_from_field']]))
            //{
            //    var_dump($table);
            //    //@todo cache that dummy
            //    $class = $this->getForeignClassFromField($val['class_from_field']);
            //    $dummy = new $class();
            //    if($table == $dummy->_table) return true;
            //}
        }
        return false;
    }
    
    public function isForeignUsingTable ($table)
    {
        foreach($this->_has_many as $key => $val)
        {
            if(isset($val['using']) && isset($val['using'][$table]))
                return true;
        }
        return false;
    }

    /**
     * isForeignAlias 
     *
     * check if the given string has been declared as an alias for a foreign key
     *
     * @param string $alias 
     * @return boolean
     */
    public function isForeignAlias ($alias)
    {
        foreach($this->_foreign_keys as $key => $val)
            if(isset($val['alias']) && $val['alias'] == $alias)
                return true;
        return false;
    }

    /**
     * isForeignMormons 
     * 
     * check if the given string has been declared as a key in the _has_many hash 
     *
     * @param string $alias_or_table 
     * @return boolean
     */
    public function isForeignMormons ($alias_or_table)
    {
        return isset($this->_has_many[$alias_or_table]);
    }

    /**
     * getForeignKeys 
     * 
     * gives the declared foreign_keys
     *
     * @return array
     */
    public function getForeignKeys ()
    {
        return array_keys($this->_foreign_keys);
    }

    public function getForeignKeyFrom($table_or_alias)
    {
        if($this->isForeignAlias($table_or_alias))
        {
            return $this->getForeignKeyFromAlias($table_or_alias);
        }
        else if($this->isForeignUsingTable($table_or_alias))
        {
//            return $this->getForeignKeyFromUsingTable($table_or_alias);
//            return $this->getForeignMormonsUsingKey($table_or_alias);
            return $this->_pkey;
        }
        else
        {
            return $this->getForeignKeyFromTable($table_or_alias);
        }
    }

    /**
     * getForeignKeyFromTable 
     * 
     * tries to find the field declared as a foreign key bound on the given 
     * table name and return it
     *
     * @param string $table
     * @return string
     * @throws Exception if foreign table is not for specified model
     */
    public function getForeignKeyFromTable ($table)
    {
        foreach($this->_foreign_keys as $field => $val)
        {
            if(isset($val['table']) && $val['table'] == $table)
                return $field;
            else if(isset($val['class_from_field']) && !empty($this->_fields[$val['class_from_field']]))
            {
                //@todo cache that dummy
                $class = $this->getForeignClassFromField($val['class_from_field']);
                $dummy = new $class();
                if($table == $dummy->_table) return $field;
            }
        }
        //FIXME get the key from foreign model instead of returning this->_pkey 
        if($this->isForeignMormons($table)) return $this->_pkey;
        throw new Exception($table.' is not a foreign table for the model '.get_class($this));
    }

    /**
     * getForeignKeyFromAlias 
     *
     * tries to find the field declared as a foreign key bound on the given 
     * alias and return it
     * 
     * @param string $alias 
     * @return string
     * @throws Exception if the given alias is not declared in the model
     */
    public function getForeignKeyFromAlias ($alias)
    {
        foreach($this->_foreign_keys as $field => $val)
            if(isset($val['alias']) && $val['alias'] == $alias) return $field;
        //FIXME get the key from foreign model instead of returning this->_pkey 
        if($this->isForeignMormonss($alias)) return $this->_pkey;
        throw new Exception($alias.' is not a foreign alias for the model '.get_class($this));
    }

    public function getForeignKeyFromUsingTable($table_or_alias)
    {
        if(!$this->isForeignUsingTable($table_or_alias))
            throw new Exception(get_class($this).' does not use '.$table_or_alias.' indirectly');
        foreach($this->_has_many as $key => $val)
        {
            if(isset($val['using']) && isset($val['using'][$table_or_alias]))
            {
                $class_name = MormConf::generateMormClass($this->getForeignMormonsTable($key));
                continue;
            }
        }
        $dummy = new $class_name();
        return $dummy->getForeignMormonsUsingKey($table_or_alias);
    }

    /**
     * getForeignTable 
     *
     * try to find the foreign table for the given field, assuming that this 
     * field has been declared as a foreign key
     * 
     * @param string $field 
     * @throws Exception if the field does not exist into table
     * @return string
     */
    public function getForeignTable ($field)
    {
        if($this->isForeignKey($field))
        {
            if(isset($this->_foreign_keys[$field]['class_from_field']))
            {
                //@todo cache that dummy
                $class = $this->getForeignClassFromField($this->_foreign_keys[$field]['class_from_field']);
                $dummy = new $class();
                return $dummy->_table;
            }
            return $this->_foreign_keys[$field]['table'];
        }
        else
            throw new Exception($field.' is not a foreign key in table '.$this->_table);
    }

    /**
     * getForeignTableFromAlias 
     *
     * try to find the foreign table for the given alias, assuming that this 
     * alias has been declared as a foreign alias
     * 
     * @param string $alias 
     * @throws Exception if the alias is not defined in the model
     * @throws MormImpossibleTableGuessException if the table could not be 
     * defined
     * @return string
     */
    public function getForeignTableFromAlias ($alias)
    {
        if($this->isForeignAlias($alias))
        {
            foreach($this->_foreign_keys as $key => $val)
                if(isset($val['alias']) && $val['alias'] == $alias)
                {
                    if(isset($this->_foreign_keys[$key]['table']))
                        return $this->_foreign_keys[$key]['table'];
                    else if(isset($this->_foreign_keys[$key]['class_from_field']) && isset($this->_fields[$this->_foreign_keys[$key]['class_from_field']]))
                    {
                        //@todo cache that dummy
                        $class = $this->getForeignClassFromField($this->_foreign_keys[$key]['class_from_field']);
                        $dummy = new $class();
                        return $dummy->_table;
                    }
                    else
                        throw new MormImpossibleTableGuessException('can not define table from alias \'' . $alias . '\'');
                }
        }
        else
            throw new Exception($alias.' is not a foreign alias in table '.$this->_table);
    }

    /**
     * getForeignTableKey 
     *
     * tries to guess the foreign table key for the given field
     * either by taking the declared one in the foreign_keys hash
     * or by returning the primary key of the foreign table
     * 
     * @param string $field 
     * @throws Exception if field is not a foreign key in the table
     * @return string
     */
    public function getForeignTableKey ($field)
    {
        if($this->isForeignKey($field))
        {
            if(isset($this->_foreign_keys[$field]['key']))
                return $this->_foreign_keys[$field]['key'];
            else
                return TableDesc::getTable($this->getForeignTable($field))->getPKey();
        }
        else
            throw new Exception($field.' is not a foreign key in table '.$this->_table);
    }

    /**
     * getForeignMormonsKey 
     *
     * tries to guess the field that should be used as a key to link the foreign 
     * table with the model. 
     * if a key is defined in the has_many hash, it is returned
     * else return this model's primary key
     * 
     * @param string $alias_or_table 
     * @throws Exception if the given alias is not defined as a foreign object 
     * @return string
     */
    public function getForeignMormonsKey ($alias_or_table)
    {
        if($this->isForeignMormons($alias_or_table))
        {
            if(isset($this->_has_many[$alias_or_table]['using']))
            {
                $ret = array();
                foreach($this->_has_many[$alias_or_table]['using'] as $using_alias => $to_set)
                    $ret[$using_alias] = $to_set['key'];
                return $ret;
            }
            return isset($this->_has_many[$alias_or_table]['key']) ? $this->_has_many[$alias_or_table]['key'] : $this->_pkey;
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    public function getForeignMormonsUsingKey ($alias_or_table)
    {
        if($this->isForeignUsingTable($alias_or_table))
        {
            foreach($this->_has_many as $key => $val)
            {
                if(isset($val['using']) && isset($val['using'][$alias_or_table]))
                    return $val['using'][$alias_or_table]['key'];
            }
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    /**
     * getForeignMormonsTable 
     * 
     * tries to guess the name of the table that should be used to join the 
     * model with the given alias defined in the has_many hash
     *
     * @param string $alias_or_table 
     * @throws Exception if the given alias is not defined as a foreign object 
     * @return string
     */
    public function getForeignMormonsTable ($alias_or_table)
    {
        if($this->isForeignMormons($alias_or_table))
        {
            return isset($this->_has_many[$alias_or_table]['table']) ? $this->_has_many[$alias_or_table]['table'] : $alias_or_table;
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    /**
     * getForeignClass 
     *
     * tries to return the class that should be loaded as a foreign object
     * 
     * @param string $field 
     * @throws Exception if field is not a foreign key into the table
     * @return string a valid class name
     */
    public function getForeignClass ($field)
    {
        if($this->isForeignKey($field))
        {
            if(isset($this->_foreign_keys[$field]['alias']))
            {
                if(isset($this->_foreign_keys[$field]['class_from_field']))
                    return $this->getForeignClassFromField($this->_foreign_keys[$field]['class_from_field']);
                else if(MormConf::isInConf($this->_foreign_keys[$field]['alias']))
                    $table_name = $this->_foreign_keys[$field]['alias'];
                else
                    $table_name = $this->_foreign_keys[$field]['table'];
            }
            else
                $table_name = $this->_foreign_keys[$field]['table'];
            return MormConf::generateMormClass($table_name);
        }
        else
            throw new Exception($field.' is not a foreign key in table '.$this->_table);
    }

    /**
     * getForeignClassFromField 
     * 
     * tries to return the class that should be loaded as a foreign object using 
     * the class_form_field statement in the foreign_key hash
     *
     * @access private
     * @param string $field 
     * @throws exception if the corresponding field is empty
     * @return string a valid class name
     */
    private function getForeignClassFromField($field)
    {
        if(empty($this->_fields[$field]))
            throw new Exception('Could not retrieve Foreign class from field '.$field);
        return MormConf::generateMormClass($this->_fields[$field]);
    }

    /**
     * getForeignObject 
     *
     * load and cache the foreign object corresponding to the given field
     * if the object has already been loaded, it is returned from cache
     * 
     * @param string $field 
     * @throws Exception if field is not a foreign key into the table
     * @return object Morm Model
     */
    public function getForeignObject ($field)
    {
        
        if($this->isForeignKey($field))
        {
            if(!isset($this->_foreign_object[$field]))
            {
                $this->loadForeignObject($field);
            }
            return $this->_foreign_object[$field];
        }
        else
            throw new Exception($field.' is not a foreign key in table '.$this->_table);
    }

    /**
     * getManyForeignObjects 
     * 
     * load and cache the foreign models corresponding to the given field
     * if the object has already been loaded, it is returned from cache
     *
     * @param string $alias_or_table 
     * @throws exception if the given parameter has not been declared in the 
     * has_many hash
     * @return Mormons 
     */
    public function getManyForeignObjects ($alias_or_table)
    {
        if($this->isForeignMormons($alias_or_table))
        {
            if(!isset($this->_foreign_mormons[$alias_or_table]))
            {
                $this->loadForeignMormons($alias_or_table);
            }
            return $this->_foreign_mormons[$alias_or_table];
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    /**
     * loadForeignObject 
     * 
     * try to load the foreign object identified by the $field parameter.
     * either by requesting the database
     * or using the second parameter to load an Morm object
     * or use the second paramter as the foreign object itself and link it only
     * 
     * @param string $field 
     * @param mixed $to_load (NULL, array or Morm object) 
     * @throws Exception if foreign object cannot be loaded
     * @throws Exception if field is not a foreign key into the table
     * @return void
     */
    public function loadForeignObject ($field, $to_load = null)
    {
        if($this->isForeignKey($field))
        {
            $foreign_class = $this->getForeignClass($field);
            if(is_null($to_load))
            {
                //FIXME remove quotes when not needed
                //use a getFor or FindBy fonction
                $sql = "select * from `".$this->getForeignTable($field)."` where `".$this->getForeignTableKey($field)."`='".$this->$field."'";
                $rs = SqlTools::sqlQuery($sql);
                if($rs && mysql_num_rows($rs) != 0)
                {
                    $to_load = mysql_fetch_assoc($rs);
                }
                else
                   throw new MormNoForeignObjectToLoadException($field);
            }
            if(is_array($to_load))
                $this->_foreign_object[$field] = self::Factory($foreign_class, $to_load);
            else if (is_object($to_load) && $to_load->is_a($foreign_class))
                $this->_foreign_object[$field] = $to_load;
            else
                throw new Exception('Could not load foreign object for field '.$field.': wrong data to load');
        }
        else
            throw new Exception($field.' is not a foreign key in table '.$this->_table);
    }

    /**
     * loadForeignMormons 
     * 
     * try to load the foreign objects identified by the $alias_or_table parameter by requesting the database
     *
     * @throws Exception
     * @param string $alias_or_table 
     * @param mixed $to_load (NULL or Mormons object) FIXME strange never 
     * used parameter ?
     * @return void
     */
    public function loadForeignMormons ($alias_or_table, $to_load = null)
    {
        if($this->isForeignMormons($alias_or_table))
        {
            $table = $this->getForeignMormonsTable($alias_or_table);
            if(is_null($to_load))
            {
                $mormons = new Mormons($table);
                if(isset($this->_has_many[$alias_or_table]['using']))
                    $this->addForeignMormonsUsingStatement($alias_or_table, $mormons);
                else
                {
                    //TODO manage multiple primary keys
                    $mormons->add_conditions(array($this->getForeignMormonsKey($alias_or_table) => $this->{$this->_pkey}));
                }
                if(isset($this->_has_many[$alias_or_table]['condition']))
                    $mormons->add_conditions($this->_has_many[$alias_or_table]['condition']);
                if(isset($this->_has_many[$alias_or_table]['sql_where']))
                    $mormons->set_sql_where($this->_has_many[$alias_or_table]['sql_where']);
                if(isset($this->_has_many[$alias_or_table]['order']))
                {
                    foreach($this->_has_many[$alias_or_table]['order'] as $field => $direction)
                    {
                        $mormons->set_order($field); 
                        $mormons->set_order_dir($direction); 
                    }
                }
                //TODO manage multiple primary keys
                $mormons->associateForeignObject($this->getForeignMormonsKey($alias_or_table), &$this);
                $this->_foreign_mormons[$alias_or_table] = $mormons;
            }
            else
            {
                if(!isset($this->_foreign_mormons[$alias_or_table]))
                    $this->loadForeignMormons($alias_or_table);
                $this->_foreign_mormons[$alias_or_table]->addMormFromArray($table, $to_load);
            }
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    /**
     * addForeignMormonsUsingStatement 
     *
     * sets the join statement and other things for a has_many mormons using the "using" hash in 
     * the $alias_or_table has_many hash
     *
     * @access private
     * @param string $alias_or_table 
     * @param Mormons $mormons (reference)
     * @return void
     */
    private function addForeignMormonsUsingStatement ($alias_or_table, &$mormons)
    {
        foreach($this->_has_many[$alias_or_table]['using'] as $using_alias => $to_set)
        {
            $mormons->set_join($using_alias);
            if(!isset($to_set['table']))
            {
                $dummy_class = MormConf::generateMormClass($using_alias);
                $dummy = new $dummy_class();
                $table = $dummy->_table;
            }
            else
                $table = $to_set['table'];
            $mormons->add_conditions(array($to_set['key'] => $this->{$this->_pkey}), $table);
            if(isset($to_set['condition']))
                $mormons->add_conditions($to_set['condition'], $table);
        }
    }

    /**
     * @throws Exception
     * @param string $table
     * @param mixed $to_load
     */
    /**
     * loadForeignObjectFromMormons 
     * 
     * same as loadForeignObject but from an associated mormon so that the 
     * objects keep a reference between themselves
     *
     * @throws exception if the given alias_or_table is not declared in the 
     * has_many hash
     * @param string $alias_or_table 
     * @param array $to_load 
     * @return void
     */
    public function loadForeignObjectFromMormons ($alias_or_table, $to_load = null)
    {
        if($this->isForeignMormons($alias_or_table))
        {
            $table = $this->getForeignMormonsTable($alias_or_table);
            if(is_null($to_load))
            {
                $mormons = new Mormons($table);
                //TODO manage multiple primary keys
                $mormons->add_conditions(array($this->getForeignMormonsKey($alias_or_table) => $this->{$this->_pkey}));
                if(isset($this->_has_many[$alias_or_table]['condition']))
                    $mormons->add_conditions($this->_has_many[$alias_or_table]['condition']);
                if(isset($this->_has_many[$alias_or_table]['order']))
                {
                    foreach($this->_has_many[$alias_or_table]['order'] as $field => $direction)
                    {
                        $mormons->set_order($field); 
                        $mormons->set_order_dir($direction); 
                    }
                }
                $mormons->associateForeignObject($this->_pkey, &$this);
                $this->_foreign_mormons[$alias_or_table] = $mormons;
            }
            else
            {
                if(!isset($this->_foreign_mormons[$alias_or_table]))
                    $this->loadForeignObjectFromMormons($alias_or_table);
                $this->_foreign_mormons[$alias_or_table]->addMormFromArray($table, $to_load, &$this);
            }
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    /**
     * getForeignValues 
     *
     * only used by the scaffolding, should probably be removed or extended to 
     * make it more generic 
     * 
     * @throws Exception if field is not a foreign key
     * @param mixed $field 
     * @param array $foreign_fields (can be null))
     * @param mixed $conditions 
     * @return array
     */
    public function getForeignValues ($field, $foreign_fields = NULL, $conditions = NULL)
    {
        if($this->isForeignKey($field))
        {
            if(!isset($this->_foreign_values[$field]))
            {
                $select = is_null($foreign_fields) ? '*' : '`'.implode('`,`', $foreign_fields).'`';
                $conditions = is_null($conditions) ? '' : $conditions;
                $sql = "select ".$select." from `".$this->getForeignTable($field)."` ".$conditions;
                $rs = SqlTools::sqlQuery($sql);
                $foreign_values = array();
                while($line = mysql_fetch_assoc($rs))
                    $foreign_values[] = $line;
                $this->_foreign_values[$field] = $foreign_values;
            }
            return $this->_foreign_values[$field];
        }
        else
            throw new Exception($field.' is not a foreign key in table '.$this->_table);
    }

    /**
     * fillDefaultValues 
     *
     * takes the default values from the table structure and fill the 
     * corresponding fields with them
     * 
     * @return void
     */
    public function fillDefaultValues()
    {
        foreach($this->table_desc as $field_name => $field_desc)
        {
            if($this->hasDefaultValue($field_name) && $this->isEmpty($this->$field_name))
                $this->$field_name = $this->getDefaultValue($field_name);
        }
    }

    /**
     * getTableDesc 
     *
     * returns the TableDesc of the model's table
     * 
     * @return TableDesc
     */
    public function getTableDesc ()
    {
        return TableDesc::getTable($this->_table); 
    }

//    public function getFields ()
//    {
//        return $this->table_desc->getFields(); 
//    }

    /**
     * getHasManyStatements 
     *
     * return the declared ha_many statements
     * 
     * @return array
     */
    public function getHasManyStatements()
    {
        return $this->_has_many;
    }

    /**
     * getFieldDesc 
     * 
     * returns the FieldDesc of the given field
     *
     * @throws Exception if field is not into table
     * @param string $field 
     * @return FieldDesc
     */
    public function getFieldDesc ($field)
    {
        if($this->isField($field))
        {
            return $this->table_desc->$field;
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * validate 
     *
     * try to validate the fields values using the field types and restrictions 
     * from the database or the defined validation methods if they exist.
     * These validation methods should be named after the following pattern:
     * "validate<Field_name>" where <Field_name> is the name of the field to 
     * valide with its first caracter uppercased. The method must throw an MormFieldValidate if there 
     * is a validation error
     * 
     * @throws MormValidateException
     * @return boolean
     */
    public function validate()
    {
        foreach($this->table_desc as $field_name => $field_desc)
        {
            $error = false;
            $validate_method = 'validate'.ucfirst($field_name);
            if(method_exists($this, $validate_method))
            {
                try 
                {
                    $this->$validate_method();
                } 
                catch (MormFieldValidateException $e) 
                {
                    $error = $e->getMessage();
                }
            }
            else
            {
                $value = $this->$field_name;
                if(!$this->checkMandatory($field_name))
                    $error = isset($this->mandatory_errors[$field_name]) ? $this->mandatory_errors[$field_name] : "This field can not be empty";
                if($error === false && !$this->isEmpty($value) && !$this->checkTypeOf($field_name))
                    $error = isset($this->type_errors[$field_name]) ? $this->type_errors[$field_name] : "Wrong data type. This field is suppose to be a ".$field_desc->php_type;
            }
            if($error)
                $this->_errors[$field_name] = $error;
        }
//        if(empty($this->_errors))
//            $this->fillDefaultValues();
        if(!empty( $this->_errors) )
        {
            throw new MormValidateException($this->_errors);
        }

        return empty($this->_errors);
    }

    /**
     * checkTypeOf 
     *
     * check the value type of the given field according to the table 
     * description
     * 
     * @param string $field
     * @throws Exception if field is not into table
     * @return boolean
     */
    public function checkTypeOf($field)
    {
        if($this->isField($field))
        {
            $field_desc = $this->getFieldDesc($field);
            if($field_desc->isPrimary() && $this->hasAutoIncrement() && $this->isEmpty($this->$field))
                return true;
            if($field_desc->isNumeric() && !is_numeric($this->$field))
                return false;
            if($field_desc->php_type == 'integer' && !$this->isInteger($this->$field)) 
                return false;
            if($field_desc->php_type == 'float' && !$this->isFloat($this->$field)) 
                return false;
            if($field_desc->php_type == 'string' && !is_string($this->$field)) 
                return false;
            return true;
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * checkMandatory 
     * 
     * check if the given field has a value and return false if it's but should 
     * not
     *
     * @param string $field
     * @throws Exception if field is not into table
     * @return boolean
     */
    public function checkMandatory($field)
    {
        if($this->isField($field))
        {
            $value = $this->$field;
            return !($this->isMandatory($field) && $this->isEmpty($value));
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * isMandatory 
     *
     * returns true if the given field can not be null and has no default value
     * 
     * @param string $field
     * @throws Exception if field is not into table
     * @return boolean
     */
    public function isMandatory($field)
    {
        if($this->isField($field))
        {
            $field_desc = $this->getFieldDesc($field);
            if($field_desc->isPrimary() && $this->hasAutoIncrement())
                return false;
            return $field_desc->Null == 'NO' && !$this->hasDefaultValue($field);
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * getErrors 
     * 
     * returns the _errors array
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * hasError 
     *
     * check the _errors array for values
     * 
     * @return boolean
     */
    public function hasError()
    {
        return (count($this->_errors)>0);
    }

    /**
     * castFields 
     *
     * force the php types of the fields values according to those defined in the 
     * database table description
     * 
     * @return void
     */
    public function castFields()
    {
        foreach($this->table_desc as $field_name => $field_desc)
        {
            if($field_desc->php_type == 'integer' && $this->isEmpty($this->$field_name))
                $this->_fields[$field_name] = NULL;
            else if(isset($this->_fields[$field_name]) && !is_null($this->_fields[$field_name]))
                settype($this->_fields[$field_name], $field_desc->php_type);
        }
    }

    /**
     * setPKey 
     *
     * called from the constructor
     * set the _pkey member by getting it from the table description
     * and return it
     * this value may be a string or an array if the table's primary key is a 
     * multiple one
     * 
     * @todo throw exception if table !isset
     * @return mixed (array or string)
     */
    private function setPKey()
    {
        if(is_null($this->_pkey))
        {
            $this->_pkey = $this->table_desc->getPKey();
        }
        return $this->_pkey;
    }

    /**
     * getPkey 
     *
     * returns the field name (or names as an array) of the table's primary key.
     * if $to_string is set to true (used specially for mormons object 
     * identification) and table has multiple primary key, returns a string with 
     * the rpimary keys names separated with the defined MORM_SEPARATOR
     * 
     * @param boolean $to_string
     * @return mixed
     */
    public function getPkey($to_string = false)
    {
        if(is_null($this->_pkey) && $to_string)
            return '';
        if(is_array($this->_pkey) && $to_string)
        {
            return implode(MormConf::MORM_SEPARATOR, $this->_pkey);
        }
        return $this->_pkey;
    }

    /**
     * getPkeyVal 
     * 
     * returns the value of the primary key
     * returns an hash of values if multiple primary keys
     * returns null if no primary key defined in the table
     *
     * @return mixed
     */
    public function getPkeyVal()
    {
        if(is_null($this->_pkey))
            return NULL;
        if(is_array($this->_pkey))
        {
            $ret = array();
            foreach($this->_pkey as $key)
            {
                $ret[$key] = $this->$key;
            }
        }
        else
            $ret = $this->{$this->_pkey};
        return $ret;
    }

    /**
     * isField 
     *
     * check if the given parameter is actually a field name in the model's table
     * 
     * @access protected
     * @param string $field
     * @return boolean
     */
    protected function isField ($field)
    {
        $td = $this->getTableDesc();
        return $td->isField($field);   
    }

    /**
     * hasDefaultValue 
     *
     * check if the given field name has a defined default value in the table's 
     * description
     * 
     * @access private
     * @param string $field
     * @return boolean
     */
    private function hasDefaultValue ($field)
    {
        $field_desc = $this->getFieldDesc($field);
        if($field_desc->Null == 'YES')
            return true;
        return !$this->isEmpty($field_desc->Default);
    }

    /**
     * getDefaultValue 
     *
     * return the default value defined in the table's description for the given 
     * field name
     * 
     * @access private
     * @param string $field
     * @return string
     */
    private function getDefaultValue ($field)
    {
        $field_desc = $this->getFieldDesc($field);
        return $field_desc->Default;   
    }

    /**
     * isEmpty 
     *
     * tries to reproduce php's "empty()" function's behaviour in a "not stupid" 
     * way
     *
     * @fixme strlen may be replaced by isset($str[0]) for better performance if 
     * we are sure it has the same effect
     * 
     * @access private
     * @param mixed 
     * @return boolean
     */
    private function isEmpty($val)
    {
        if(is_string($val) && strlen($val) == 0)
            return true;
        if(is_numeric($val) && intval($val) == 0)
            return false;
        return empty($val);
    }

    /**
     * isFLoat 
     *
     * tries to be a little less stupid than php's "is_float()" function
     * 
     * @access private
     * @param mixed $val 
     * @return void
     */
    private function isFLoat($val)
    {
        if(is_bool($val))
            return false;
        $f_val = floatval($val);
        $s_val = strval($val);
        return strval($f_val) === $s_val;
    }


    /**
     * isInteger 
     *
     * tries to be a little less stupid than php's "is_int()" function
     * 
     * @access private
     * @param mixed $val 
     * @return boolean
     */
    private function isInteger($val)
    {
        if(is_bool($val))
            return false;
        $f_val = intval($val);
        $s_val = strval($val);
        return strval($f_val) === $s_val;
    }


    /**
     * hasAutoIncrement 
     *
     * check if the model's table has an auto incrment field
     * 
     * @access private
     * @return boolean
     */
    private function hasAutoIncrement ()
    {
        return $this->table_desc->hasAutoIncrement();   
    }

    /**
     * fieldsToInsert 
     * 
     * returns a hash with the fields which values need to be inserted in the 
     * database
     *
     * @access private
     * @return array
     */
    private function fieldsToInsert ()
    {
        $to_insert = $this->_fields;
        foreach($this->table_desc as $field_name => $field_desc)
        {
            if($field_desc->isPrimary() && $this->hasAutoIncrement() && $this->isEmpty($this->$field_name))
                unset($to_insert[$field_name]);
            if($this->hasDefaultValue($field_name) && $this->isEmpty($this->$field_name) && in_array($field_name, array_keys($to_insert)))
                unset($to_insert[$field_name]);
        }
        return $to_insert;
    }

    /**
     * createInsertSql 
     *
     * build the insert sql query for the model and return it
     *
     * @access private
     * @return string
     */
    private function createInsertSql ()
    {
        $to_insert = $this->fieldsToInsert();
        return "insert into `".$this->_table."` ".SqlBuilder::set($to_insert);
    }

    /**
     * fieldsToUpdate 
     * 
     * returns a hash with the fields which values need to be updated in the 
     * database
     * this is done by comparing the original values with the new ones. (diff 
     * betweeen the _original and _fields hash)
     *
     * @access private
     * @return array
     */
    private function fieldsToUpdate ()
    {
        $to_update = array_diff_assoc($this->_fields, $this->_original);
        foreach($this->table_desc as $field_name => $field_desc)
        {
            if($field_desc->isPrimary() && $this->hasAutoIncrement() && $this->isEmpty($this->$field_name))
                unset($to_update[$field_name]);
            //else if($this->hasDefaultValue($field_name) && $this->isEmpty($this->$field_name) && in_array($field_name, array_keys($to_update)))
            //    unset($to_update[$field_name]);
        }
        return $to_update;
    }

    /**
     * createUpdateSql 
     *
     * build the update sql query for the model and return it
     *
     * @access private
     * @return string
     */
    private function createUpdateSql ()
    {
        $set=array();
        $to_update = $this->fieldsToUpdate();
        foreach($to_update as $key => $value)
        {
            $set[] = "`".$key."`=".SqlTools::formatSqlValue($value);
        }
        $set = implode(',', $set);
        return "update `".$this->_table."` set ".$set.$this->createIdentifyingWhereSql(); 
    }

    /**
     * createDeleteSql 
     *
     * build the delete sql query for the model and return it
     * 
     * @access private
     * @return string
     */
    private function createDeleteSql ()
    {
        return "delete from `".$this->_table."` ".$this->createIdentifyingWhereSql(); 
    }

    /**
     * createIdentifyingWhereSql 
     *
     * build the sql where statement used to get the model's corresponding row or 
     * the given key's one and return it
     * 
     * @param mixed $not_yet_loaded_key 
     * @return string
     */
    private function createIdentifyingWhereSql($not_yet_loaded_key = null)
    {
        $pkey = is_null($not_yet_loaded_key) ? $this->getPkeyVal() : $not_yet_loaded_key;
        if(is_array($this->_pkey))
        {
            $req = array();
            foreach($this->_pkey as $key)
            {
                $req[] = '`'.$this->_table.'`.`'.$key.'` = '.SqlTools::formatSqlValue($pkey[$key]);
            }
            $where = " where ".implode(' AND ', $req); 
        }
        else
            $where = ' where `'.$this->_table.'`.`'.$this->_pkey."`=".SqlTools::formatSqlValue($pkey); 
        return $where;
    }

    /**
     * castTo 
     *
     * use this with caution
     * cast a Morm model to another Morm model.
     * This function uses a bad php hack and can therefore only get a class name 
     * that is an instance of this
     * 
     * @param string $class 
     * @return void
     */
    public function castTo($class)
    {
        
        if(!class_exists($class)) {
            trigger_error("The object can't be casted : the class '{$class}' doesn't exist", E_USER_ERROR);
        }
        $this_class = get_class($this);
        if(!is_subclass_of($class,$this_class)) {
            trigger_error("The object can't be casted : '{$class}' is not a subclass of '{$this_class}'", E_USER_ERROR);
        }
        $str = serialize($this);
        $str = preg_replace('/^O:\d*:"' . $this_class . '"/','O:' . strlen($class) . ':"' . $class . '"',$str);
        
        $instance = unserialize($str);
        
        if(method_exists($instance, 'prepare')) {
            $instance->prepare();
        }
        
        return $instance;
    }


    public function getSame()
    {
        $morm = new self();
        $values = $this->_fields;
        if(is_array($this->_pkey))
        {
            foreach($this->_pkey as $value)
                unset($values[$value]);
        }
        else
            unset($values[$this->_pkey]);
        $morm->setFromArray($values);
        return $morm;
    }

    public function getStiField()
    {
        /**
         * I may have a 'type' field in this model but I don't want to use it as a 
         * STI 
         */
        if($this->sti_field === NULL) return NULL;
        /**
         * morm uses the 'type' field for STI but don't be stupid and throw an 
         * exception if there isn't any 'type' field for the model
         */
        if($this->sti_field == 'type' && !$this->isField($this->sti_field)) return NULL;
        /**
         * Using 'type' for STI is bad (or not, or was already used for something 
         * else on this model) but now I want to use Morm's cool STI feature on it 
         * and let it guess the class name from another field.
         */
        if($this->isField($this->sti_field)) return $this->sti_field;
        /**
         * come on, the field I defined for sti does not even exist in the table.
         * Silly me. 
         */
        throw new Exception($this->sti_field.' is not a field of the table '.$this->_table.' and can therefore not be used as an sti field');
    }

    /**
     * is_a 
     * 
     * checks if $this is an instance of or inherits from the the given class 
     * name or given object's class name
     *
     * @param string|object $obj_or_class 
     * @return boolean
     */
    public function is_a($obj_or_class)
    {
        if (!is_object($obj_or_class) && !is_string($obj_or_class)) return false;
        $obj = is_object($obj_or_class) ? $obj_or_class : new $obj_or_class();//FIXME this will not work if the class constructor needs a parameter
        return ($this instanceof $obj);
    }

    /**
     * Factory 
     *
     * instantiate a Morm and loads it using the sti field if declared, needed 
     * and filled
     *
     * FIXME, maybe this could be used in FactoryFromMormons but to o that, Morm 
     * should be able to load an object from an Array as if it was given to the 
     * constructor
     * 
     * @param string $super_class top class of the STI
     * @params array $to_load array used to load the mmorm object
     * @return Morm
     */
    public static function Factory($super_class, $to_load)
    {
        $class = $super_class;
        $model = new $class();
        if($sti_field = $model->getStiField())
        {
            if(isset($to_load[$sti_field]) && !empty($to_load[$sti_field]))
            {
                $sti_class = MormConf::generateMormClass($to_load[$sti_field]);
                $sti_model = new $sti_class();
                if($sti_model->is_a($super_class)) 
                {
                    $class = $sti_class;
                    unset($sti_model);
                }                
                else throw new Exception('The class '.$sti_class.' is not a '.$super_class.' and could not be used as a sti model');
            }
            else
                throw new Exception('Could not guess the class "' . $class . '" to instantiate from this array, the sti field "' . $sti_field . '" wasn\'t there');
        }
        unset($model);
        return new $class($to_load);
    }

    /**
     * FactoryFromMormons 
     *
     * almost the same as Factory but does strange things useful for Mormons
     * 
     * @param string $super_class top class of the STI
     * @param Mormons $mormons mormons object to associate with the model
     * @params array $to_load array used to load the mmorm object
     * @access public
     * @return Morm
     */
    public static function FactoryFromMormons($super_class, &$mormons, $to_load)
    {
        $model = new $super_class();
        if($sti_field = $model->getStiField())
        {
            $sti_field_mormonized = 'morm'.MormConf::MORM_SEPARATOR.$model->_table.MormConf::MORM_SEPARATOR.$sti_field;
            if(isset($to_load[$sti_field_mormonized]) && !empty($to_load[$sti_field_mormonized]))
            {
                $sti_class = MormConf::generateMormClass($to_load[$sti_field_mormonized]);
                $sti_model = new $sti_class();
                if($sti_model->is_a($super_class)) $model = $sti_model;
                else throw new Exception('The class '.$sti_class.' is not a '.$super_class.' and could not be used as a sti model');
            }
            else
                throw new Exception('Could not guess the class to instantiate from this array, the sti field wasn\'t there');
        }
        $model->associateWithMormons($mormons);
        $model->loadFromMormons($to_load);
        return $model;
    }

    /**
     * plug
     *
     * load a Morm plugin
     *
     * @param string $plugin_name class name to load for your plugin
     * @param array $plugin_options optional array passed to the constructor when instanciating a plugin
     * @access public
     * @return boolean
     */
    public static function plug($name, $options=array())
    {
        // Silently plugins loaded twice
        if(isset(self::$_plugin[$name]))
            return true;

        // Try to load plugin source
        $plugin_file = dirname(__FILE__) . "/plugins/$name/$name.php";
        if(!file_exists($plugin_file))
            throw new Exception("Can't load plugin $name: source file is missing");

        require_once($plugin_file);

        // Check for conflicts between plugin methods
        $registered_methods = array_keys(self::$_plugin_method);
        $new_methods = call_user_func(array($name, 'extend_with'));
        foreach($new_methods as $new_method)
        {
            if(in_array($new_method, $registered_methods))
                throw new Exception("Can't load plugin $name: method conflict " .
                                    "with plugin: " . self::$_plugin_method[$method] .
                                    " ($new_method)");
        }

        // Register plugin and its methods
        self::$_plugin[$name] = $new_methods;
        foreach(self::$_plugin[$name] as $method)
            self::$_plugin_method[$method] = $name;

        // Register plugin constructor options
        self::$_plugin_options[$name] = $options;
    }
}

