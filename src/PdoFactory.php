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
        $defaultPdoOptions = [
            PDO::ATTR_TIMEOUT => 5,
            // We want emulation by default (faster for single queries). Disable if you want to
            // use proper native prepared statements
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $pdoOptions = array_merge($defaultPdoOptions, (isset($options['pdo']) ? $options['pdo'] : []));

        return new PDO($dsn, $username, $password, $pdoOptions);
    }
}
