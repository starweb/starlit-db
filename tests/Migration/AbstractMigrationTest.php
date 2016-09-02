<?php

namespace Starlit\Db\Migration;

use Starlit\Db\Db;

class AbstractMigrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TestMigration15
     */
    protected $migration;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockDb;

    public function setUp()
    {
        $this->mockDb = $this->createMock(Db::class);
        $this->migration = new TestMigration15($this->mockDb);
    }

    public function testGetNumber()
    {
        $this->assertEquals(15, $this->migration->getNumber());
    }

    public function testGetNumberException()
    {
        $migration = new TestInvalidMigration($this->mockDb);

        $this->expectException(\LogicException::class);
        $migration->getNumber();
    }

    public function testUp()
    {
        $this->mockDb->expects($this->once())->method('exec');
        $this->migration->up();
    }

    public function testDown()
    {
        $this->mockDb->expects($this->once())->method('exec');
        $this->migration->down();
    }

    public function testDownDefault()
    {
        $this->mockDb->expects($this->never())->method('exec');

        $this->migration = new TestMigration16WithDefaultDown($this->mockDb);
        $this->migration->down();
    }
}

class TestMigration15 extends AbstractMigration
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

class TestMigration16WithDefaultDown extends AbstractMigration
{
    public function up()
    {
        $this->db->exec('SOME SQL');
    }
}

class TestInvalidMigration extends AbstractMigration
{
    public function up()
    {
    }
}
