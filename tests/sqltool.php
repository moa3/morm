<?php
require_once('prepend.php');

class MormTestSqlFormatAndEscape extends MormUnitTestCase
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

class MormTestSqlToolsQuery extends MormUnitTestCase
{
    protected function mormSetUp()
    {
        $this->sql->queryDB('CREATE TABLE `test` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY
                                        ) ENGINE = InnoDB;');
    }

    protected function mormTearDown()
    {
        $this->sql->queryDB('DROP TABLE `test`');
    }

    public function testSimpleQuery()
    {
        $result = mysql_fetch_array(SqlTools::sqlQuery('SELECT COUNT(1)'));
        $this->assertEqual(1, $result[0]);
    }

    public function tetsBadQueryThowException()
    {
        try {
            $result = SqlTools::sqlQuery('SELECT \'');
            $this->fail("Must throw a MormSqlException");
        } catch (MormSqlException $e) {
            $this->pass();
        }
    }

    public function testDuplicateEntryThrowException()
    {
        try {
            SqlTools::sqlQuery('INSERT INTO test VALUES(1)');
            SqlTools::sqlQuery('INSERT INTO test VALUES(1)');
            $this->fail("Must throw a MormDuplicateEntryException");
        } catch (MormDuplicateEntryException $e) {
            $this->pass();
        }
    }

    public function testFormatSqlToolsNoArray()
    {
        $result = mysql_fetch_array(SqlTools::sqlQuery('SELECT ?', 42));
        $this->assertEqual(42, $result[0]);
    }

    public function testFormatSqlToolsWithArray()
    {
        $result = mysql_fetch_array(SqlTools::sqlQuery('SELECT ? + ?', array(40, 2)));
        $this->assertEqual(42, $result[0]);
    }

    public function testFormatSqlToolsWithArrayNoFake()
    {
        $result = mysql_fetch_array(SqlTools::sqlQuery('SELECT ? + ?', array(40, 1)));
        $this->assertEqual(41, $result[0]);
    }

}
