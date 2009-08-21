<?php
require_once('prepend.php');

class Authors extends Morm 
{
    public $_table = "authors" ;

    public static function createTable() 
    {
        return 'CREATE TABLE `authors` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                                       `name` VARCHAR( 255 ) NOT NULL
                                        ) ENGINE = InnoDB;';
    }

    public static function dropTable() 
    {
        return 'DROP TABLE `authors`;';
    }
}

class TestMorm extends MormUnitTestCase 
{
    protected function mormSetUp()
    {
        $this->sql->queryDB(Authors::createTable());
    }

    protected function mormTearDown()
    {
        $this->sql->queryDB(Authors::dropTable());
    }

    public function testGetCountOnOneTable() 
    {
        $post = new Mormons('authors');
        $this->assertEqual(0, $post->get_count());
    }

    public function testGetCountWithTwoRows()
    {
        $author = new Authors();
        $author->name = "Chuck Norris";
        $author->save();
        $author = new Authors();
        $author->name = "Chuck Norrissette";
        $author->save();
        $post = new Mormons('authors');
        $this->assertEqual(2, $post->get_count());
    }
    
    public function testInsertWithArray()
    {
        $author = new Authors(array('name' => 'Chuck Norris'));
        $author->save();
        $authors = new Mormons('authors');
        $this->assertEqual(1, $authors->get_count());
    }

    public function testGetData()
    {
        $author = new Authors(array('name' => 'Chuck Norris'));
        $author->save();
        $post = new Mormons('authors');
        $this->assertEqual('Chuck Norris', $post->first()->name);
        $this->assertEqual(1, $post->first()->id);
    }

    public function testFillIdWhenSave()
    {
        $author = new Authors(array('name' => 'Chuck Norris'));
        $this->assertEqual(NULL, $authors->id);
        $author->save();
        $this->assertEqual(1, $author->id);
    }

    public function testGetById()
    {
        $author = new Authors(array('name' => 'Chuck Norris'));
        $author->save();
        $authors = new Authors(1);
        $this->assertEqual('Chuck Norris', $authors->name);
    }

    public function testGetByIdThrowException()
    {
        try {
            $authors = new Authors(2);
            $this->fail('Must throw a NoPrimaryKeySqlException');
        } catch (NoPrimaryKeySqlException $e) {
            $this->pass();
        }
    }

    public function testGetByIdWithDifferentSyntax()
    {
        $author = new Authors(array('name' => 'Chuck Norris'));
        $author->save();
        $authors = new Mormons('authors');
        $id = 1;
        $this->assertEqual('Chuck Norris', $authors->$id->name);
    }

    public function testLoopOverTable()
    {
        $author = new Authors(array('name' => 'Chuck Norris'));
        $author->save();
        $author = new Authors(array('name' => 'Bruce Lee'));
        $author->save();
        $authors = new Mormons('authors');
        $i = 0;
        foreach ($authors as $author)
        {
            $i++;
        }
        $this->assertEqual(2, $i);
    }

    public function testUpdateEntry()
    {
        $author = new Authors();
        $author->name = "Chuck Norris";
        $author->save();
        $author->name = "Bruce Lee";
        $author->save();
        $author2 = new Authors(1);
        $this->assertEqual('Bruce Lee', $author2->name);
    }

    public function testDeleteEntry()
    {
        $author = new Authors();
        $author->name = "Chuck Norris";
        $author->save();
        $author->delete();
        try 
        {
            $author2 = new Authors(1);
            $this->fail("Must throw a NoPrimaryKeySqlException");
        }
        catch (NoPrimaryKeySqlException $e) {
            $this->pass();
        }
    }
    
}
?>
