<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db;

use Starlit\Utils\Str;
use Starlit\Utils\Arr;

/**
 * Abstract class to model a single database row into an object.
 *
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
abstract class AbstractDbEntity implements \Serializable
{
    /**
     * The database table name (meant to be overridden).
     *
     * @var string
     */
    protected static $dbTableName;

    /**
     * Entity's database properties and their attributes (meant to be overridden).
     * Example format:
     *
     * $dbProperties = [
     *     'productId' => ['type' => 'int'],
     *     'otherId'   => ['type' => 'int', 'required' => true,],
     *     'name'      => ['type' => 'string', 'maxLength' => 10, 'required' => true, 'default' => 'Some name'],
     * ];
     *
     * 'type' => 'int'     Corresponding PHP type (required).
     * 'required' => true  The value have to be set (not '', null, false)
     * 'nonEmpty' => true  The value should not be empty ('', 0, null)
     *
     * Properties correspond to database table's columns but words are
     * camel cased instead of separated with underscore (_) as in the database.
     *
     * @var array
     */
    protected static $dbProperties = [];

    /**
     * Object database field name that is used for primary key (meant to be overridden).
     * Should be camel cased as it maps to the dbFields array.
     *
     * @var string|array
     */
    protected static $primaryDbPropertyKey;

    /**
     * @var array
     */
    private static $cachedDefaultDbData = [];

    /**
     * @var array
     */
    private static $cachedDbPropertyNames;

    /**
     * @var array
     */
    private static $cachedDbFieldNames;

    /**
     * @var array
     */
    private static $typeDefaults = [
        'string'   => '',
        'int'      => 0,
        'float'    => 0.0,
        'bool'     => false,
        'dateTime' => null,
    ];

    /**
     * Database row data with field names and their values.
     *
     * @var array
     */
    private $dbData = [];

    /**
     * Database fields that has had their value modified since init/load.
     *
     * @var array
     */
    private $modifiedDbProperties = [];

    /**
     * @var bool
     */
    private $deleteFromDbOnSave = false;

    /**
     * @var bool
     */
    private $deleted = false;

    /**
     * @var bool
     */
    private $forceDbInsertOnSave = false;

    /**
     * Constructor.
     *
     * @param mixed $primaryDbValueOrRowData
     */
    public function __construct($primaryDbValueOrRowData = null)
    {
        self::checkStaticProperties();

        // Set default values
        $this->dbData = $this->getDefaultDbData();

        // Override default values with provided values
        if ($primaryDbValueOrRowData !== null) {
            $this->setPrimaryDbValueOrRowData($primaryDbValueOrRowData);
        }
    }

    /**
     * Make sure that class has all necessary static properties set.
     */
    private static function checkStaticProperties()
    {
        static $checkedClasses = [];
        if (!in_array(static::class, $checkedClasses)) {
            if (empty(static::$dbTableName)
                || empty(static::$dbProperties)
                || empty(static::$primaryDbPropertyKey)
                || (is_scalar(static::$primaryDbPropertyKey)
                    && !isset(static::$dbProperties[static::$primaryDbPropertyKey]['type']))
                || (is_array(static::$primaryDbPropertyKey)
                    && !Arr::allIn(static::$primaryDbPropertyKey, array_keys(static::$dbProperties)))
            ) {
                throw new \LogicException("All db entity's static properties not set");
            }
            $checkedClasses[] = static::class;
        }
    }

    /**
     * @param mixed $primaryDbValueOrRowData
     */
    public function setPrimaryDbValueOrRowData($primaryDbValueOrRowData = null)
    {
        // Row data would ba an associative array (not sequential, that would indicate a multi column primary key)
        if (is_array($primaryDbValueOrRowData) && !isset($primaryDbValueOrRowData[0])) {
            $this->setDbDataFromRow($primaryDbValueOrRowData);
        } else {
            $this->setPrimaryDbValue($primaryDbValueOrRowData);
        }
    }

    /**
     * Get all default database values.
     *
     * @return array
     */
    public function getDefaultDbData()
    {
        $class = get_called_class();
        if (!isset(self::$cachedDefaultDbData[$class])) {
            self::$cachedDefaultDbData[$class] = [];
            foreach (array_keys(static::$dbProperties) as $propertyName) {
                self::$cachedDefaultDbData[$class][$propertyName] = $this->getDefaultDbPropertyValue($propertyName);
            }
        }

        return self::$cachedDefaultDbData[$class];
    }

    /**
     * Get default db value (can be overridden if non static default values need to be used).
     *
     * @param string $propertyName
     * @return mixed
     */
    public function getDefaultDbPropertyValue($propertyName)
    {
        // A default value is set
        if (array_key_exists('default', static::$dbProperties[$propertyName])) {
            $defaultValue = static::$dbProperties[$propertyName]['default'];
        // No default value set, use default for type
        } else {
            $defaultValue = self::$typeDefaults[static::$dbProperties[$propertyName]['type']];
        }

        return $defaultValue;
    }

    /**
     * @return mixed
     */
    public function getPrimaryDbValue()
    {
        if (is_array(static::$primaryDbPropertyKey)) {
            $primaryValues = [];
            foreach (static::$primaryDbPropertyKey as $keyPart) {
                $primaryValues[] = $this->dbData[$keyPart];
            }

            return $primaryValues;
        }

        return $this->dbData[static::$primaryDbPropertyKey];
    }

    /**
     * @param mixed $primaryDbValue
     */
    public function setPrimaryDbValue($primaryDbValue)
    {
        if (is_array(static::$primaryDbPropertyKey)) {
            if (!is_array($primaryDbValue)) {
                throw new \InvalidArgumentException("Primary db value should be an array");
            }

            reset($primaryDbValue);
            foreach (static::$primaryDbPropertyKey as $keyPart) {
                $this->dbData[$keyPart] = current($primaryDbValue);
                next($primaryDbValue);
            }
        } else {
            $this->dbData[static::$primaryDbPropertyKey] = $primaryDbValue;
        }
    }

    /**
     * @return bool
     */
    public function isNewDbEntity()
    {
        if (is_array(static::$primaryDbPropertyKey)) {
            // Multiple column keys have to use explicit force insert because we have no way
            // to detect if it's a new entity (can't leave more than one primary field empty on insert because
            // db can't have two auto increment columns)
            throw new \LogicException("Can't detect if multi column primary key is a new entity");
        }

        return !$this->getPrimaryDbValue();
    }

    /**
     * @return bool
     */
    public function shouldInsertOnDbSave()
    {
        return (!is_array(static::$primaryDbPropertyKey) && $this->isNewDbEntity())
            || $this->shouldForceDbInsertOnSave();
    }

    /**
     * Set a row field value.
     *
     * @param string $property
     * @param mixed  $value
     * @param bool   $setAsModified
     * @param bool   $force
     */
    protected function setDbValue($property, $value, $setAsModified = true, $force = false)
    {
        if (!isset(static::$dbProperties[$property])) {
            throw new \InvalidArgumentException("No database entity property[{$property}] exists");
        }

        // Don't set type if value is null and allowed (allowed currently indicated by default => null)
        $nullIsAllowed = (array_key_exists('default', static::$dbProperties[$property])
            && static::$dbProperties[$property]['default'] === null);
        if (!($value === null && $nullIsAllowed)) {
            $type = static::$dbProperties[$property]['type'];
            // Set null when empty and default is null
            if ($value === '' && $nullIsAllowed) {
                 $value = null;
            } elseif ($type === 'dateTime') {
                if (!($value instanceof \DateTimeInterface)) {
                    $value = $this->createDateTimeDbValue($value);
                }
            } else {
                settype($value, $type);
            }
        }

        if ($this->dbData[$property] !== $value || $force) {
            $this->dbData[$property] = $value;

            if ($setAsModified && !$this->isDbPropertyModified($property)) {
                $this->modifiedDbProperties[] = $property;
            }
        }
    }

    /**
     * @param string $value
     * @return \DateTime|\Carbon\Carbon|null
     */
    protected function createDateTimeDbValue($value)
    {
        static $carbonExists = null;
        if ($carbonExists === true
            || ($carbonExists === null && ($carbonExists = class_exists(\Carbon\Carbon::class)))
        ) {
            return new \Carbon\Carbon($value);
        }

        return new \DateTime($value);
    }

    /**
     * Get a database field value.
     *
     * @param string $property
     * @return mixed
     */
    protected function getDbValue($property)
    {
        return $this->dbData[$property];
    }

    /**
     * Get raw (with underscore as word separator as it is formatted in database)
     * field name from a object field property name (camelcased).
     *
     * @param string $propertyName
     * @return string
     */
    public static function getDbFieldName($propertyName)
    {
        if (!isset(self::$cachedDbFieldNames[$propertyName])) {
            self::$cachedDbFieldNames[$propertyName] = Str::camelToSeparator($propertyName);
        }

        return self::$cachedDbFieldNames[$propertyName];
    }

    /**
     * Get object field property name (camelCased) from database field name (underscore separated).
     *
     * @param string $dbFieldName
     * @return string
     */
    public static function getDbPropertyName($dbFieldName)
    {
        if (!isset(self::$cachedDbPropertyNames[$dbFieldName])) {
            self::$cachedDbPropertyNames[$dbFieldName] = Str::separatorToCamel($dbFieldName);
        }

        return self::$cachedDbPropertyNames[$dbFieldName];
    }

    /**
     * @return bool
     */
    public function hasModifiedDbProperties()
    {
        return !empty($this->modifiedDbProperties);
    }

    /**
     * @param string $property
     * @return bool
     */
    public function isDbPropertyModified($property)
    {
        return in_array($property, $this->modifiedDbProperties);
    }

    /**
     * @return array
     */
    public function getModifiedDbData()
    {
        return array_intersect_key($this->dbData, array_flip($this->modifiedDbProperties));
    }

    /**
     * @param string $property
     */
    public function clearModifiedDbProperty($property)
    {
        if (($key = array_search($property, $this->modifiedDbProperties))) {
            unset($this->modifiedDbProperties[$key]);
        }
    }

    public function clearModifiedDbProperties()
    {
        $this->modifiedDbProperties = [];
    }

    public function setAllDbPropertiesAsModified()
    {
        $this->modifiedDbProperties = array_keys(static::$dbProperties);
    }

    /**
     * Magic method used to automate getters & setters for row data.
     *
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    public function __call($name, array $arguments = [])
    {
        $propertyName = lcfirst(substr($name, 3));

        if (strpos($name, 'get') === 0 && isset(static::$dbProperties[$propertyName])) {
            return $this->getDbValue($propertyName);
        } elseif (strpos($name, 'set') === 0 && isset(static::$dbProperties[$propertyName])) {
            $argumentCount = count($arguments);
            if ($argumentCount >= 1 && $argumentCount <= 3) {
                return $this->setDbValue($propertyName, ...$arguments);
            } else {
                throw new \BadMethodCallException("Invalid argument count[{$argumentCount}] for {$name}()");
            }
        } else {
            throw new \BadMethodCallException("No method named {$name}()");
        }
    }

    /**
     * Set database fields' data.
     *
     * @param array $data
     */
    public function setDbData(array $data)
    {
        foreach (array_keys(static::$dbProperties) as $propertyName) {
            if (array_key_exists($propertyName, $data)) {
                $this->setDbValue($propertyName, $data[$propertyName], true);
            }
        }
    }

    /**
     * Set db data from raw database row data with field names in database format.
     *
     * @param array $rowData
     */
    public function setDbDataFromRow(array $rowData)
    {
        // If there are less row data than properties, use rows as starting point (optimization)
        if (count($rowData) < count(static::$dbProperties)) {
            foreach ($rowData as $dbFieldName => $value) {
                $propertyName = static::getDbPropertyName($dbFieldName);
                if (isset(static::$dbProperties[$propertyName])) {
                    $this->setDbValue($propertyName, $value, false);
                }
            }
        // If there are more row data than properties, use properties as starting point
        } else {
            foreach (array_keys(static::$dbProperties) as $propertyName) {
                $fieldName = static::getDbFieldName($propertyName);
                if (array_key_exists($fieldName, $rowData)) {
                    $this->setDbValue($propertyName, $rowData[$fieldName], false);
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getDbData()
    {
        return $this->dbData;
    }

    /**
     * @return array
     */
    public function getDbRowData()
    {
        $rowData = [];
        foreach ($this->getDbData() as $propertyName => $value) {
            $dbFieldName = static::getDbFieldName($propertyName);
            $rowData[$dbFieldName] = $value;
        }

        return $rowData;
    }

    /**
     * @return array
     */
    public function getDbDataWithoutPrimary()
    {
        $dbDataWithoutPrimary = $this->dbData;

        if (is_array(static::$primaryDbPropertyKey)) {
            foreach (static::$primaryDbPropertyKey as $keyPart) {
                unset($dbDataWithoutPrimary[$keyPart]);
            }
        } else {
            unset($dbDataWithoutPrimary[static::$primaryDbPropertyKey]);
        }

        return $dbDataWithoutPrimary;
    }

    /**
     * @param bool $deleteFromDbOnSave
     */
    public function setDeleteFromDbOnSave($deleteFromDbOnSave = true)
    {
        $this->deleteFromDbOnSave = $deleteFromDbOnSave;
    }

    /**
     * @return bool
     */
    public function shouldBeDeletedFromDbOnSave()
    {
        return $this->deleteFromDbOnSave;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * @param bool $deleted
     */
    public function setDeleted($deleted = true)
    {
        $this->deleted = $deleted;
    }

    /**
     * @param bool $forceDbInsertOnSave
     */
    public function setForceDbInsertOnSave($forceDbInsertOnSave)
    {
        $this->forceDbInsertOnSave = $forceDbInsertOnSave;
    }

    /**
     * @return bool
     */
    public function shouldForceDbInsertOnSave()
    {
        return $this->forceDbInsertOnSave;
    }

    /**
     * @return array
     */
    public static function getDbProperties()
    {
        return static::$dbProperties;
    }

    /**
     * @param string $propertyName
     * @return int|null
     */
    public static function getDbPropertyMaxLength($propertyName)
    {
        return isset(static::$dbProperties[$propertyName]['maxLength'])
            ? static::$dbProperties[$propertyName]['maxLength']
            : null;
    }

    /**
     * @param string $propertyName
     * @return bool
     */
    public static function getDbPropertyRequired($propertyName)
    {
        return isset(static::$dbProperties[$propertyName]['required'])
            ? static::$dbProperties[$propertyName]['required']
            : false;
    }

    /**
     * @param string $propertyName
     * @return bool
     */
    public static function getDbPropertyNonEmpty($propertyName)
    {
        return isset(static::$dbProperties[$propertyName]['nonEmpty'])
            ? static::$dbProperties[$propertyName]['nonEmpty']
            : false;
    }

    /**
     * @return string|array
     */
    public static function getPrimaryDbPropertyKey()
    {
        return static::$primaryDbPropertyKey;
    }

    /**
     * @return string|array
     */
    public static function getPrimaryDbFieldKey()
    {
        $primaryDbPropertyKey = static::getPrimaryDbPropertyKey();

        if (is_array($primaryDbPropertyKey)) {
            $primaryDbFieldKey = [];
            foreach ($primaryDbPropertyKey as $propertyName) {
                $primaryDbFieldKey[] = static::getDbFieldName($propertyName);
            }

            return $primaryDbFieldKey;
        } else {
            return static::getDbFieldName($primaryDbPropertyKey);
        }
    }

    /**
     * Return array with db property names.
     *
     * @param array $exclude
     * @return array
     */
    public static function getDbPropertyNames(array $exclude = [])
    {
        $dbPropertyNames = array_keys(static::$dbProperties);

        return $exclude ? array_diff($dbPropertyNames, $exclude) : $dbPropertyNames;
    }

    /**
     * Return array with raw db field names.
     *
     * @param array $exclude
     * @return array
     */
    public static function getDbFieldNames(array $exclude = [])
    {
        $fieldNames = [];
        foreach (array_keys(static::$dbProperties) as $propertyName) {
            $fieldNames[] = static::getDbFieldName($propertyName);
        }

        return $exclude ? array_diff($fieldNames, $exclude) : $fieldNames;
    }


    /**
     * Get raw database field names prefixed (id, name becomes t.id, t.name etc.).
     *
     * @param string $dbTableAlias
     * @param array  $exclude
     * @return array
     */
    public static function getPrefixedDbFieldNames($dbTableAlias, array $exclude = [])
    {
        return Arr::valuesWithPrefix(static::getDbFieldNames($exclude), $dbTableAlias . '.');
    }

    /**
     * Get database columns transformed from e.g. "productId, date" to "p.product_id AS p_product_id, p.date AS p_date".
     *
     * @param string $dbTableAlias
     * @param array  $exclude
     * @return array
     */
    public static function getAliasedDbFieldNames($dbTableAlias, array $exclude = [])
    {
        $newArray = [];
        foreach (static::getDbFieldNames($exclude) as $dbFieldName) {
            $fromCol = $dbTableAlias . '.' . $dbFieldName;
            $toCol = $dbTableAlias . '_' . $dbFieldName;
            $newArray[] = $fromCol . ' AS ' . $toCol;
        }

        return $newArray;
    }

    /**
     * Filters a full db item array by it's table alias and the strips the table alias.
     *
     * @param array  $rowData
     * @param string $dbTableAlias
     * @param bool   $skipStrip For cases when you want to filter only (no stripping)
     * @return array
     */
    public static function filterStripDbRowData(array $rowData, $dbTableAlias, $skipStrip = false)
    {
        $columnPrefix = $dbTableAlias . '_';

        $filteredAndStrippedRowData = [];
        foreach ($rowData as $key => $val) {
            if (strpos($key, $columnPrefix) === 0) {
                $strippedKey = $skipStrip ? $key : Str::stripLeft($key, $columnPrefix);
                $filteredAndStrippedRowData[$strippedKey] = $val;
            }
        }

        return $filteredAndStrippedRowData;
    }

    /**
     * @return string
     */
    public static function getDbTableName()
    {
        return static::$dbTableName;
    }

    /**
     * Method to handle the serialization of this object.
     *
     * Implementation of Serializable interface. If descendant private properties
     * should be serialized, they need to be visible to this parent (i.e. not private).
     *
     * @return string
     */
    public function serialize()
    {
        return serialize(get_object_vars($this));
    }

    /**
     * Method to handle the unserialization of this object.
     *
     * Implementation of Serializable interface. If descendant private properties
     * should be unserialized, they need to be visible to this parent (i.e. not private).
     *
     * @param string $serializedObject
     */
    public function unserialize($serializedObject)
    {
        $objectVars = unserialize($serializedObject);

        foreach ($objectVars as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Merges other object's modified database data into this object.
     *
     * @param AbstractDbEntity $otherEntity
     */
    public function mergeWith(AbstractDbEntity $otherEntity)
    {
        $dataToMerge = $otherEntity->getModifiedDbData();
        $this->setDbData($dataToMerge);
    }
}
