<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db;

use Starlit\Db\Exception\ConnectionException;
use Starlit\Db\Exception\QueryException;
use \PDO;
use \PDOException;
use \PDOStatement;

/**
 * Extended PDO database wrapper.
 *
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
class Db
{
    /**
     * Database handle/connection.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $dsn;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var bool
     */
    protected $hasActiveTransaction = false;

    /**
     * Constructor.
     *
     * @param string|PDO  $hostDsnOrPdo A MySQL host, a dsn or an existing PDO instance.
     * @param string|null $username
     * @param string|null $password
     * @param string|null $database
     * @param array       $options
     */
    public function __construct(
        $hostDsnOrPdo,
        $username = null,
        $password = null,
        $database = null,
        array $options = []
    ) {
        if ($hostDsnOrPdo instanceof PDO) {
            $this->pdo = $hostDsnOrPdo;
        } elseif (strpos($hostDsnOrPdo, ':') !== false) {
            $this->dsn = $hostDsnOrPdo;
        } else {
            $this->dsn = "mysql:host={$hostDsnOrPdo}" . ($database ? ";dbname={$database}" : '');
        }

        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
    }

    /**
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return;
        }

        $retries = isset($this->options['connectRetries'])? $this->options['connectRetries'] : 0;
        do {
            try {
                $defaultPdoOptions = [
                    PDO::ATTR_TIMEOUT            => 5,
                    // We want emulation by default (faster for single queries). Disable if you want to
                    // use proper native prepared statements
                    PDO::ATTR_EMULATE_PREPARES   => true,
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                $pdoOptions = $defaultPdoOptions + (isset($this->options['pdo']) ? $this->options['pdo'] : []);

                $this->pdo = new PDO(
                    $this->dsn,
                    $this->username,
                    $this->password,
                    $pdoOptions
                );

                return;
            } catch (PDOException $e) {
                if ($this->isConnectionExceptionCausedByConnection($e) && $retries > 0) {
                    // Sleep for 100 - 500 ms until next retry
                    usleep(rand(100000, 500000));
                } else {
                    throw new ConnectionException($e);
                }
            }

        } while ($retries-- > 0);
    }

    /**
     * @param PDOException $exception
     * @return bool
     */
    private function isConnectionExceptionCausedByConnection(PDOException $exception)
    {
        return in_array($exception->getCode(), [
            2002, // Can't connect to MySQL server (Socket)
            2003, // Can't connect to MySQL server (TCP)
            2006, // MySQL server has gone away
            2013, // Lost connection to MySQL server during query
        ]);
    }

    /**
     * Close the database connection.
     */
    public function disconnect()
    {
        $this->pdo = null;
    }

    /**
     */
    public function reconnect()
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Check if database connection is open.
     *
     * @return bool
     */
    public function isConnected()
    {
        return ($this->pdo instanceof PDO);
    }

    /**
     * Returns the PDO handle.
     *
     * Can be used to gain access to any special PDO methods.
     *
     * @return PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Creates and executes a PDO statement.
     *
     * @param string $sql
     * @param array  $parameters
     * @return PDOStatement
     */
    protected function executeQuery($sql, array $parameters = [])
    {
        $this->connect();

        $dbParameters = $this->prepareParameters($parameters);
        try {
            $pdoStatement = $this->pdo->prepare($sql);
            $pdoStatement->execute($dbParameters);

            return $pdoStatement;
        } catch (PDOException $e) {
            throw new QueryException($e, $sql, $dbParameters);
        }
    }

    /**
     * Execute an SQL statement and return the number of affected rows.
     *
     * @param string $sql
     * @param array  $parameters
     * @return int The number of rows affected
     */
    public function exec($sql, array $parameters = [])
    {
        $statement = $this->executeQuery($sql, $parameters);

        return $statement->rowCount();
    }

    /**
     * Execute an SQL statement and return the first row.
     *
     * @param string $sql
     * @param array  $parameters
     * @param bool   $indexedKeys
     * @return array|false
     */
    public function fetchRow($sql, array $parameters = [], $indexedKeys = false)
    {
        $statement = $this->executeQuery($sql, $parameters);

        return $statement->fetch($indexedKeys ? PDO::FETCH_NUM : PDO::FETCH_ASSOC);
    }

    /**
     * Execute an SQL statement and return all rows as an array.
     *
     * @param string $sql
     * @param array  $parameters
     * @param bool   $indexedKeys
     * @return array
     */
    public function fetchRows($sql, array $parameters = [], $indexedKeys = false)
    {
        $statement = $this->executeQuery($sql, $parameters);

        return $statement->fetchAll($indexedKeys ? PDO::FETCH_NUM : PDO::FETCH_ASSOC);
    }

    /**
     * Execute an SQL statement and return the first column of the first row.
     *
     * @param string $sql
     * @param array  $parameters
     * @return string|false
     */
    public function fetchValue($sql, array $parameters = [])
    {
        $statement = $this->executeQuery($sql, $parameters);

        return $statement->fetchColumn(0);
    }

    /**
     * Quote a value for a safe use in query (eg. bla'bla -> 'bla''bla').
     *
     * @param mixed $value
     * @return string
     */
    public function quote($value)
    {
        $this->connect();

        return $this->pdo->quote($value);
    }

    /**
     * Get id of the last inserted row.
     *
     * @return int
     */
    public function getLastInsertId()
    {
        $this->connect();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Prepare parameters for database use.
     *
     * @param array $parameters
     * @return array
     */
    protected function prepareParameters(array $parameters = [])
    {
        foreach ($parameters as &$parameterValue) {
            if (is_bool($parameterValue)) {
                $parameterValue = (int) $parameterValue;
            } elseif ($parameterValue instanceof \DateTimeInterface) {
                $parameterValue = $parameterValue->format('Y-m-d H:i:s');
            } elseif (!is_scalar($parameterValue) && $parameterValue !== null) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid db parameter type "%s"', gettype($parameterValue))
                );
            }
        }
        unset($parameterValue);

        return $parameters;
    }

    /**
     * Begin transaction (turns off autocommit mode).
     *
     * @param bool $onlyIfNoActiveTransaction
     * @return bool
     */
    public function beginTransaction($onlyIfNoActiveTransaction = false)
    {
        $this->connect();

        if ($onlyIfNoActiveTransaction && $this->hasActiveTransaction()) {
            return false;
        }

        $this->pdo->beginTransaction();
        $this->hasActiveTransaction = true;

        return true;
    }

    /**
     * Commits current active transaction (restores autocommit mode).
     */
    public function commit()
    {
        $this->connect();

        $this->pdo->commit();
        $this->hasActiveTransaction = false;
    }

    /**
     * Rolls back current active transaction (restores autocommit mode).
     */
    public function rollBack()
    {
        $this->connect();

        $this->pdo->rollBack();
        $this->hasActiveTransaction = false;
    }

    /**
     * @return bool
     */
    public function hasActiveTransaction()
    {
        return $this->hasActiveTransaction;
    }

    /**
     * @param string $table
     * @param array $data
     * @param bool $updateOnDuplicateKey
     * @return int The number of rows affected
     */
    public function insert($table, array $data, $updateOnDuplicateKey = false)
    {
        $sql = 'INSERT INTO `' . $table . '`';
        if (empty($data)) {
            $sql .= "\nVALUES ()";
            $parameters = [];
        } else {
            $fields = array_keys($data);
            $fieldsSql = '`' . implode('`, `', $fields) . '`';
            $placeholdersSql = implode(', ', array_fill(0, count($fields), '?'));

            $sql .= ' (' . $fieldsSql . ")\n";
            $sql .= 'VALUES (' . $placeholdersSql . ")\n";

            $parameters = array_values($data);

            if ($updateOnDuplicateKey) {
                $assignmentSql = $this->getAssignmentSql(array_fill_keys($fields, '?'));
                $sql .= 'ON DUPLICATE KEY UPDATE ' . $assignmentSql;
                $parameters = array_merge($parameters, $parameters);
            }
        }

        return $this->exec($sql, $parameters);
    }

    /**
     * @param array $assignmentData
     * @return string
     */
    protected function getAssignmentSql(array $assignmentData)
    {
        $assignments = [];
        foreach ($assignmentData as $field => $value) {
            $assignments[] = sprintf('`%s` = %s', $field, $value);
        }

        return implode(', ', $assignments);
    }

    /**
     * @param string $table
     * @param array  $data
     * @param string $whereSql
     * @param array  $whereParameters
     * @return int The number of rows affected
     */
    public function update($table, array $data, $whereSql = '', array $whereParameters = [])
    {
        $fields = array_keys($data);
        $assignmentSql = $this->getAssignmentSql(array_fill_keys($fields, '?'));

        $sql = 'UPDATE `' . $table . "`\n"
            . 'SET ' . $assignmentSql
            . ($whereSql ? "\nWHERE " . $whereSql : '')
        ;

        $parameters = array_merge(array_values($data), $whereParameters);

        return $this->exec($sql, $parameters);
    }
}
