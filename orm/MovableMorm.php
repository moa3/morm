<?php
/**
 * allow an object to have a position and move it easily
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class MovableMorm Extends Morm
{
    public function moveUp()
    {
        $this->moveToNull();
        $sql = "update `".$this->_table."` set position = position - 1 where position = ".$this->position + 1;
        if(SqlTools::sqlQuery($sql))
        {
            $sql = "update `".$this->_table."` set position = ".($this->position + 1)." where `".$this->_table."`.`".$this->_pkey."` = ".$this->{$this->_pkey};
            if(SqlTools::sqlQuery($sql))
            {
                return $this->updatePosition();
            }
            return false;
        }
        return false;
    }
    
    public function moveDown()
    {
        if($this->position == 0)
            throw new Exception("Can't move down first element");
        $this->moveToNull();
        $sql = "update `".$this->_table."` set position = position + 1 where position = ".$this->position - 1;
        if(SqlTools::sqlQuery($sql))
        {
            $sql = "update `".$this->_table."` set position = ".($this->position - 1)." where `".$this->_table."`.`".$this->_pkey."` = ".$this->{$this->_pkey};
            if(SqlTools::sqlQuery($sql))
            {
                return $this->updatePosition();
            }
            return false;
        }
        return false;
    }

    public function moveTo($position)
    {
        if($this->position == $position)
            throw new Exception("Can't move element to same position");
        $this->moveToNull();
        if($position > $this->position)
            $sql = "update `".$this->_table."` set position = position - 1 where position > ".$this->position." and position <= ".$position;
        else
            $sql = "update `".$this->_table."` set position = position + 1 where position < ".$this->position." and position >= ".$position;
        if(SqlTools::sqlQuery($sql))
        {
            $sql = "update `".$this->_table."` set position = ".$position." where `".$this->_table."`.`".$this->_pkey."` = ".$this->{$this->_pkey};
            if(SqlTools::sqlQuery($sql))
            {
                return $this->updatePosition();
            }
            return false;
        }
        return false;
    }

    public function moveToEnd()
    {
        $this->moveToNull();
        $sql = "update `".$this->_table."` set position = position - 1 where position > ".$this->position;
        if(SqlTools::sqlQuery($sql))
        {
            $sql = "update `".$this->_table."` set position = (select max(position) + 1 from `".$this->_table."`) where `".$this->_table."`.`".$this->_pkey."` = ".$this->{$this->_pkey};
            if(SqlTools::sqlQuery($sql))
            {
                return $this->updatePosition();
            }
            return false;
        }
        return false;
    }

    public function moveToBegin()
    {
        return $this->moveTo(0);
    }

    private function moveToNull()
    {
        $sql = "update `".$this->_table."` set position = NULL where `".$this->_table."`.`".$this->_pkey."` = ".$this->{$this->_pkey};
        if(SqlTools::sqlQuery($sql))
        {
            return true;
        }
        return false;
    }

    public function updatePosition()
    {
        $sql = "select position from `".$this->_table."` where `".$this->_table."`.`".$this->_pkey."` = ".$this->{$this->_pkey};
        if($res = SqlTools::sqlQuery($sql))
        {
            $pos = mysql_fetch_assoc($res);
            $this->position = $pos['position'];
            return true;
        }
        return false;
    }

    public function delete()
    {
        $position = $this->position;
        if(parent::delete())
        {
            $sql = "update `".$this->_table."` set position = position - 1 where position > ".$position;
            if(SqlTools::sqlQuery($sql))
            {
                return true;
            }
            return false;

        }
    }
}

