<?php

use Starlit\Db\Migration\AbstractMigration;

class Migration2 extends AbstractMigration
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
