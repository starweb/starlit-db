<?php

namespace Starlit\Db;

class AbstractDbEntityFetcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractDbEntityFetcher
     */
    protected $dbFetcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockDb;

    protected function setUp()
    {
        $this->mockDb = $this->createMock(Db::class);
        $this->dbFetcher = new TestFetcher($this->mockDb);
    }

    public function testGetLimitSql()
    {
        // Use reflection to make protected method accessible
        $method = new \ReflectionMethod($this->dbFetcher, 'getLimitSql');
        $method->setAccessible(true);

        $limit = '5';
        $limitSql = $method->invoke($this->dbFetcher, $limit);

        $this->assertEquals('LIMIT 5', $limitSql);
    }

    public function testGetLimitSqlPageItem()
    {
        // Use reflection to make protected method accessible
        $method = new \ReflectionMethod($this->dbFetcher, 'getLimitSql');
        $method->setAccessible(true);


        $pageItem = [1, 10];
        $limitSql = $method->invoke($this->dbFetcher, '', $pageItem);

        $this->assertEquals('LIMIT 0, 10', $limitSql);
    }

    public function testGetLimitSqlPageItemInvalidPageNo()
    {
        $this->expectException(\InvalidArgumentException::class);

        // Use reflection to make protected method accessible
        $method = new \ReflectionMethod($this->dbFetcher, 'getLimitSql');
        $method->setAccessible(true);

        $pageItem = [0, 10];
        $method->invoke($this->dbFetcher, '', $pageItem);
    }

    public function testGetFetchPaginationResult()
    {
        // Use reflection to make protected method accessible
        $method = new \ReflectionMethod($this->dbFetcher, 'getFetchPaginationResult');
        $method->setAccessible(true);


        $fakeObjects = [1, 2];
        $result = $method->invoke($this->dbFetcher, $fakeObjects, false);

        $this->assertEquals($fakeObjects, $result);
    }

    public function testGetFetchPaginationResultPagination()
    {
        // Use reflection to make protected method accessible
        $method = new \ReflectionMethod($this->dbFetcher, 'getFetchPaginationResult');
        $method->setAccessible(true);


        $fakeObjects = [1, 2];

        $this->mockDb->expects($this->once())
            ->method('fetchValue')
            ->will($this->returnValue(count($fakeObjects)));

        list($objects, $totalRowCount) = $method->invoke($this->dbFetcher, $fakeObjects, true);
        $this->assertEquals($fakeObjects, $objects);
        $this->assertEquals(count($fakeObjects), $totalRowCount);
    }

    public function testGetDbEntitiesFromRows()
    {
        $rows = [
            ['id' => 1, 'some_name' => 'asd'],
            ['id' => 2, 'some_name' => 'asd2'],
            ['id' => 1, 'some_name' => 'asd3'] // To test multiple rows with same key
        ];

        $method = new \ReflectionMethod($this->dbFetcher, 'getDbEntitiesFromRows');
        $method->setAccessible(true);

        $objects = $method->invokeArgs($this->dbFetcher, [&$rows]); // "&$rows" is trickery to fool PHP to work
        // with the "array &$rows" method signature, which will make the reflection invoke to fail otherwise

        $this->assertCount(2, $objects);
        $this->assertInstanceOf(TestFetcherEntity::class, $objects[1]);
        $this->assertEquals('asd3', $objects[1]->getSomeName());
    }

    public function testGetDbEntitiesFromRowsWithKey()
    {
        $rows = [
            ['id' => 1, 'some_name' => 'asd'],
            ['id' => 2, 'some_name' => 'asd2'],
        ];

        $method = new \ReflectionMethod($this->dbFetcher, 'getDbEntitiesFromRows');
        $method->setAccessible(true);

        $objects = $method->invokeArgs($this->dbFetcher, [&$rows, 'someName']); // "&$rows" is trickery to fool PHP to work
        // with the "array &$rows" method signature, which will make the reflection invoke to fail otherwise

        $this->assertEquals(1, $objects['asd']->getId());
    }

    public function testGetDbEntitiesFromRowsWithMultiKeyEntity()
    {
        $rows = [
            ['id' => 1, 'id2' => 5, 'some_value' => 'asd'],
            ['id' => 2, 'id2' => 6, 'some_value' => 'asd2'],
        ];

        $fetcher = new MultiKeyTestFetcher($this->mockDb);
        $method = new \ReflectionMethod($fetcher, 'getDbEntitiesFromRows');
        $method->setAccessible(true);

        $objects = $method->invokeArgs($fetcher, [&$rows]); // "&$rows" is trickery to fool PHP to work
        // with the "array &$rows" method signature, which will make the reflection invoke to fail otherwise

        $this->assertEquals([1, 5], $objects['1-5']->getPrimaryDbValue());
    }

    public function testGetDbEntitiesFromRowsFail()
    {
        $invalidDbFetcher = new TestIncompleteFetcher($this->mockDb);

        $this->expectException('\LogicException');

        $method = new \ReflectionMethod($invalidDbFetcher, 'getDbEntitiesFromRows');
        $method->setAccessible(true);
        $rows = [];
        $method->invokeArgs($invalidDbFetcher, [&$rows]);
    }

    public function testGetDbEntityFromRow()
    {
        // Make method accessible
        $refMethod = new \ReflectionMethod($this->dbFetcher, 'getDbEntityFromRow');
        $refMethod->setAccessible(true);

        $row = ['id' => 1, 'some_name' => 'asd'];
        $object = $refMethod->invoke($this->dbFetcher, $row);

        $this->assertInstanceOf(TestFetcherEntity::class, $object);
    }

    public function testGetDbEntityFromRowFail()
    {
        $invalidDbFetcher = new TestIncompleteFetcher($this->mockDb);

        $this->expectException(\LogicException::class);

        $method = new \ReflectionMethod($invalidDbFetcher, 'getDbEntityFromRow');
        $method->setAccessible(true);
        $method->invokeArgs($invalidDbFetcher, [[]]);
    }
}

class TestFetcher extends AbstractDbEntityFetcher
{
    protected $dbEntityClass = TestFetcherEntity::class;
}

class TestIncompleteFetcher extends AbstractDbEntityFetcher
{
}

class TestFetcherEntity extends AbstractDbEntity
{
    protected static $dbTableName = 'someTable';

    protected static $dbProperties = [
        'id' => ['type' => 'int'],
        'someName' => ['type' => 'string', 'default' => 'test', 'maxLength' => 5, 'required' => true],
        'someValue' => ['type' => 'bool']
    ];

    protected static $primaryDbPropertyKey = 'id';
}

class MultiKeyTestFetcher extends AbstractDbEntityFetcher
{
    protected $dbEntityClass = MultiKeyTestFetcherEntity::class;
}

class MultiKeyTestFetcherEntity extends AbstractDbEntity
{
    protected static $dbTableName = 'someOtherTable';

    protected static $dbProperties = [
        'id' => ['type' => 'int'],
        'id2' => ['type' => 'int'],
        'someValue' => ['type' => 'string'],
    ];

    protected static $primaryDbPropertyKey = ['id', 'id2'];
}
