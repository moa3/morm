<?php

class NoPrimaryKeySqlException extends MormSqlException
{
    public function __construct($message = 'No primary Key', $table)
    {
        parent::__construct($message);
    }
}
