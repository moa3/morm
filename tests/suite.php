<?php
require_once('prepend.php');

class AllTests extends TestSuite {
    function AllTests() {
        $this->TestSuite('All tests');
        $testsFile = array('basics.php', 'fielddesc.php', 
                           'tabledesc.php', 'sqltool.php', 
                           'oneToMany.php');
        foreach ($testsFile as $file)
        {
            $this->addFile(dirname(__FILE__) .'/'. $file);
        }
    }
}
?>
