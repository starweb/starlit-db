<?php

namespace Starlit\Db\Exception;

class QueryExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var QueryException
     */
    private $exception;

    private $sql = 'SELECT * FROM `table` WHERE `column_1` = ? AND `column_2` = ?';
    private $parameters = ['a', 'b'];

    public function setUp()
    {
        $mockPdoException = $this->createMock(\PDOException::class);

        $this->exception = new QueryException($mockPdoException, $this->sql, $this->parameters);
    }

    public function testGetMessage()
    {
        $message = $this->exception->getMessage();

        $this->assertContains($this->sql, $message);
        $this->assertContains($this->parameters[0], $message);
        $this->assertContains($this->parameters[1], $message);
    }

    public function testGetSql()
    {
        $this->assertEquals($this->sql, $this->exception->getSql());
    }

    public function testGetDbParameters()
    {
        $this->assertEquals($this->parameters, $this->exception->getDbParameters());
    }
}
