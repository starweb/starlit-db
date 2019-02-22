<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db;

use PDO;

interface PdoFactoryInterface
{
    public function createPdo(string $dsn, string $username = null, string $password = null, array $options = []): PDO;
}
