<?php
require_once('prepend.php');

class TestFieldDesc extends MormUnitTestCase
{
    protected function mormSetUp()
    {
        $this->field = new FieldDesc();
    }

    protected function fieldDesc($Type, $expectedLength, $expectedValues, 
                                 $expectedType, $expectedPhpType, $isPrimary, $isNumeric)
    {
        $this->field->Type = $Type;
        $this->field->Decorate();
        $this->assertEqual($expectedLength, $this->field->length);
        $this->assertEqual($expectedValues, $this->field->values);
        $this->assertEqual($expectedType, $this->field->type);
        $this->assertEqual($expectedPhpType, $this->field->php_type);
        $this->assertEqual($isPrimary, $this->field->isPrimary());
        $this->assertEqual($isNumeric, $this->field->isNumeric());
    }

    public function testParseTypeInt()
    {
        $this->fieldDesc('int(11)', 11, NULL, 'int', 'integer', false, true);
    }

    public function testParseAnotherTypeInt()
    {
        $this->fieldDesc('int(22)', 22, NULL, 'int', 'integer', false, true);
    }

    public function testParseTypeVarchar()
    {
        $this->fieldDesc('varchar(255)', 255, NULL, 'varchar', 'string', false, false);
    }
    
    public function testParseTypeTinyint()
    {
        $this->fieldDesc('tinyint(22)', 22, NULL, 'tinyint', 'integer', false, true);
    }

    public function testParseTypeEnum()
    {
        $this->fieldDesc('enum(\'foo\',\'bar\')', NULL, array('foo', 'bar'), 'enum', 'string', false, false);
    }

    public function testParseTypeSet()
    {
        $this->fieldDesc('set(\'foo\',\'bar\')', NULL, array('foo', 'bar'), 'set', 'string', false, false);
    }

    public function testPrimaryKey()
    {
        $this->field->Key = 'PRI';
        $this->assertTrue($this->field->isPrimary());
    }

    public function testNotPrimaryKey()
    {
        $this->field->Key = '';
        $this->assertFalse($this->field->isPrimary());
    }
}
