<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db\Exception;

/**
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
class QueryException extends DbException
{
    /***
     * @var string
     */
    protected $sql;

    /**
     * @var array
     */
    protected $dbParameters;

    /**
     * @param \PDOException $e
     * @param string        $sql
     * @param array         $dbParameters
     */
    public function __construct(\PDOException $e, $sql = '', array $dbParameters = [])
    {
        $this->sql = $sql;
        $this->dbParameters = $dbParameters;

        $extraMessage = '';
        if (!empty($this->sql)) {
            $extraMessage .= " [SQL: {$this->sql}]";
        }
        if (!empty($this->dbParameters)) {
            $extraMessage .= " [Parameters: " . implode(', ', $this->dbParameters) . "]";
        }

        parent::__construct($e, $extraMessage);
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @return array
     */
    public function getDbParameters()
    {
        return $this->dbParameters;
    }
}
