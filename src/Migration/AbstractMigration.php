<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db\Migration;

use Starlit\Db\Db;

/**
 * Concrete migration class names must contain a sequential migration number, eg. "Migration1".
 *
 * Names can also include something more meaningful, eg. "Migration14CreateAuthorsTable".
 *
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
abstract class AbstractMigration
{
    /**
     * @var Db;
     */
    protected $db;

    /**
     * @param Db $db
     */
    final public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * @return int
     */
    public function getNumber()
    {
        if (!preg_match('/\d+/', get_class($this), $matches) ||  !($number = (int) $matches[0])) {
            throw new \LogicException("Invalid migration class name (must include a migration number)");
        }

        return $number;
    }

    /**
     * Actions to be performed when migrating up to this version (e.g. 6.1.0 -> 6.2.0)
     */
    abstract public function up();

    /**
     * Actions to be performed when migrating down from this version (e.g. 6.2.0 -> 6.0.0)
     */
    public function down()
    {
    }
}
