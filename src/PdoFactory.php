<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2019 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db;

use PDO;

class PdoFactory implements PdoFactoryInterface
{
    public function createPdo(string $dsn, string $username = null, string $password = null, array $options = []): PDO
    {
        return new PDO($dsn, $username, $password, $options);
    }
}
