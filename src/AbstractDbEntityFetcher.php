<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db;

/**
 * A database entity fetcher is intended to fetch new entities from the database.
 *
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
abstract class AbstractDbEntityFetcher
{
    /**
     * The database adapter/connection/handler.
     *
     * @var Db
     */
    protected $db;

    /**
     * Database entity's class name (optional, meant to be overridden).
     *
     * @var string
     */
    protected $dbEntityClass;

    /**
     * Constructor.
     *
     * @param Db $db
     */
    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * Helper method to get LIMIT SQL and paging flag from limit parameter.
     *
     * @param int   $limit           A limit number (like 5)
     * @param array $pageItem        A pagination array like [1, 10], where 1 is the page number and 10 the number of
     *                               rows per page (first page is 1).
     * @return string
     */
    protected function getLimitSql($limit, array $pageItem = [])
    {
        $limitSql = '';
        if (!empty($limit) || !empty($pageItem)) {
            $limitSql = 'LIMIT ';
            if (!empty($pageItem)) {
                list($pageNo, $rowsPerPage) = $pageItem;

                $pageNo = (int) $pageNo;
                if ($pageNo < 1) {
                    throw new \InvalidArgumentException("Invalid pagination page number \"{$pageNo}\"");
                }
                $rowsPerPage = (int) $rowsPerPage;

                // Offset example: "10" skips the first 10 rows, start showing the 11th
                $offset = ($pageNo - 1) * $rowsPerPage;
                $limitSql .= sprintf('%d, %d', $offset, $rowsPerPage);
            } else {
                $limitSql .= (int) $limit;
            }
        }

        return $limitSql;
    }

    /**
     * Helper method to get pagination result array if pagination is requested.
     *
     * @param array $objects
     * @param bool  $pagination
     * @return array
     */
    protected function getFetchPaginationResult(array $objects, $pagination)
    {
        if ($pagination) {
            $totalRowCount = $this->db->fetchValue('SELECT FOUND_ROWS()');

            return [$objects, $totalRowCount];
        } else {
            return $objects;
        }
    }

    /**
     * @param array  $rows
     * @param string $keyPropertyName Optional, will default to primary if not provided
     * @return array
     */
    protected function getDbEntitiesFromRows(array &$rows, $keyPropertyName = null)
    {
        if (!$this->dbEntityClass) {
            throw new \LogicException('No db entity class set');
        }

        $entityClass = $this->dbEntityClass;
        if (!empty($keyPropertyName)) {
            $keyDbFieldName = AbstractDbEntity::getDbFieldName($keyPropertyName);
        } else {
            $keyDbFieldName = $entityClass::getPrimaryDbFieldKey();
        }

        $keyIsArray = is_array($keyDbFieldName);
        $dbObjects = [];
        foreach ($rows as $row) {
            if ($keyIsArray) {
                $key = implode('-', array_intersect_key($row, array_flip($keyDbFieldName)));
            } else {
                $key = $row[$keyDbFieldName];
            }

            // If multiple rows with same key, we assume the secondary rows are secondary
            // information (like language rows), which we can add to the same object by
            // calling setRawDbData again manually.
            if (isset($dbObjects[$key])) {
                $this->setDbEntityDataFromRow($dbObjects[$key], $row);
            } else {
                $dbObjects[$key] = $this->createNewDbEntity($row);
            }
        }

        return $dbObjects;
    }

    /**
     * @param array $rowData
     * @return AbstractDbEntity
     */
    protected function createNewDbEntity(array $rowData)
    {
        $entityClass = $this->dbEntityClass;
        $dbEntity = new $entityClass();
        $this->setDbEntityDataFromRow($dbEntity, $rowData);

        return $dbEntity;
    }

    /**
     * @param AbstractDbEntity $dbEntity
     * @param array            $rowData
     */
    protected function setDbEntityDataFromRow(AbstractDbEntity $dbEntity, array $rowData)
    {
        $dbEntity->setDbDataFromRow($rowData);
    }

    /**
     * @param array|false $row
     * @return AbstractDbEntity|null
     */
    protected function getDbEntityFromRow($row)
    {
        if (!$this->dbEntityClass) {
            throw new \LogicException('No db entity class set');
        }

        return $row ? $this->createNewDbEntity($row) : null;
    }
}
