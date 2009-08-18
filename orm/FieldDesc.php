<?php
/**
 * FieldDesc 
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class  FieldDesc
{
    public $type = NULL;
    public $php_type = 'string';
    public $values = NULL;
    public $length = NULL;

    public function Decorate()
    {
        $this->setType();
        $this->setPHPType();
    }

    public function isPrimary()
    {
        return $this->Key == 'PRI';
    }

    private function setType()
    {
        // see http://fr.php.net/manual/fr/function.mysql-list-fields.php for a 
        // direct access to mySQL field type, may be useful
        $this->type = $this->Type;
        $regexps = array(
                         'int' => '/^int\(([\d]+)\)([^\$]*)$/',
                         'varchar' => '/^varchar\(([\d]+)\)$/',
                         'tinyint' => '/^tinyint\(([\d]+)\)([^\$]*)$/',
                         'enum' => '/^enum\(([^\)]*)\)$/',
                         'set' => '/^set\(([^\)]*)\)$/',
                         );
        foreach($regexps as $type => $regexp)
        {
            $matches = array();
            $match = preg_match($regexp, $this->Type, $matches);
            if($match)
            {
                $this->type = $type;
                $set_method = 'set'.ucfirst($type);
                $this->$set_method($matches);
                continue;
            }
        }
    }

    private function setPHPType()
    {
        $type_corresps = array(
                               'bool' => 'boolean',
                               'int' => 'integer',
                               'tinyint' => 'integer',
                               'float' => 'float',
                               );
        if(isset($type_corresps[$this->type]))
            $this->php_type = $type_corresps[$this->type];
    }

    public function isNumeric()
    {
        $numeric_types = array(
                               'integer',
                               'float'
                               );
        return in_array($this->php_type, $numeric_types);
    }

    private function setEnum($match)
    {
        $this->values = explode("','", substr($match[1], 1, strlen($match[1]) - 2));
    }

    private function setSet($match)
    {
        $this->values = explode("','", substr($match[1], 1, strlen($match[1]) - 2));
    }

    private function setInt($match)
    {
        $this->length = intval($match[1]);
    }

    private function setTinyint($match)
    {
        if(intval($match[1]) == 1)
        {
            $this->type = 'bool';
        }
        $this->length = intval($match[1]);
    }

    private function setVarchar($match)
    {
        $this->length = intval($match[1]);
    }

}

