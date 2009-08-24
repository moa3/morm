<?php
require_once('prepend.php');

class Books extends Morm
{
    public $_table = 'books';

    protected $_foreign_keys = array('author_id' => array('table' => 'authors'));

    public static function createTable()
    {
        return 'CREATE TABLE `books` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                                       `author_id` INT NOT NULL,
                                       `name` VARCHAR( 255 ) NOT NULL
                                        ) ENGINE = InnoDB;';
    }

    public static function dropTable() 
    {
        return 'DROP TABLE `books`;';
    }

    public function setAlias($alias_name)
    {
        $this->_foreign_keys['author_id']['alias'] = $alias_name;
    }

    public function setTable($table_name)
    {
        $this->_foreign_keys['author_id']['table'] = $table_name;
    }

    public function setKey($key_name)
    {
        $this->_foreign_keys['author_id']['key'] = $key_name;
    }

    public function getForeignObjectForTesting()
    {
        return $this->_foreign_object;
    }
}

class Comments extends Morm
{
    public $_table = 'comments';

    protected $_foreign_keys = array('object_id' => array('class_from_field' => 'object_type', 'alias' => 'object'));

    public static function createTable()
    {
        return 'CREATE TABLE `comments` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                                       `object_type` VARCHAR( 255 ) NOT NULL,
                                       `object_id` INT NOT NULL,
                                       `body` VARCHAR( 255 ) NOT NULL
                                        ) ENGINE = InnoDB;';
    }

    public static function dropTable() 
    {
        return 'DROP TABLE `comments`;';
    }
}

class TestOneToOneRelations extends MormTestCaseWithTableAuthors
{
    public function mormSetUp()
    {
        parent::mormSetUp();
        $this->sql->queryDB(Books::createTable());
        $this->sql->queryDB(Comments::createTable());
        $this->author = $this->createEntry('Authors', array('name' => 'John Doe', 'email' => 'foo@example.com'));
        $this->book = $this->createEntry('Books', array('name' => 'Foo Bar', 'author_id' => $this->author->id));
    }

    public function mormTearDown()
    {
        parent::mormTearDown();
        $this->sql->queryDB(Books::dropTable());
        $this->sql->queryDB(Comments::dropTable());
    }

    public function TestGetOneBookWithAuthor()
    {
        $fetch = new Books($this->book->id);
        $this->assertEqual('Foo Bar', $fetch->name);
        $this->assertEqual('John Doe', $fetch->authors->name);
    }

    public function testGetOneBookWithAuthorAndAliasOnRelation()
    {
        $fetch = new Books($this->book->id);
        $fetch->setAlias('bar');
        $this->assertEqual('John Doe', $fetch->bar->name);
    }

    public function testGetOneBookWithAuthorAndAliasNamedNameOnRelation()
    {
        $fetch = new Books($this->book->id);
        $fetch->setAlias('name');
        $this->assertEqual('Foo Bar', $fetch->name);
        $this->assertEqual('John Doe', $fetch->authors->name);
    }

    public function testPolymorphism()
    {
        $comment = $this->createEntry('Comments', array('object_type' => 'Books', 'object_id' => $this->book->id, 'body' => 'Chuck Norris is better than you.'));
        $fetch = new Comments($comment->id);
        $this->assertEqual('Chuck Norris is better than you.', $fetch->body);
        $this->assertEqual('Foo Bar', $fetch->object->name);
        $this->assertEqual('John Doe', $fetch->object->authors->name);
    }
    
    public function testAnotherPolymorphism()
    {
        $comment = $this->createEntry('Comments', array('object_type' => 'Authors', 'object_id' => $this->author->id, 'body' => 'Good.'));
        $fetch = new Comments($comment->id);
        $this->assertEqual('Good.', $fetch->body);
        $this->assertEqual('John Doe', $fetch->object->name);
    }

    public function testSetJoin()
    {
        $books = new Mormons('books');
        $books->set_join('authors');
        foreach ($books as $book)
        {
            $this->assertEqual(1, count($book->getForeignObjectForTesting()));
        }       
    }

    public function testNoJoinObject()
    {
        $books = new Mormons('books');
        foreach ($books as $book)
        {
            $this->assertEqual(0, count($book->getForeignObjectForTesting()));
            $name = $book->authors->name;
            $this->assertEqual(1, count($book->getForeignObjectForTesting()));
        }
        
    }

}

class TestOneToOneRelationsWithADifferentKeyName extends MormUnitTestCase
{

    public function mormSetUp()
    {
        $this->sql->queryDB('CREATE TABLE `authors` (
                                       `id_` INT NOT NULL,
                                       `name` VARCHAR( 255 ) NOT NULL
                                        ) ENGINE = InnoDB;');
        $this->sql->queryDB(Books::createTable());
        $this->author = $this->createEntry('Authors', array('name' => 'John Doe', 'email' => 'foo@example.com'));
        $this->book = $this->createEntry('Books', array('name' => 'Foo Bar', 'author_id' => $this->author->id_));
    }

    public function mormTearDown()
    {
        $this->sql->queryDB(Authors::dropTable());
        $this->sql->queryDB(Books::dropTable());
    }

    public function TestGetOneBookWithAuthor()
    {
        $fetch = new Books($this->book->id);
        $fetch->setKey('id_');
        $this->assertEqual('Foo Bar', $fetch->name);
        $this->assertEqual('John Doe', $fetch->authors->name);
    }

    public function TestGetOneBookWithBadKeyThrowException()
    {
        $fetch = new Books($this->book->id);
        $fetch->setKey('foo');
        try 
        {
            $this->assertEqual('John Doe', $fetch->authors->name);
            $this->fail('Must throw a MormSqlException.');
        }
        catch (MormSqlException $e)
        {
            $this->pass();
        }
    }
}
