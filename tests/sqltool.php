<?php
require_once('prepend.php');

class MormTestSqlTools extends MormUnitTestCase
{
    public function testFormatInt()
    {
        $this->assertEqual('SELECT id WHERE id=1', SqlTools::formatQuery('SELECT id WHERE id=?', array(1)));
    }

    public function testFormatString()
    {
        $this->assertEqual('SELECT id WHERE id=\'1\'', SqlTools::formatQuery('SELECT id WHERE id=?', array('1')));
    }

    public function testFormatBooleanTrue()
    {
        $this->assertEqual('SELECT id WHERE id=1', SqlTools::formatQuery('SELECT id WHERE id=?', array(true)));
    }

    public function testFormatBooleanFalse()
    {
        $this->assertEqual('SELECT id WHERE id=0', SqlTools::formatQuery('SELECT id WHERE id=?', array(false)));
    }

    public function testFormatNUll()
    {
        $this->assertEqual('SELECT id WHERE id=NULL', SqlTools::formatQuery('SELECT id WHERE id=?', array(NULL)));
    }

    public function testFormatDouble()
    {
        $this->assertEqual('SELECT id WHERE id=\'0.1\'', SqlTools::formatQuery('SELECT id WHERE id=?', array(0.1)));
    }

    public function testFormatArrayString()
    {
        $this->assertEqual('SELECT id WHERE id=\'foo\',\'bar\'', SqlTools::formatQuery('SELECT id WHERE id=?', array(array('foo', 'bar'))));
    }

    public function testFormatArrayInt()
    {
        $this->assertEqual('SELECT id WHERE id=2,3', SqlTools::formatQuery('SELECT id WHERE id=?', array(array(2, 3))));
    }

    public function testFormatEscape()
    {
        $this->assertEqual("SELECT id WHERE id='FOO\'BAR'", SqlTools::formatQuery('SELECT id WHERE id=?', array("FOO'BAR")));
    }

    public function testFormatEscapeStringWithHtml()
    {
        $this->assertEqual('SELECT id WHERE id=\'<iframe src=\"HACK\">HACK</iframe>\'', SqlTools::formatQuery('SELECT id WHERE id=?', array('<iframe src="HACK">HACK</iframe>')));
    }

    public function testEscapeHtmlDoubleQuote()
    {
        $this->assertEqual(' HACK ', SqlTools::mysql_escape('<iframe src="HACK">HACK</iframe>'));
    }

    public function testEscapeHtmlSimpleQuote()
    {
        $this->assertEqual(' HACK ', SqlTools::mysql_escape('<iframe src=\'HACK\'>HACK</iframe>'));
    }
}
