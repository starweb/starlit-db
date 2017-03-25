<?php

namespace Starlit\Db;

class BasicDbEntityServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockDb;

    /**
     * @var BasicDbEntityService
     */
    protected $dbService;

    protected function setUp()
    {
        $this->mockDb = $this->createMock(Db::class);
        $this->dbService = new BasicDbEntityService($this->mockDb);
    }

    public function testLoadThrowsExceptionWithInvalidEntity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $entity = new ServiceTestEntity();
        $this->dbService->load($entity);
    }

    public function testLoadThrowsExceptionWhenEntityDoesNotExist()
    {
        $this->mockDb->expects($this->any())
            ->method('fetchRow')
            ->will($this->returnValue(false));

        $this->expectException(\RuntimeException::class);
        $entity = new ServiceTestEntity(1);
        $this->dbService->load($entity);
    }

    public function testLoadReturnsLoadedEntity()
    {
        $this->mockDb->expects($this->any())
            ->method('fetchRow')
            ->will($this->returnValue(['id' => 1, 'some_name' => 'A name']));

        $entity = new ServiceTestEntity(1);
        $this->dbService->load($entity);
        $this->assertEquals(['id' => 1, 'someName' => 'A name', 'someValue' => 1], $entity->getDbData());
    }

    public function testSaveDoesntSaveUnchangedEntity()
    {
        $this->mockDb->expects($this->never())
            ->method('exec');

        $entity = new ServiceTestEntity(1);
        $this->assertFalse($this->dbService->save($entity));
    }

    public function testSaveInserts()
    {
        $entity = new ServiceTestEntity();
        $entity->setSomeName('A Name');

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->with($entity->getDbTableName(), ['some_name' => 'A Name', 'some_value' => 1]);

        $this->dbService->save($entity);
    }

    public function testSaveUpdates()
    {
        $entity = new ServiceTestEntity(3);
        $entity->setSomeName('A Name');

        $this->mockDb->expects($this->once())
            ->method('update')
            ->with(
                $entity->getDbTableName(),
                ['some_name' => 'A Name'],
                '`id` = ?',
                [3]
            );

        $this->dbService->save($entity);
    }

    public function testSaveUpdatesMultiKeyEntity()
    {
        $key = [3, 2];
        $entity = new ServiceTestMultiKeyEntity($key);
        $entity->setSomeName('A Name');

        $this->mockDb->expects($this->once())
            ->method('update')
            ->with(
                $entity->getDbTableName(),
                ['some_name' => 'A Name'],
                '`id` = ? AND `second_id` = ?',
                $key
            );

        $this->dbService->save($entity);
    }

    public function testSaveFailsDueToMaxLengthError()
    {
        $entity = new ServiceTestEntity();
        $entity->setSomeName('12345678901');

        $this->expectException('\RuntimeException');
        $this->dbService->save($entity);
    }

    public function testSaveFailsOnRequiredError()
    {
        $entity = new ServiceTestEntity();
        $entity->setSomeName('');

        $this->expectException('\RuntimeException');
        $this->dbService->save($entity);
    }

    public function testSaveFailsOnDateTimeRequiredError()
    {
        $entity = new ServiceDateTimeTestEntity();
        $entity->setSomeDate(null);

        $this->expectException('\RuntimeException');
        $this->dbService->save($entity);
    }

    public function testSaveFailsOnEmptyError()
    {
        $entity = new ServiceTestEntity();
        $entity->setSomeName('A Name');
        $entity->setSomeValue(0);

        $this->expectException('\RuntimeException');
        $this->dbService->save($entity);
    }

    public function testSaveDoesntDeleteWhenNoPrimary()
    {
        $entity = new ServiceTestEntity();
        $entity->setDeleteFromDbOnSave(true);

        $this->mockDb->expects($this->never())
            ->method('exec');

        $this->assertFalse($this->dbService->save($entity));
    }

    public function testSaveDeletes()
    {
        $entity = new ServiceTestEntity(1);
        $entity->setDeleteFromDbOnSave(true);

        $this->mockDb->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('DELETE'));

        $this->assertFalse($this->dbService->save($entity));
    }

    public function testDelete()
    {
        $entity = new ServiceTestEntity(1);

        $this->mockDb->expects($this->once())
          ->method('exec')
          ->with($this->stringContains('DELETE'));

        $this->dbService->delete($entity);
    }

    public function testDeleteThrowsExceptionOnInvalidEntity()
    {
        $entity = new ServiceTestEntity();

        $this->expectException(\InvalidArgumentException::class);
        $this->dbService->delete($entity);
    }
}

class ServiceTestEntity extends AbstractDbEntity
{
    protected static $dbTableName = 'someTable';

    protected static $dbProperties = [
        'id'             => ['type' => 'int'],
        'someName'       => ['type' => 'string', 'required' => true, 'maxLength' => 10],
        'someValue'      => ['type' => 'int', 'default' => 1, 'nonEmpty' => true],
    ];

    protected static $primaryDbPropertyKey = 'id';
}

class ServiceTestMultiKeyEntity extends AbstractDbEntity
{
    protected static $dbTableName = 'someTable';

    protected static $dbProperties = [
        'id'             => ['type' => 'int'],
        'secondId'       => ['type' => 'int'],
        'someName'       => ['type' => 'string', 'required' => true, 'maxLength' => 10],
        'someValue'      => ['type' => 'int', 'default' => 1, 'nonEmpty' => true],
    ];

    protected static $primaryDbPropertyKey = ['id', 'secondId'];
}

class ServiceDateTimeTestEntity extends AbstractDbEntity
{
    protected static $dbTableName = 'someTable';

    protected static $dbProperties = [
        'id'             => ['type' => 'int'],
        'someDate'       => ['type' => 'dateTime', 'required' => true, 'default' => null],
    ];

    protected static $primaryDbPropertyKey = 'id';
}