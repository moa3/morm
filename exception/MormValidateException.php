<?php

class MormValidateException extends MormSqlException
{
    public $errors = array();

     public function __construct($message = 'Erreur de validation')
     {
         if(is_array($message)) 
          {
              $this->message = print_r($message,TRUE);
              $this->errors = $message;
          } 
          else 
          {
              $this->message = $message;
          }
     }
     

}
