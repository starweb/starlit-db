<?php

namespace Starlit\Db;

class AbstractDbEntityTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructorScalarInit()
    {
        $entity = new TestDbEntity(3);
        $this->assertEquals($entity->getPrimaryDbValue(), 3);
    }

    public function testCheckStaticProperties()
    {
        $this->expectException(\LogicException::class);

        new TestIncompleteDbEntity();
    }

    public function testGetDefaultDbData()
    {
        $entity = new TestDbEntity(3);
        $firstProperties = array_slice($entity->getDefaultDbData(), 0, 3);
        $this->assertEquals(['someId' => 0, 'someName' => 'test', 'someField' => false], $firstProperties);
    }

    public function testGetPrimaryDbValue()
    {
        $entity = new TestDbEntity(5);
        $this->assertEquals(5, $entity->getPrimaryDbValue());
    }

    public function testSetPrimaryDbValue()
    {
        $entity = new TestDbEntity();
        $entity->setPrimaryDbValue(6);
        $this->assertEquals(6, $entity->getPrimaryDbValue());
    }

    public function testSetGetPrimaryDbValueWithMultiKey()
    {
        $key = [5, 2];
        $entity = new TestDbEntityMultiPrimary();
        $entity->setPrimaryDbValue($key);
        $this->assertEquals($key, $entity->getPrimaryDbValue());
    }

    public function testSetPrimaryDbValueWithInvalidMultiKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $entity = new TestDbEntityMultiPrimary();
        $entity->setPrimaryDbValue(5);
    }

    public function testIsNewDbEntity()
    {
        $entity = new TestDbEntity();
        $this->assertTrue($entity->isNewDbEntity());

        $entity->setPrimaryDbValue(5);
        $this->assertFalse($entity->isNewDbEntity());
    }

    public function testIsNewDbEntityWithMultiKey()
    {
        $entity = new TestDbEntityMultiPrimary();
        $this->expectException(\LogicException::class);
        $entity->isNewDbEntity();
    }

    public function testSetDbValueFail()
    {
        $this->expectException(\InvalidArgumentException::class);

        $method = new \ReflectionMethod(AbstractDbEntity::class, 'SetDbValue');
        $method->setAccessible(true);
        $entity = new TestDbEntity();
        $this->assertEquals('blah', $method->invoke($entity, 'blalb', 'val'));
    }

    public function testSetDbValue()
    {
        $entity = new TestDbEntity();

        // Call protected set method
        $method = new \ReflectionMethod(TestDbEntity::class, 'setDbValue');
        $method->setAccessible(true);
        $method->invoke($entity, 'someName', 123, true);

        // Call protected get method
        $method = new \ReflectionMethod(TestDbEntity::class, 'getDbValue');
        $method->setAccessible(true);

        $this->assertEquals(123, $method->invoke($entity, 'someName'));
    }

    public function testSetDbValueNull()
    {
        $entity = new TestDbEntity();

        // Call protected set method
        $setMethod = new \ReflectionMethod(TestDbEntity::class, 'setDbValue');
        $setMethod->setAccessible(true);
        $getMethod = new \ReflectionMethod(TestDbEntity::class, 'getDbValue');
        $getMethod->setAccessible(true);


        $setMethod->invoke($entity, 'someFloat', null);
        $this->assertEquals($getMethod->invoke($entity, 'someFloat'), null);

        $setMethod->invoke($entity, 'someFloat', '');
        $this->assertEquals($getMethod->invoke($entity, 'someFloat'), null);

        $setMethod->invoke($entity, 'someOtherFloat', null);
        $this->assertEquals(0.0, $getMethod->invoke($entity, 'someOtherFloat'));
    }

    public function testSetDbValueDateTime()
    {
        $entity = new TestDbEntity();

        $dateTime = new \DateTime('2000-01-01 00:00:00');
        $entity->setSomeDate($dateTime);
        $this->assertSame($dateTime, $entity->getSomeDate());

        $entity->setSomeDate($dateTime->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(\DateTimeInterface::class, $entity->getSomeDate());
        $this->assertSame($dateTime->format('Y-m-d H:i:s'), $entity->getSomeDate()->format('Y-m-d H:i:s'));

        $entity->setSomeDate(null);
        $this->assertNull($entity->getSomeDate());
    }

    public function testSetDbValueNoChange()
    {
        $entity = new TestDbEntity();

        $entity->setSomeName('test'); // Same as default value
        $this->assertFalse($entity->isDbPropertyModified('someName'));
    }

    public function testSetDbValueForceChange()
    {
        $entity = new TestDbEntity();

        $entity->setSomeName('test', true, true); // Same as default value
        $this->assertTrue($entity->isDbPropertyModified('someName'));
    }
    
    public function testGetDbFieldName()
    {
        $entity = new TestDbEntity();
        $this->assertEquals('some_name', $entity->getDbFieldName('someName'));
    }

    public function testShouldInsertOnDbSave()
    {
        $entity = new TestDbEntity();
        $this->assertTrue($entity->shouldInsertOnDbSave());
        $entity->setPrimaryDbValue(5);
        $this->assertFalse($entity->shouldInsertOnDbSave());
        $entity->setForceDbInsertOnSave(true);
        $this->assertTrue($entity->shouldInsertOnDbSave());
    }

    public function testHasModifiedDbProperties()
    {
        $entity = new TestDbEntity();
        $this->assertFalse($entity->hasModifiedDbProperties());

        $entity->setSomeField(true);
        $this->assertTrue($entity->hasModifiedDbProperties());
    }

    public function testGetDbPropertyName()
    {
        $this->assertEquals('someName', TestDbEntity::getDbPropertyName('some_name'));
    }

    public function testGetModifiedDbData()
    {
        $entity = new TestDbEntity();
        $this->assertEmpty($entity->getModifiedDbData());
        $entity->setSomeField(true);
        $this->assertArrayHasKey('someField', $entity->getModifiedDbData());
    }

    public function clearModifiedDbProperty()
    {
        $entity = new TestDbEntity();
        $entity->setSomeField(true);
        $entity->setSomeFloat(5.0);
        $this->assertNotEmpty($entity->getModifiedDbData());

        $entity->clearModifiedDbProperty('someField');
        $this->assertFalse($entity->isDbPropertyModified('someField'));
        $this->assertNotEmpty($entity->getModifiedDbData());
    }

    public function testClearModifiedDbProperties()
    {
        $entity = new TestDbEntity();
        $entity->setSomeField(true);
        $this->assertNotEmpty($entity->getModifiedDbData());
        $entity->clearModifiedDbProperties();
        $this->assertEmpty($entity->getModifiedDbData());
    }

    public function testSetAllDbPropertiesAsModified()
    {
        $entity = new TestDbEntity();
        $this->assertEmpty($entity->getModifiedDbData());

        $entity->setAllDbPropertiesAsModified();
        $this->assertEquals($entity->getDbData(), $entity->getModifiedDbData());
    }

    public function test__call()
    {
        $entity = new TestDbEntity(5);
        $this->assertEquals(5, $entity->__call('getSomeId'));

        $entity->__call('setSomeId', [6]);
        $this->assertEquals(6, $entity->__call('getSomeId'));
        $this->assertTrue($entity->hasModifiedDbProperties());
    }

    public function test__callFail()
    {
        $entity = new TestDbEntity();
        $this->expectException('\BadMethodCallException');
        $entity->__call('getBlabla');
    }

    public function test__callFailArgCount()
    {
        $entity = new TestDbEntity();
        $this->expectException('\BadMethodCallException');
        $entity->__call('setSomeId');
    }

    public function testSetDbData()
    {
        $entity = new TestDbEntity();
        $entity->setDbData(['someName' => 'bla']);

        $this->assertContains('bla', $entity->getDbData());
    }

    public function testGetDbRowData()
    {
        $entity = new TestDbEntity();
        $entity->setDbData(['someName' => 'bla']);

        $this->assertArrayHasKey('some_name', $entity->getDbRowData());
    }

    public function testGetDbDataWithoutPrimary()
    {
        $entity = new TestDbEntity();
        $this->assertArrayNotHasKey($entity->getPrimaryDbPropertyKey(), $entity->getDbDataWithoutPrimary());
        $this->assertArrayHasKey('someField', $entity->getDbDataWithoutPrimary());
    }

    public function testGetDbDataWithoutPrimaryWithMultiKey()
    {
        $entity = new TestDbEntityMultiPrimary();

        foreach ($entity->getPrimaryDbPropertyKey() as $keyPart) {
            $this->assertArrayNotHasKey($keyPart, $entity->getDbDataWithoutPrimary());
        }
        $this->assertArrayHasKey('someField', $entity->getDbDataWithoutPrimary());
    }

    public function testSetDbDataFromRow()
    {
        // Test with less properties than there are
        $rowData = [
            'some_id' => 123,
            'some_name' => 'asd',
        ];
        $desiredResult = [
            'someId' => 123,
            'someName' => 'asd',
        ];
        $entity = new TestDbEntity();

        $entity->setDbDataFromRow($rowData);
        $this->assertEquals($desiredResult, array_slice($entity->getDbData(), 0, 2));

        // Test with more properties than there are
        $rowData2 = [
            'some_id' => 123,
            'some_name' => 'asd',
            'some_field' => '1',
            'some_float' => 123,
            'some_other_float' => 123.5,
            'unknown_prop' => 'asd',
        ];
        $desiredResult2 = [
            'someId' => 123,
            'someName' => 'asd',
            'someField' => true,
            'someFloat' => 123,
            'someOtherFloat' => 123.5
        ];

        $entity->setDbDataFromRow($rowData2);
        $this->assertEquals($desiredResult2, array_slice($entity->getDbData(), 0, 5));
    }

    public function testConstructWithRowData()
    {
        $rowData = [
            'some_id' => 123,
            'some_name' => 'asd',
        ];
        $desiredResult = [
            'someId' => 123,
            'someName' => 'asd',
        ];

        $entity = new TestDbEntity($rowData);

        $this->assertEquals($desiredResult, array_slice($entity->getDbData(), 0, 2));
    }

    public function testDeleteFromDbOnSave()
    {
        $entity = new TestDbEntity();
        $this->assertFalse($entity->shouldBeDeletedFromDbOnSave());
        $entity->setDeleteFromDbOnSave(true);
        $this->assertTrue($entity->shouldBeDeletedFromDbOnSave());
    }

    public function testForceDbInsertOnSave()
    {
        $entity = new TestDbEntity();
        $this->assertFalse($entity->shouldForceDbInsertOnSave());
        $entity->setForceDbInsertOnSave(true);
        $this->assertTrue($entity->shouldForceDbInsertOnSave());
    }

    public function testGetDbProperties()
    {
        $entity = new TestDbEntity();
        $this->assertArrayHasKey('someName', $entity->getDbProperties());
    }

    public function testGetDbPropertyMaxLength()
    {
        $entity = new TestDbEntity();
        $this->assertEquals(5, $entity->getDbPropertyMaxLength('someName'));
    }

    public function testGetDbPropertyRequired()
    {
        $entity = new TestDbEntity();
        $this->assertTrue($entity->getDbPropertyRequired('someName'));
    }

    public function testGetDbPropertyNonEmpty()
    {
        $entity = new TestDbEntity();
        $this->assertTrue($entity->getDbPropertyNonEmpty('someOtherFloat'));
    }

    public function testGetDbTableName()
    {
        $entity = new TestDbEntity();
        $this->assertEquals('someTable', $entity->getDbTableName());
    }

    public function testGetPrimaryDbPropertyKey()
    {
        $entity = new TestDbEntity();
        $this->assertEquals('someId', $entity->getPrimaryDbPropertyKey());
    }

    public function testGetPrimaryDbFieldKey()
    {
        $entity = new TestDbEntity();
        $this->assertEquals('some_id', $entity->getPrimaryDbFieldKey());
    }

    public function testGetPrimaryDbFieldKeyWithMultiKey()
    {
        $entity = new TestDbEntityMultiPrimary();
        $this->assertEquals(['some_id', 'some_other_id'], $entity->getPrimaryDbFieldKey());
    }

    public function testGetDbPropertyNames()
    {
        $entity = new TestDbEntity(1);

        $this->setUp();
        $propertyNames = $entity->getDbPropertyNames();
        $this->assertContains('someName', $propertyNames);
        $this->assertContains('someField', $propertyNames);
    }

    public function testGetDbFieldNames()
    {
        $entity = new TestDbEntity();
        $this->assertContains('some_name', $entity->getDbFieldNames());
        $this->assertContains('some_field', $entity->getDbFieldNames());
    }

    public function testGetPrefixedDbFieldNames()
    {
        $entity = new TestDbEntity();
        $prefixedDbFieldNames = $entity->getPrefixedDbFieldNames('t');
        $this->assertContains('t.some_name', $prefixedDbFieldNames);
        $this->assertContains('t.some_field', $prefixedDbFieldNames);
    }

    public function testSetDeleted()
    {
        $entity = new TestDbEntity();

        $this->assertFalse($entity->isDeleted());
        $entity->setDeleted(true);
        $this->assertTrue($entity->isDeleted());
    }

    public function testGetAliasedDbFieldNames()
    {
        $entity = new TestDbEntity();
        $newColumns = $entity->getAliasedDbFieldNames('t');

        $this->assertContains('t.some_id AS t_some_id', $newColumns);
        $this->assertContains('t.some_name AS t_some_name', $newColumns);
        $this->assertContains('t.some_field AS t_some_field', $newColumns);
    }

    public function testFilterStripDbRowData()
    {
        $entity = new TestDbEntity();
        $rawDbData = ['t_bla_bla' => 1, 't_test' => 2];
        $strippedData = $entity->filterStripDbRowData($rawDbData, 't');

        $this->assertEquals(['bla_bla' => 1, 'test' => 2], $strippedData);
    }

    public function testSerialize()
    {
        $entity = new TestDbEntity();
        $this->assertNotContains('private info', serialize($entity));
    }

    public function testUnserialize()
    {
        $entity = new TestDbEntity();
        $entity->publicProperty = 123;
        $unserializedEntity = unserialize(serialize($entity));
        $this->assertEquals(123, $unserializedEntity->publicProperty);
    }

    public function testMergeWith()
    {
        $entity = new TestDbEntity();
        $entity2 = new TestDbEntity();
        $entity2->setSomeName('Not modified', false);
        $entity2->setSomeFloat(123123);

        $entity->mergeWith($entity2);

        $this->assertNotEquals('Not modified', $entity->getSomeName());
        $this->assertEquals(123123, $entity->getSomeFloat());
    }
}

class TestDbEntity extends AbstractDbEntity
{
    protected static $dbTableName = 'someTable';

    protected static $dbProperties = [
        'someId'         => ['type' => 'int'],
        'someName'       => ['type' => 'string', 'default' => 'test', 'maxLength' => 5, 'required' => true],
        'someField'      => ['type' => 'bool'],
        'someFloat'      => ['type' => 'float', 'default' => null],
        'someOtherFloat' => ['type' => 'float', 'nonEmpty' => true, 'default' => 1.0],
        'someDate'       => ['type' => 'dateTime', 'required' => true, 'default' => null],
    ];

    protected static $primaryDbPropertyKey = 'someId';

    private $privateVar = 'private info';
}

class TestIncompleteDbEntity extends AbstractDbEntity
{
}

class TestDbEntityMultiPrimary extends AbstractDbEntity
{
    protected static $dbTableName = 'someTable';

    protected static $dbProperties = [
        'someId' => ['type' => 'int'],
        'someOtherId' => ['type' => 'int'],
        'someField' => ['type' => 'bool'],
    ];

    protected static $primaryDbPropertyKey = ['someId', 'someOtherId'];
}
