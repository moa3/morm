<?php
/**
 * SqlBuilder 
 * this is most probably the file you'd like to change in order to be able to 
 * use another database than mysql
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class SqlBuilder
{
    static function select($select)
    {
        $statement = array();
        foreach($select as $table => $val)
        {
            if(is_numeric($table))
            {
                $table = $val;
                $val = '*';
            }
            $table_desc = TableDesc::getTable($table); 
            switch(gettype($val)) 
            {
            case 'array':
                foreach($val as $field)
                {
                    if($table_desc->isField($field))
                        $statement[] = self::singleSelect($table, $field);
                    else
                        $statement[] = $field;
                }
                break;
            case 'string':
                if(empty($val) || $val == '*')
                {
                    foreach($table_desc as $field => $field_desc)
                    {
                        $statement[] = self::singleSelect($table, $field);
                    }
                }
                else
                    $statement[] = $field;
                break;
            default:
                break;
            }
        }
        return implode(',', $statement);
    }

    static function singleSelect($table, $field, $prefix = 'morm')
    {
        return '`'.$table.'`.`'.$field.'` as '.self::selectAlias($table, $field, $prefix);
    }

    static function selectAlias($table, $field, $prefix = 'morm')
    {
        $prefix = is_string($prefix) ? $prefix : 'morm';
        return '`'.$prefix.MormConf::MORM_SEPARATOR.$table.MormConf::MORM_SEPARATOR.$field.'`';
    }

    static function from($tables)
    {
        $tables = !is_array($tables) ? array($tables) : $tables ;
        return ' `'.implode('`,`', $tables).'` ';
    }

    static function joins($joins, $type)
    {
        $ret = array();
        foreach($joins as $join)
            $ret[] = self::singleJoin($join[0], $join[1], $type);
        return implode(' ', $ret);
    }

    static function singleJoin($tables, $fields, $type)
    {
        $left = array_keys($tables);
        $left = $left[0];
        $right = $tables[$left];
        return ' '.$type.' JOIN `'.$right.'` ON `'.$left.'`.`'.$fields[$left].'`=`'.$right.'`.`'.$fields[$right].'` ';
    }

    static function where($conditions, $sql_where='')
    {
        
        if(empty($conditions))
            return '';
        $where = array();
        foreach($conditions as $table => $condition)
        {
            if(is_array($condition))
            {
                foreach($condition as $k => $cond)
                    $where[] = self::singleWhere($table, array($k => $cond));
            }
            else
                $where[] = self::singleWhere($table, $condition);
        }
        if(empty($where))
            return empty($sql_where) ? '' : 'WHERE '.$sql_where;
        return empty($sql_where) ? 'WHERE '.implode(' AND ', $where) : 'WHERE '.implode(' AND ', $where).' AND '.$sql_where;
    }

    static function singleWhere($table, $condition)
    {
        $field = array_keys($condition);
        $field = $field[0];
        $operator = '=';
        if(is_array($condition[$field]) && isset($condition[$field]['operator']))
        {
            $operator = $condition[$field]['operator'];
            $condition[$field] = $condition[$field][0];
        }
        $table_desc = TableDesc::getTable($table);
        if(is_array($condition[$field]))
        {
            foreach($condition[$field] as $key => $value)
                settype($condition[$field][$key], $table_desc->$field->php_type);
            $operator = 'IN';
            return '`'.$table.'`.`'.$field.'` '.$operator.' ('.SqlTools::formatSqlValue($condition[$field]).')';

        }
        settype($condition[$field], $table_desc->$field->php_type);
        return '`'.$table.'`.`'.$field.'` '.$operator.' '.SqlTools::formatSqlValue($condition[$field]);
    }

    static function order_by($orders, $dir)
    {
        if(empty($orders))
            return '';
        $order_by = array();
        foreach($orders as $table => $order)
        {
            if(is_array($order))
            {
                foreach($order as $k => $ord)
                    $order_by[] = self::singleOrder_By($table, $ord);
            }
            else
                $order_by[] = self::singleOrder_By($table, $order);
        }
        return empty($order_by) ? '' : ' ORDER BY '.implode(',', $order_by).' '.$dir;
    }

    static function singleOrder_By($table, $order)
    {
        return '`'.$table.'`.`'.$order.'`';
    }

    static function limit($offset, $limit) 
    {
        if(!is_null($limit))
        {
            return is_null($offset) ? sprintf(" limit %d", $limit) : sprintf(" limit %d,%d", $offset, $limit) ;
        }
        return '';
    }

    static function set ($values)
    {
        $set = array();
        foreach($values as $field => $value)
            $set[] = '`'.$field.'`='.SqlTools::formatSqlValue($value);
        return 'set ' . implode(' , ', $set);
    }

}
