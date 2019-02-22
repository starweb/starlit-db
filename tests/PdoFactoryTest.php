<?php declare(strict_types=1);

namespace Starlit\Db;

use PDO;

class PdoFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PdoFactoryInterface
     */
    private $factory;

    protected function setUp()
    {
        $this->factory = new PdoFactory();
    }

    public function testConstruction()
    {
        $this->assertInstanceOf(PdoFactoryInterface::class, $this->factory);
    }

    public function testCreatePdo()
    {
        $pdo = $this->factory->createPdo('sqlite::memory');
        $this->assertInstanceOf(PDO::class, $pdo);
    }
}
