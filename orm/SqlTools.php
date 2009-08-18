<?php
/**
 * SqlTools 
 * 
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class SqlTools
{
    /**
     * formatSqlValue 
     * 
     * used to correctly escape the values used in an sql query 
     *
     * @param mixed $value if this is an object or an array it will iterate on the 
     * value and return each of the contained values separated by comas (typically 
     * for an IN statement)
     * @access public
     * @return mixed depending on the type of the given parameter
     */
    static function formatSqlValue($value)
    {
        $type = gettype($value);//do not use gettype as it is slow thanks to fucking PHP
        switch ($type)
        {
            case 'boolean':
                return $value ? 1 : 0;
                break;
            case 'integer':
                return $value;
                break;
            case 'double':
                return "'".$value."'";
                break;
            case 'string':
                return "'".self::mysql_escape($value, TRUE)."'";
                break;
            case 'array':
            case 'object':
                $values = array();
                foreach($value as $k => $v)
                    $values[$k] = self::formatSqlValue($v);
                return implode(',', $values);
                break;
            case 'NULL':
                return 'NULL';
                break;
            default:
                return "'".self::mysql_escape($value, TRUE)."'";
                break;
        }

    }

    public static function mysql_escape($string,$accept_html=FALSE)
    {
        // remove html tags by default
        if ($accept_html===FALSE)
            $string = preg_replace('/<[^>]*>/',' ',$string);
        return get_magic_quotes_gpc() ? mysql_real_escape_string(stripslashes($string)) : mysql_real_escape_string($string);
    }

    /**
     * @static
     * @access public
     * @return resource|boolean
     */
    static function sqlQuery($query, $params=null, $link_identifier = null)
    {
        switch ( gettype($params) )
        {
            case 'array':
                $sql = self::formatQuery($query, $params);
                break;
            case 'NULL':
                $sql = $query;
                break;
            default:
                $sql = self::formatQuery($query, array($params));
                break;
        }
        //if(SITEENV == 'DEV')
        //    user_error($query, NOTICE);

        $result = is_null($link_identifier) ? mysql_query($sql) : mysql_query($sql, $link_identifier);
        if(!$result) {
            throw MormSqlException::getByErrno(mysql_errno(),mysql_error());
        }
        return $result;

    }

    /**
     * format_query
     *
     * used to convert a query formatted with ? in the right format (adding the 
     * quotes and everything needed to prevent injection and broken queries)
     * 
     * @static
     * @param string $query 
     * @param array $params this is ALWAYS supposed to be an array
     * @access public
     * @return string well formated sql query
     */
    static function formatQuery($query, $params)
    {
        foreach($params as $param)
        {
            $query = preg_replace('/\?/', self::formatSqlValue($param), $query, 1);
        }
        return $query;
    }
}
