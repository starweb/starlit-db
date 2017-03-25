<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db;

use Starlit\Db\Exception;

/**
 * A database entity service is intended to handle database operations for existing
 * entity objects, i.e. load, save and load secondary objects etc.
 *
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
class BasicDbEntityService
{
    /**
     * The database adapter/connection/handler.
     *
     * @var Db
     */
    protected $db;

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
     * Load object's values from database table.
     *
     * @param AbstractDbEntity $dbEntity
     * @throws Exception\EntityNotFoundException
     */
    public function load(AbstractDbEntity $dbEntity)
    {
        // Check that the primary key is set
        if (!$dbEntity->getPrimaryDbValue()) {
            throw new \InvalidArgumentException(
                'Database entity can not be loaded because primary value is not set'
            );
        }

        // Fetch ad from db
        $row = $this->db->fetchRow(
            '
                SELECT *
                FROM `' . $dbEntity->getDbTableName() . '`
                WHERE ' . $this->getPrimaryKeyWhereSql($dbEntity) . '
            ',
            $this->getPrimaryKeyWhereParameters($dbEntity)
        );

        if (!$row) {
            throw new Exception\EntityNotFoundException("Db entity[{$dbEntity->getPrimaryDbValue()}] does not exist");
        }

        $dbEntity->setDbDataFromRow($row);
    }

    /**
     * Save entity to database table.
     *
     * @param AbstractDbEntity $dbEntity
     * @return bool
     */
    public function save(AbstractDbEntity $dbEntity)
    {
        if ($dbEntity->shouldBeDeletedFromDbOnSave()) {
            // Only delete if previously saved to db
            if ($dbEntity->getPrimaryDbValue()) {
                $this->delete($dbEntity);
            }

            return false;
        }

        if ($dbEntity->shouldInsertOnDbSave()) {
            // Note that database data always contains all properties, with defaults for non set properties
            $dataToSave = $dbEntity->getDbData();
        } else {
            if ($dbEntity->hasModifiedDbProperties()) {
                $dataToSave = $dbEntity->getModifiedDbData();
            } else {
                // Return if no value has been modified and it's not an insert
                // (we always want to insert if no id exist, since some child objects might
                // depend on the this primary id being available)
                return false;
            }
        }

        // We don't the want to insert/update primary value unless forced insert
        if (!$dbEntity->shouldForceDbInsertOnSave()) {
            $primaryKey = $dbEntity->getPrimaryDbPropertyKey();
            if (is_array($primaryKey)) {
                foreach ($primaryKey as $keyPart) {
                    unset($dataToSave[$keyPart]);
                }
            } else {
                unset($dataToSave[$primaryKey]);
            }
        }

        // Check data
        $sqlData = [];
        foreach ($dataToSave as $propertyName => $value) {
            if (!empty($value) && is_scalar($value)
                && $dbEntity->getDbPropertyMaxLength($propertyName)
                && mb_strlen($value) > $dbEntity->getDbPropertyMaxLength($propertyName)
            ) {
                throw new \RuntimeException(
                    "Database field \"{$propertyName}\" exceeds field max length (value: \"{$value}\")"
                );
            } elseif (empty($value) && $dbEntity->getDbPropertyNonEmpty($propertyName)) {
                throw new \RuntimeException("Database field \"{$propertyName}\" is empty and required");
            } elseif (((is_scalar($value) && ((string) $value) === '') || (!is_scalar($value) && empty($value)))
                && $dbEntity->getDbPropertyRequired($propertyName)
            ) {
                throw new \RuntimeException("Database field \"{$propertyName}\" is required to be set");
            }

            // Set data keys db field format
            $fieldName = $dbEntity->getDbFieldName($propertyName);
            $sqlData[$fieldName] = $value;
        }


        // Insert new database row
        if ($dbEntity->shouldInsertOnDbSave()) {
            $this->db->insert(
                $dbEntity->getDbTableName(),
                $sqlData
            );

            if (!is_array($dbEntity->getPrimaryDbPropertyKey())
                && !empty($lastInsertId = $this->db->getLastInsertId())
            ) {
                $dbEntity->setPrimaryDbValue($lastInsertId);
            }
        // Update existing database row
        } else {
            $this->db->update(
                $dbEntity->getDbTableName(),
                $sqlData,
                $this->getPrimaryKeyWhereSql($dbEntity),
                $this->getPrimaryKeyWhereParameters($dbEntity)

            );
        }

        $dbEntity->clearModifiedDbProperties();
        $dbEntity->setForceDbInsertOnSave(false);

        return true;
    }

    /**
     * Delete entity's corresponding database row.
     *
     * @param AbstractDbEntity $dbEntity
     */
    public function delete(AbstractDbEntity $dbEntity)
    {
        if (!$dbEntity->getPrimaryDbValue()) {
            throw new \InvalidArgumentException('Primary database value not set');
        }

        $this->db->exec(
            'DELETE FROM `' . $dbEntity->getDbTableName() . '` WHERE ' . $this->getPrimaryKeyWhereSql($dbEntity),
            $this->getPrimaryKeyWhereParameters($dbEntity)
        );

        $dbEntity->setDeleted();
    }

    /**
     * @param AbstractDbEntity $dbEntity
     * @return string
     */
    protected function getPrimaryKeyWhereSql(AbstractDbEntity $dbEntity)
    {
        if (is_array($dbEntity->getPrimaryDbPropertyKey())) {
            $whereStrings = [];
            foreach ($dbEntity->getPrimaryDbFieldKey() as $primaryKeyPart) {
                $whereStrings[] = '`' . $primaryKeyPart . '` = ?';
            }
            $whereSql = implode(' AND ', $whereStrings);
        } else {
            $whereSql = '`' . $dbEntity->getPrimaryDbFieldKey() . '` = ?';
        }

        return $whereSql;
    }

    /**
     * @param AbstractDbEntity $dbEntity
     * @return array
     */
    protected function getPrimaryKeyWhereParameters(AbstractDbEntity $dbEntity)
    {
        if (is_array($dbEntity->getPrimaryDbPropertyKey())) {
            $whereParameters = $dbEntity->getPrimaryDbValue();
        } else {
            $whereParameters = [$dbEntity->getPrimaryDbValue()];
        }

        return $whereParameters;
    }
}
