==============
Morm - PHP ORM
==============

.. contents:: Table of Contents

Introduction
============

Morm is a PHP Orm. For now Morm only support Mysql.

Features:

* Create/Read/Update/Delete records in database
* Relations

 - OneToOne
 - OneToMany
 - ManyToMany

CRUD
====

The First step before using Morm is to create Php Class.

Let's start with a very simple table authors :

.. code-block:: sql
   :linenos:

   CREATE TABLE `authors` (
         `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
         `name` VARCHAR( 255 ) NOT NULL,
         `email` VARCHAR( 255 ) NOT NULL
   ) ENGINE = InnoDB;'

.. code-block:: php
   :linenos:

   <?php
   class Authors extends Morm
   {
       public $_table = 'authors';
   }
   ?>

Create
------

.. code-block:: php
   :linenos:
   
   $author = new Authors();
   $author->name = 'Foo';
   $author->email = 'foo@example.org';
   $author->save()

Read
----

For looping over all authors:

.. code-block:: php
   :linenos:
   
   $authors = new Mormons('authors');
   foreach ($authors as $author)
   {
        echo $authors->name;
   }

Update
------



Delete
------

Just use the delete() method.

.. code-block:: php
   :linenos:

   $author = new Authors(1);
   $author->delete();

Credits
=======

Morm is copyright (C) 2008-2009 _AF83: http://af83.com/ and Luc-Pascal Ceccaldi.

Contribute
==========

Morm is release under GNU GPL 3.

