<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb / Ehandelslogik i Lund AB
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
    protected $parameters;

    /**
     * Constructor.
     *
     * @param \PDOException $e
     * @param string        $sql
     * @param array         $parameters
     */
    public function __construct(\PDOException $e, $sql = '', array $parameters = [])
    {
        $this->sql = $sql;
        $this->parameters = $parameters;

        $extraMessage = '';
        if (!empty($this->sql)) {
            $extraMessage .= " [SQL: {$this->sql}]";
        }
        if (!empty($this->parameters)) {
            $extraMessage .= " [Parameters: " . implode(', ', $this->parameters) . "]";
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
    public function getParameters()
    {
        return $this->parameters;
    }
}
