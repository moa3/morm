<?php
/**
 * Mormons 
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class Mormons implements Iterator
{

    /**
     * @access private
     */
    private $mormons = null; 

    /**
     * @access private
     * @var integer
     */
    private $nb_mormons = null;

    /**
     * @access private
     */
    private $mormon_keys = null; 

    /**
     * @access private
     */
    private $key = 0;

    /**
     * @access private
     * @var array
     */
    private $tables = array();

    /**
     * @access private
     * @var array
     */
    private $select = array();

    /**
     * @access private
     * @var array
     */
    private $joins = array();

    /**
     * @access private
     * @var array
     */
    private $join_tables = array();

    /**
     * @access private
     * @var array
     */
    private $where = array();

    /**
     * @access private
     * @var string
     */
    private $sql_where = '';

    /**
     * @access private
     * @var array
     */
    private $order = array();

    /**
     * @access private
     * @var string
     */
    private $order_dir = 'DESC';

    /**
     * @access private
     * @var integer
     */
    private $offset = null;

    public $needRightElements = false;

    /**
     * @access private
     * @var integer
     */
    private $limit = null;

    /**
     * @access private
     * @var array
     */
    private $base_models = array();

    /**
     * @access private
     */
    private $base_table = null;

    private $_executed = false;

    private $_foreign_object_waiting_for_association = null;

    /**
     * __construct 
     *
     * Constructor for the Mormons.
     * The given parameter must be a table name and will be used as the base 
     * table name
     * 
     * @param string $init 
     * @return void
     */
    public function __construct ($init)
    {
        if(is_string($init)) //this is suppose to be a table name
        {
            $init = $this->add_table($init);
            $this->base_table = $this->base_models[$init]->_table;
        }
    }

    /**
     * __call 
     *
     * call the method if it exists
     * else if the method exists for the Morm objects in the $this->mormons 
     * array, call it for each of these object.
     * 
     * @fixme check this method, the foreach does not do what it is suppose to do
     *
     * @throws Exception if the method does not exist neither in Mormons 
     * nor in Morm or if no element present in the array
     * @param string $method name of the called method
     * @param array $arguments array of parameters for the method
     * @return mixed
     */
    public function __call ($method, $arguments)
    {
        if(method_exists($this, $method))
            return call_user_func_array(array($this, $method), $arguments);
        if(is_array($this->mormons))
        {
            foreach($this->mormons as $obj_id => $morm)
            {
                if(method_exists($morm, $method))
                    return call_user_func_array(array($morm, $method), $arguments);
                else
                    throw new Exception("The method ".$method." does not exist for ".get_class($morm)." object");
            }
            throw new Exception("No elements in this Mormons");
        }
        throw new Exception("The method ".$method." does not exist in Mormons class");
    }

    public function __get ($get)
    {
        if(is_numeric($get))
            return $this->getById($get);
        if($filter = $this->base_models[$this->base_table]->isFilter($get))
            return $this->getFilteredBy($filter);
        return NULL;
    }

    public function getById ($id)
    {
        if(!$this->_executed)
            $this->execute();
        $to_get = array($this->base_table => $id);
        if($this->is_loaded($to_get))
        {
            return $this->mormons[$this->base_table.'_'.$id];
        }
        else
            throw new Exception("The object identified by $id in table {$this->base_table} is not in this mormons");
    }

    public function getFilteredBy($filter)
    {
        $this->setFilter($filter);
        return $this;
    }

    protected function setFilter($filter)
    {
        foreach($filter as $type => $to_set)
            call_user_func_array(array($this, $type), $to_set);
    }

    /**
     * add_table 
     * 
     * 
     *
     * @param string $table 
     * @return string
     */
    public function add_table($table)
    {
        if($this->is_used_table($table)) throw new Exception("The table ".$table." already exists in this object");
        $class_name = MormConf::generateMormClass($table); 
        $base_object = new $class_name();
        $table = $base_object->_table;
        $this->tables[] = $table;
        $this->where[$table] = array();
        $this->base_models[$table] = $base_object;
        return $table;
    }

    /**
     * @param string $sql
     * @return void
     */
    public function set_sql_where($sql)
    {
        if(!is_string($sql)) throw new Exception("The where clause is suppose to be a string");
        if($this->sql_where != $sql)
        {
            $this->sql_where .= ' '.$sql;
            $this->_executed = false;
        }
    }

    public function where($sql)
    {
        return $this->set_sql_where($sql);
    }

    /**
     * set_join 
     * 
     * TODO take $type in account to define if join is RIGHT, LEFT, INNER, OUTER etc.
     *
     * @param mixed $table 
     * @param mixed $on 
     * @param string $type 
     * @return void
     */
    public function set_join($table_or_alias, $on = null, $type='LEFT')
    {   
        $table = $this->getJoinableTable($table_or_alias);
        if(!$this->is_used_table($table))
        {
            $table = (MormConf::isInConf($table_or_alias)) ? $this->add_table($table_or_alias) : $this->add_table($table);
            $this->join_tables[] = $table;
        }
        if(!is_null($on))
        {
            $tables = array_keys($on);
            $this->joins[] = array(array($tables[0] => $tables[1]), $on);
        }
        else
        {
            $key = $this->base_models[$this->base_table]->getForeignKeyFrom($table_or_alias);
            try
            {
                $ft_key = $this->base_models[$this->base_table]->getForeignTableKey($key);
            }
            catch (Exception $e)
            {
                if($this->base_models[$this->base_table]->isForeignUsingTable ($table))
                    $ft_key = $this->base_models[$this->base_table]->getForeignMormonsUsingKey($table);
                else
                    $ft_key = $this->base_models[$this->base_table]->getForeignMormonsKey($table);
            }
            $this->joins[] = array(array($this->base_table => $table), array($this->base_table => $key, $table => $ft_key));
        }
        //@todo put executed tu false only when join has changed
        $this->_executed = false;
        //        switch($type)
        //        {
        //            case 'LEFT':
        //                break;
        //            case 'RIGHT':
        //                break;
        //            default:
        //                throw new Exception("The join type ".$type." does not exist or is not yet supported by Mormons");
        //                break;
        //        }
    }

    /**
     * set_order 
     * 
     * @todo Consider the possibility of having more than one order fields
     *
     * @param string $order ordering field
     * @param string $alternate_table optionnal alternate table
     * @return void
     */
    public function set_order($order, $alternate_table=null)
    {
        if(!is_string($order) && !is_array($order)) throw new Exception("The order is suppose to be a field or an array of fields");
        $order_table = (!is_null($alternate_table)) ? $alternate_table : $this->base_table; 
        $order_table = (!$this->is_used_table($order_table)) ? $this->add_table($alternate_table) : $order_table; 
        if(is_string($order)) 
            $order = array($order);
        if(!isset($this->order[$order_table]) || $this->order[$order_table] != $order)
        {
            $this->order[$order_table] = $order;
            $this->_executed = false;
        }
        return $this;
    }
    
    
    public function unset_order()
    {
        $this->order = array();
    }

    /**
     * @throws Exception is $dir is not correct value
     * @param string $dir
     * @return void
     */
    public function set_order_dir($dir)
    {
        if(!in_array($dir, array('DESC', 'desc', 'ASC', 'asc'))) throw new Exception("The direction is suppose to be DESC, desc, ASC or asc");
        if($this->order_dir != $dir)
        {
            $this->order_dir = $dir;
            $this->_executed = false;
        }
        return $this;
    }

    /**
     * @throws Exception if $offset is not numeric value
     * @param integer $offset
     * @return void
     */
    public function set_offset($offset)
    {
        if(!is_numeric($offset)) throw new Exception("The offset is suppose to be numeric value");
        if($this->offset != $offset)
        {
            $this->offset = $offset;
            $this->_executed = false;
        }
        return $this;
    }

    /**
     * limit 
     *
     * alias for set_limit
     * 
     * @param mixed $limit 
     * @return void
     */
    public function limit($limit)
    {
        return $this->set_limit($limit);
    }

    /**
     * @throws Exception if $limit is not a numeric value
     * @param integer $limit
     * @return void
     */
    public function set_limit($limit)
    {
        if(!is_numeric($limit)) throw new Exception("The limit is suppose to be numeric value");
        if($this->limit != $limit)
        {
            $this->limit = $limit;
            $this->_executed = false;
        }
        return $this;
    }

    /**
     * @param string $table
     * @return boolean
     */
    public function is_used_table($table)
    {
        return in_array($table, $this->tables);
    }

    /**
     * @param array $conds
     * @return void
     */
    public function add_conditions($conds, $alternate_table=null)
    {
        $cond_table = $this->base_table; 
        if(!is_null($alternate_table))
        {
            if(!$this->is_used_table($alternate_table)) 
                $cond_table = $this->add_table($alternate_table); 
            else
                $cond_table = $alternate_table;
        }
        
        foreach ($conds as $field => $void) {
            if(!$this->base_models[$cond_table]->table_desc->isField($field)) {
                throw new MormFieldUnexistingException($cond_table, $field);
            }
        }

        $this->lookForClassFromFieldToSet($conds);
        $diffs = array_diff_assoc($conds, $this->where[$cond_table]);
        if(!empty($diffs))
        {
            $this->where[$cond_table] = array_merge($this->where[$cond_table], $conds);
            $this->_executed = false;
        }
    }

    /**
     * @throws Exception if MySQL error occurs
     * @return void
     */
    public function execute()
    {
        $rs = SqlTools::sqlQuery("SELECT ".SqlBuilder::select($this->tables).
                        " \nFROM ".SqlBuilder::from($this->get_from_tables()).
                        SqlBuilder::joins($this->joins, $this->getJoinType())."\n".
                        SqlBuilder::where($this->where, $this->sql_where)."\n".
                        SqlBuilder::order_by($this->order, $this->order_dir)."\n".
                        SqlBuilder::limit($this->offset, $this->limit));
        if($rs)
        {
            $this->load($rs);
            $this->_executed = true;
            if(!is_null($this->_foreign_object_waiting_for_association))
            {
                $this->associateForeignObject($this->_foreign_object_waiting_for_association[0], $this->_foreign_object_waiting_for_association[1]);
            }
        }
        else
            throw new Exception("Fatal error:".mysql_error());
    }


    public function delete()
    {
        
        if(!empty($this->order) || !is_null($this->limit) || !is_null($this->offset)) {
            throw new MormImpossibleDeletionException($this->base_table);
        }
        
        $rs = SqlTools::sqlQuery("DELETE {$this->base_table} \n".
            " FROM ".SqlBuilder::from($this->get_from_tables())."\n".
            SqlBuilder::joins($this->joins, $this->getJoinType())."\n".
            SqlBuilder::where($this->where, $this->sql_where)."\n"
        );
    
        if(!$rs)
            throw new Exception("Fatal error:".mysql_error());
      
    }

    /**
     * @todo cache the result
     * @param boolean $with_limit
     * @return integer
     */
    public function get_count($with_limit = false)
    {
        $limit_stmt = $with_limit ? SqlBuilder::order_by($this->order, $this->order_dir).SqlBuilder::limit($this->offset, $this->limit) : '';
        $rs = SqlTools::sqlQuery("SELECT count(1)
                        \nFROM ".SqlBuilder::from($this->get_from_tables()).
                        SqlBuilder::joins($this->joins, $this->getJoinType()).
                        SqlBuilder::where($this->where, $this->sql_where).
                        $limit_stmt);
        if($rs)
            return (int) mysql_result($rs, 0);
        else
            throw new Exception("Fatal error:".mysql_error());
    }

    /**
     * @return array
     */
    public function get_from_tables()
    {
        return array_diff($this->tables, $this->join_tables);
    }

    private function getEveryLoadableData($line)
    {
        $return = array();
        foreach($line as $field => $value)
        {
            //extract table name and field_name
            $extract = explode(MormConf::MORM_SEPARATOR, $field);
            $table_name = $extract[1];
            $field_name = $extract[2];
            $return['table'][$table_name] = true;
            $return['loadable_data'][$table_name][$field_name] = $value;
            $return['object_identifier'][$table_name] = $this->extractMormonIdFromLine($line, $table_name);
        }
        return $return;
    }

    /**
     * @param resource $rs
     * @return void
     */
    private function load($rs)
    {
        $this->mormons = array();
        $model_name = get_class($this->base_models[$this->base_table]);
        while($line = mysql_fetch_assoc($rs))
        {
            unset($model);
            $loadable_tables = $this->get_loadable_tables($line);
            if($this->is_loaded($loadable_tables['bt']))
            {
                $model = $this->get_loaded($loadable_tables['bt']);
                foreach($loadable_tables['fm'] as $table => $pkey)
                    $model->loadForeignObjectFromMormons($table, $line);
            }
            else
            {
                $model = Morm::FactoryFromMormons($model_name, $this, $line);
                $model_fields = $model->getTableDesc();
                if($model_fields->getPKey())
                {
                    $key = is_array($model->_pkey) ? implode(MormConf::MORM_SEPARATOR, $model->getPkeyVal()) : $model->{$model->_pkey};
                    $this->mormons[$this->base_table.'_'.$key] = $model;
                }
                else
                    $this->mormons[] = $model;
            }
        }
        $this->mormon_keys = array_keys($this->mormons);
        $this->nb_mormons = count($this->mormons);
    }

    /**
     * @param array $object_array
     * @return boolean
     */
    private function is_loaded($object_array)
    {
        $table = array_keys($object_array);
        $table = $table[0];
        return isset($this->mormons[$table.'_'.$object_array[$table]]);
    }

    /**
     * @todo Should maybe check if object is_loaded
     * @param array $object_array
     */
    private function get_loaded($object_array)
    {
        $table = array_keys($object_array);
        $table = $table[0];
        return $this->mormons[$table.'_'.$object_array[$table]];
    }

    private function get_loadable_tables(&$line)
    {
        $ret = array();
        foreach($this->extractTablesFromLine($line) as $table)
        {
            if($this->base_models[$this->base_table]->isForeignTable($table))
            {
                if(!isset($ret['ft'])) $ret['ft'] = array();
                if(isset($ret['ft'][$table])) continue;
                $ret['ft'][$table] = $this->extractMormonIdFromLine($line, $table);
            }
            else if($this->base_models[$this->base_table]->isForeignMormons($table))
            {
                if(!isset($ret['fm'])) $ret['fm'] = array();
                if(isset($ret['fm'][$table])) continue;
                $ret['fm'][$table] = $this->extractMormonIdFromLine($line, $table);
            }
            else if($table == $this->base_table && !isset($ret['bt']))
            {
                $ret['bt'][$table] = $this->extractMormonIdFromLine($line, $table);
            }
        }   
        return $ret;
    }

    private function extractMormonIdFromLine($line, $table)
    {
        if(is_array($this->base_models[$table]->_pkey))
        {
            $to_implode = array();
            $pkey = $this->base_models[$table]->_pkey;
            foreach($pkey as $field_name)
                $to_implode[] = $line['morm'.MormConf::MORM_SEPARATOR.$table.MormConf::MORM_SEPARATOR.$field_name];
            $ret = implode(MormConf::MORM_SEPARATOR, $to_implode);
        }
        else
            $ret ='morm'.MormConf::MORM_SEPARATOR.$table.MormConf::MORM_SEPARATOR.$this->base_models[$table]->_pkey;
        return $ret;
    }

    private function extractTablesFromLine($line)
    {
        $tables = array();
        array_walk($line, create_function('$v, $k, &$tables', '$matches = explode(MormConf::MORM_SEPARATOR, $k); if($matches[0] != $k && !isset($tables[$matches[1]])) $tables[$matches[1]] = true;'), &$tables);
        return array_keys($tables);
    }

    public function addMormFromArray($table, $to_load, &$to_associate = null)
    {
        $model_name = get_class($this->base_models[$this->base_table]);
        $model = Morm::FactoryFromMormons($model_name, $this, $to_load);
        $model_fields = $model->getTableDesc();
        if($model_fields->getPKey())
            $this->mormons[$this->base_table.'_'.$model->{$model->_pkey}] = $model;
        else
            $this->mormons[] = $model;
        if(is_object($to_associate))
            $model->loadForeignObject($to_associate->_pkey, $to_associate);
        $this->mormon_keys = array_keys($this->mormons);
        $this->nb_mormons = count($this->mormons);
    }

    public function associateForeignObject($field, &$to_load)
    {
        if(!$this->_executed)
            $this->_foreign_object_waiting_for_association = array($field, $to_load);
        else
            foreach($this->mormons as $obj_id => $morm)
            {
                if(is_array($field))
                {
                    foreach($field as $using => $key)
                    {
                        if(!$morm->isForeignUsingTable($using))
                            $morm->$using->loadForeignObject($key, $to_load);
                    }
                }
                else
                    $morm->loadForeignObject($field, $to_load);
            }
    }

    public function getClassFromObjId ($obj_id)
    {
        return $obj_id;   
    }

    /**
     * @return boolean
     */
    public function hasElements() {
        return !empty($this->mormons);
    }

    private function getJoinType()
    {
        return $this->needRightElements ? 'INNER' : 'LEFT';
    }

    private function getJoinableTable($table_or_alias)
    {
        if($this->base_models[$this->base_table]->isForeignAlias($table_or_alias))
            return $this->base_models[$this->base_table]->getForeignTableFromAlias($table_or_alias);
        else
            return $table_or_alias;
    }

    private function lookForClassFromFieldToSet($conditions)
    {
        foreach($conditions as $cond_field => $condition)
        {
            if($this->base_models[$this->base_table]->isForeignClassFromField($cond_field) && !is_array($condition))
            {
                $field_value = $this->base_models[$this->base_table]->$cond_field;
                if(empty($field_value))
                    $this->base_models[$this->base_table]->$cond_field = $condition;
                else
                    throw new Exception('Impossible to redefine a condition on a \'class_form_field\' field');
            }
        }
    }
    
    public function first()
    {
        $this->rewind();
        return $this->current();
    }

    public function last()
    {
        $this->rewind();
        return $this->mormons[$this->mormon_keys[$this->nb_mormons - 1]]; 
    }

    /************ Iterator methods **************/
    public function current(){ 
        return $this->mormons[$this->mormon_keys[$this->key]]; 
    }

    public function key() { 
        return $this->mormon_keys[$this->key]; 
    }

    public function next(){ 
        $this->key++; 
    }

    public function rewind(){ 
        if(!$this->_executed)
            $this->execute();
        $this->key = 0; 
    }

    /**
     * @return boolean
     */
    public function valid() { 
        if(!$this->_executed)
            $this->execute();
        return $this->key < $this->nb_mormons;
    }
}

