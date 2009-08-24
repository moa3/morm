<?php
/**
 * TableDesc 
 *
 * singleton containing the table structure and other practical stuff
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class TableDesc implements Iterator
{
    /**
     * @static
     * @access public
     * @var array
     */
    static $tables = array();

    /**
     * @access private
     */
    private $table;

    /**
     * @access private
     */
    private $fields = null; 

    /**
     * @access private
     */
    private $nb_fields = null;

    /**
     * @access private
     */
    private $field_keys = null; 

    /**
     * @access private
     */
    private $current = null; 

    /**
     * @access private
     */
    private $key = 0;

    /**
     * Constructor.
     *
     * @param string $table
     */
    public function __construct ($table)
    {
        //set a table_desc object with the fields for a table
        $rs = SqlTools::sqlQuery("desc $table");
        if($rs)
        {
            $this->table['name'] = $table;
            while($field = mysql_fetch_object($rs, 'FieldDesc'))
            {
                $field->Decorate();
                $this->fields[$field->Field] = $field;
            }
            $this->field_keys = array_keys($this->fields);
            $this->nb_fields = count($this->field_keys);
            self::$tables[$table] = $this;
        }
        else
            throw new Exception('Table '.$table.' may not exist '.mysql_error());
    }

    /**
     * @param string $name
     */
    public function __get ($name)
    {
        if($this->isField($name))
            return $this->fields[$name] ;
        else
            return $this->$name;
    }

    public function getPKey ()
    {
        if(!isset($this->table['pkey']))
        {
            foreach($this->fields as $field_name => $value) {
                if($field_name != 'table' && $value->Key == 'PRI')
                {
                    if(isset($this->table['pkey']))
                    {
                        if(!is_array($this->table['pkey']))
                            $this->table['pkey'] = array($this->table['pkey']);
                        $this->table['pkey'][] = $field_name;
                    }
                    else
                        $this->table['pkey'] = $field_name;
                }
            }
        }
        return isset($this->table['pkey']) ? $this->table['pkey'] : NULL;
    }

    public function hasAutoIncrement ()
    {
        if(!isset($this->table['auto_increment']))
        {
            $ac = false;
            foreach($this->fields as $field_name => $value) {
                if($field_name != 'table' && $value->Extra == 'auto_increment')
                {
                    $ac = true;
                    $this->table['auto_increment_field'] = $field_name;
                }
            }
            $this->table['auto_increment'] = $ac;
        }
        return $this->table['auto_increment'];
    }

    public function getAutoIncrementField ()
    {
        if($this->hasAutoIncrement())
            return $this->table['auto_increment_field'];
        return NULL;
    }

    /**
     * @param string $field
     * @return boolean
     */
    public function isField ($field)
    {
        return isset($this->fields[$field]);
    }

    public function getFields ()
    {
        return $this->fields;
    }

    /**
     * @static
     * @param string $table
     * @return TableDesc
     */
    static public function getTable ($table)
    {
        if(!isset(self::$tables[$table]))
            self::$tables[$table] = new TableDesc($table);
        return self::$tables[$table];       
    }

    /************ Iterator methods **************/
    public function current(){ 
        return $this->fields[$this->field_keys[$this->key]]; 
    } 

    public function key() { 
        return $this->field_keys[$this->key]; 
    } 

    /**
     * @return void
     */
    public function next(){ 
        $this->key++; 
    } 

    /**
     * @return void
     */
    public function rewind(){ 
        $this->key = 0; 
    } 

    /**
     * @return boolean
     */
    public function valid() { 
        return $this->key < $this->nb_fields; 
    }
}
