<?php
require_once('prepend.php');

class Writer extends User
{

}

class Reader extends User
{

}

class Translator extends Morm
{

}

class User extends Morm
{
    protected $_table = "user" ;

    public static function createTable()
    {
        return 'CREATE TABLE `user` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                                       `name` VARCHAR( 255 ) NOT NULL,
                                       `type` VARCHAR( 255 ) NOT NULL
                                        ) ENGINE = InnoDB;';
    }

    public static function dropTable()
    {
        return 'DROP TABLE `user`;';
    }
}

class OtherUser extends Morm
{
    protected $sti_field = 'othertype';

    protected $_table = "otheruser";

    public static function createTable()
    {
        return 'CREATE TABLE `otheruser` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                                       `name` VARCHAR( 255 ) NOT NULL,
                                       `othertype` VARCHAR( 255 ) NOT NULL
                                        ) ENGINE = InnoDB;';
    }

    public static function dropTable()
    {
        return 'DROP TABLE `otheruser`;';
    }
}

class Corrector extends OtherUser
{

}

class TestSTIFields extends MormUnitTestCase
{
    public function mormSetUp()
    {
        $this->sql->queryDB(User::createTable());
    }

    public function mormTearDown()
    {
        $this->sql->queryDB(User::dropTable());
    }

    public function testInstanceSTI()
    {
        $this->user = $this->createEntry('User', array('name' => 'John Doe', 'type' => 'reader'));
        $user = new Mormons('user');
        $this->assertIsA($user->first(), 'Reader');
    }

    public function testAnotherInstanceSTI()
    {
        $this->createEntry('User', array('name' => 'Foo Bar', 'type' => 'writer'));
        $user = new Mormons('user');
        $this->assertIsA($user->first(), 'Writer');
    }

    public function testInstanceWith2Entries()
    {
        $this->createEntry('User', array('name' => 'John Doe', 'type' => 'reader'));
        $this->createEntry('User', array('name' => 'Foo Bar', 'type' => 'writer'));
        
        $users = new Mormons('user');
        foreach ($users as $user)
        {
            if ($user->name == 'John Doe')
            {
                 $this->assertIsA($user, 'Reader');
            }
            if ($user->name == 'Foo Bar')
            {
                 $this->assertIsA($user, 'Writer');
            }
        }
    }

    /*public function testThrowExceptionIfNotClass()
    {
        $this->createEntry('User', array('name' => 'Foo Bar', 'type' => 'chuck'));
        $user = new Mormons('user');
        try
        {
            $user->first();
            $this->fail("Must throw an exception");
        }
        catch (MormSqlException $e)
        {
            $this->pass();
        }
        }*/

    public function testThrowExceptionIfClassDoesntExtendMorm()
    {
        $this->createEntry('User', array('name' => 'Foo Bar', 'type' => 'norris'));
        $user = new Mormons('user');
        try
        {
            $user->first();
            $this->fail("Must throw an exception");
        }
        catch (MormSqlException $e)
        {
            $this->pass();
        }
    }

    public function testThrowExceptionIfClassDoesntExtendUser()
    {
        $this->createEntry('User', array('name' => 'Foo Bar', 'type' => 'translator'));
        $user = new Mormons('user');
        try
        {
            $user->first();
            $this->fail("Must throw an exception");
        }
        catch (MormSqlException $e)
        {
            $this->pass();
        }
        catch  (Exception $e)
        {
            $this->fail("Must throw a MormSqlException");
        }
    }
    
}

class TestStiFieldsWithOverridedStiFieldProperty extends MormUnitTestCase
{
    public function mormSetUp()
    {
        $this->sql->queryDB(OtherUser::createTable());
    }

    public function mormTearDown()
    {
        $this->sql->queryDB(OtherUser::dropTable());
    }

    public function testInstanceSTI()
    {
        $this->user = $this->createEntry('OtherUser', array('name' => 'John Doe', 'othertype' => 'corrector'));
        $user = new Mormons('otheruser');
        $this->assertIsA($user->first(), 'Corrector');
    }

}
