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
 * Class for handling migration between different database versions.
 *
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
class Migrator
{
    /**
     * @const string
     */
    const DIRECTION_UP = 'up';

    /**
     * @const string
     */
    const DIRECTION_DOWN = 'down';

    /**
     * @var string
     */
    protected $migrationsTableName = 'migrations';

    /**
     * @var string
     */
    protected $migrationsDirectory;

    /**
     * @var Db
     */
    protected $db;

    /**
     * @var callable
     */
    protected $infoCallback;

    /**
     * @var AbstractMigration[]
     */
    protected $migrations;

    /**
     * @var int[]
     */
    protected $migratedNumbers;

    /**
     * @var bool
     */
    private $hasMigrationsTable;

    /**
     * @param string          $migrationsDirectory
     * @param Db              $db
     * @param callable        $infoCallback
     */
    public function __construct(
        $migrationsDirectory,
        Db $db,
        callable $infoCallback = null
    ) {
        $this->migrationsDirectory = $migrationsDirectory;
        $this->db = $db;
        $this->infoCallback = $infoCallback;
    }

    /**
     * @return \SplFileInfo[]
     */
    protected function findMigrationFiles()
    {
        $migrationFiles = [];
        $directoryIterator = new \FilesystemIterator($this->migrationsDirectory);
        foreach ($directoryIterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $migrationFiles[] = $fileInfo;
            }
        }

        return $migrationFiles;
    }

    /**
     * @return AbstractMigration[]
     */
    protected function loadMigrations()
    {
        $migrations = [];
        foreach ($this->findMigrationFiles() as $file) {
            require_once $file->getPathname();

            $className = '\\' . $file->getBasename('.' . $file->getExtension());
            $migration = new $className($this->db);

            $migrations[$migration->getNumber()] = $migration;
        }

        ksort($migrations);

        return $migrations;
    }

    /**
     * @return AbstractMigration[]
     */
    public function getMigrations()
    {
        if (!isset($this->migrations)) {
            $this->migrations = $this->loadMigrations();
        }

        return $this->migrations;
    }

    /**
     * @return int
     */
    public function getLatestNumber()
    {
        $numbers = array_keys($this->getMigrations());
        $latest = end($numbers);

        return ($latest !== false) ? $latest : 0;

    }

    /**
     * @return bool
     */
    private function hasMigrationsTable()
    {
        if (!isset($this->hasMigrationsTable)) {
            $this->hasMigrationsTable =
                (bool) $this->db->fetchValue('SHOW TABLES LIKE ?', [$this->migrationsTableName]);
        }

        return $this->hasMigrationsTable;
    }

    /**
     */
    protected function createMigrationsTable()
    {
        if (!$this->hasMigrationsTable()) {
            $this->db->exec('
                CREATE TABLE `' . $this->migrationsTableName . '` (
                    `migration_number` BIGINT NOT NULL,
                    `completed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`migration_number`)
                )
            ');
            $this->hasMigrationsTable = true;
        }
    }

    /**
     * @return array
     */
    protected function getMigratedNumbers()
    {
        if (!isset($this->migratedNumbers)) {
            $this->createMigrationsTable();

            $this->migratedNumbers = [];

            $sql = 'SELECT * FROM `' . $this->migrationsTableName . '` ORDER BY migration_number';
            foreach ($this->db->fetchRows($sql) as $row) {
                $this->migratedNumbers[] = $row['migration_number'];
            }
        }

        return $this->migratedNumbers;
    }

    /**
     * @return int
     */
    public function getCurrentNumber()
    {
        $migratedNumbers = $this->getMigratedNumbers();
        $current = end($migratedNumbers);

        return ($current !== false) ? $current : 0;
    }

    /**
     * @param int $toNumber
     * @return string
     */
    protected function getDirection($toNumber)
    {
        return ($this->getCurrentNumber() > $toNumber) ? self::DIRECTION_DOWN : self::DIRECTION_UP;
    }

    /**
     * @param int $to
     * @return AbstractMigration[]
     */
    public function getMigrationsTo($to = null)
    {
        $toNumber = $this->getToNumber($to);
        $allMigrations = $this->getMigrations();
        $direction = $this->getDirection($toNumber);
        if ($direction === self::DIRECTION_DOWN) {
            $allMigrations = array_reverse($allMigrations, true);
        }

        $migrations = [];
        foreach ($allMigrations as $migrationNumber => $migration) {
            if ($this->shouldMigrationBeMigrated($migration, $toNumber, $direction)) {
                $migrations[$migrationNumber] = $migration;
            }
        }

        return $migrations;
    }

    /**
     * @param AbstractMigration $migration
     * @param int               $toNumber
     * @param string            $direction
     * @return bool
     */
    private function shouldMigrationBeMigrated(AbstractMigration $migration, $toNumber, $direction)
    {
        if (($direction === self::DIRECTION_UP
                && $migration->getNumber() <= $toNumber
                && !in_array($migration->getNumber(), $this->getMigratedNumbers()))
            || ($direction == self::DIRECTION_DOWN
                && $migration->getNumber() > $toNumber
                && in_array($migration->getNumber(), $this->getMigratedNumbers()))
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param AbstractMigration $migration
     */
    protected function addMigratedMigration(AbstractMigration $migration)
    {
        $this->createMigrationsTable();

        $this->db->exec(
            'INSERT INTO `' . $this->migrationsTableName . '` SET migration_number = ?',
            [$migration->getNumber()]
        );

        $this->migratedNumbers[] = $migration->getNumber();
    }

    /**
     * @param AbstractMigration $migration
     */
    protected function deleteMigratedMigration(AbstractMigration $migration)
    {
        $this->createMigrationsTable();

        $this->db->exec(
            'DELETE FROM `' . $this->migrationsTableName . '` WHERE migration_number = ?',
            [$migration->getNumber()]
        );

        if (($key = array_search($migration->getNumber(), $this->migratedNumbers)) !== false) {
            unset($this->migratedNumbers[$key]);
        }
    }

    /**
     * Empty database.
     */
    public function emptyDb()
    {
        if (($rows = $this->db->fetchRows('SHOW TABLES', [], true))) {
            $this->db->exec('SET foreign_key_checks = 0');
            foreach ($rows as $row) {
                $this->db->exec('DROP TABLE `' . $row[0] . '`');
            }
            $this->db->exec('SET foreign_key_checks = 1');
        }
    }

    /**
     * @param int|null $to
     * @return int
     * @throws \InvalidArgumentException
     */
    protected function getToNumber($to = null)
    {
        if ($to === null) {
            return $this->getLatestNumber();
        } elseif (!preg_match('/^\d+$/', $to)) {
            throw new \InvalidArgumentException('Migration number must be a number');
        }

        $toNumber = (int) $to;

        if (!in_array($toNumber, array_keys($this->getMigrations()))) {
             throw new \InvalidArgumentException('Invalid migration number');
        }

        return $toNumber;
    }

    /**
     * @param int|null $to A migration number to migrate to (if not provided, latest migration number will be used)
     * @return bool Returns true if any action was performed
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function migrate($to = null)
    {
        if ($this->getCurrentNumber() > $this->getLatestNumber()) {
            throw new \RuntimeException(sprintf(
                'The current migration number (%d) is higher than latest available (%d). Something is wrong!',
                $this->getCurrentNumber(),
                $this->getLatestNumber()
            ));
        }

        $toNumber = $this->getToNumber($to);
        $migrations = $this->getMigrationsTo($toNumber);

        // If there's no migration to be done, we are up to date.
        if (empty($migrations)) {
            $this->addInfo(sprintf(
                'No migrations available, things are up to date (migration %d)!',
                $this->getCurrentNumber()
            ));

            return false;
        }

        $this->addInfo(sprintf(
            'Running %d migrations from migration %d to %d...',
            count($migrations),
            $this->getCurrentNumber(),
            $toNumber
        ));

        $this->runMigrations($migrations, $this->getDirection($toNumber));

        $this->addInfo(sprintf('Done! %s migrations migrated!', count($migrations)));

        return true;
    }

    /**
     * @param AbstractMigration[] $migrations
     * @param string $direction
     */
    protected function runMigrations(array $migrations, $direction)
    {
        foreach ($migrations as $migration) {
            if ($direction === self::DIRECTION_UP) {
                $this->addInfo(sprintf(' - Migrating up %d...', $migration->getNumber()));
                $migration->up();
                $this->addMigratedMigration($migration);

            } else {
                $this->addInfo(sprintf(' - Migrating down %d...', $migration->getNumber()));
                $migration->down();
                $this->deleteMigratedMigration($migration);

            }
        }
    }

    /**
     * @param string $info
     */
    protected function addInfo($info)
    {
        if ($this->infoCallback) {
            $callback = $this->infoCallback;
            $callback($info);
        }
    }
}
