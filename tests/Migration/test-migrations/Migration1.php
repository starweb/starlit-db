<?php

use Starlit\Db\Migration\AbstractMigration;

class Migration1 extends AbstractMigration
{
    public function up()
    {
        $this->db->exec('SOME SQL');
    }

    public function down()
    {
        $this->db->exec('SOME SQL');
    }
}
