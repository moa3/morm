<?php

/**
 * MormSerializer
 * This is a small POC of what could be done to serialize a single Morm instance
 *
 * @author  Arnaud Berthomier <arnaud.berthomier@af83.com>
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class MormSerializer
{
    /**
     * @access protected
     */
    private $_morm = undef;

    /**
     * extend_with
     *
     * Declare which methods this plugin extends a Morm intance with
     *
     * @return array
     */
    public static function extend_with()
    {
        return array("toJSON", "toXML");
    }

    /**
     * Constructor
     *
     */
    public function __construct($morm, $opts=array())
    {
        $this->_morm = $morm;
    }

    /**
     * toJSON
     *
     * Serialize a Morm object to JSON
     * XXX needs PHP Json
     *
     * @return string
     */
    public function toJSON($opts=array())
    {
        $struct = array();
        $json_fields = $this->get_fields_from_opts($opts);
        foreach ($json_fields as $field)
            $struct[$field] = $this->_morm->$field;
        return json_encode($struct);
    }

    /**
     * toXML
     *
     * Serialize a Morm object to XML
     * XXX This is just an ugly example of XML serialzation with SimpleXML
     *
     * @return string
     */
    public function toXML($opts=array())
    {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<record></record>");
        $xml_fields = $this->get_fields_from_opts($opts);
        foreach ($xml_fields as $field)
            $xml->addChild($field, $this->_morm->$field);
        return $xml->asXML();
    }

    /**
     * get_fields_from_opts
     *
     * Filter fields according to the options: 'only' or 'except'
     *
     * @return array
     * @access private
     */
    private function get_fields_from_opts($opts=array())
    {
        $fieldset = $this->_morm->table_desc->field_keys;
        if(isset($opts['only']) && is_array($opts['only']))
            return array_intersect($fieldset, $opts['only']);
        elseif(isset($opts['except']) && is_array($opts['except'])) {
            return array_diff($fieldset, $opts['except']);
        }
        return $fieldset;        
    }
}
?>
