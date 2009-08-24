<?php
require_once('prepend.php');

class TestTableDesc extends MormTestCaseWithTableAuthors
{
    public function testConstructTableDesc()
    {
        $tableDesc = new TableDesc('authors');
        $this->pass();
    }

    public function testNewWithBadTableThrowException()
    {
        try {
            $tableDesc = new TableDesc('foo');
            $this->fail('Must throw a MormSqlException');
        } catch (MormSqlException $e) {
            $this->pass();
        }
    }
    
    public function testGetNbFields()
    {
        $tableDesc = new TableDesc('authors');
        $this->assertEqual(2, $tableDesc->nb_fields);
    }

    public function testHaveAutoIncrement()
    {
        $tableDesc = new TableDesc('authors');
        $this->assertTrue($tableDesc->hasAutoIncrement());
    }

}
