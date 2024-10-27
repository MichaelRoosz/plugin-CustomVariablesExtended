<?php

namespace Piwik\Plugins\CustomVariablesExtended\Dao;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class LogTableConversion {
    public const TABLE_NAME = 'log_custom_variable_conversion';

    /** @var string $tableName */
    private $tableName = self::TABLE_NAME;

    /** @var string $tableNamePrefixed */
    private $tableNamePrefixed;

    public function __construct() {
        $this->tableNamePrefixed = Common::prefixTable($this->tableName);
    }

    public function insertCustomVariable(
        int $idSite,
        int $idVisit,
        int $idGoal,
        int $buster,
        string $scope,
        int $index,
        string $name,
        string $value
    ): void {
        $scopeId = CustomVariablesExtended::scopeNameToId($scope);

        $this->getDb()->query(
            'INSERT INTO ' . $this->tableNamePrefixed
                . ' (`idvisit`, `idgoal`, `buster`, `scope`, `index`, `idsite`, `name`, `value`)'
                . ' VALUES (?,?,?,?,?,?,?,?)'
                . ' ON DUPLICATE KEY UPDATE '
                . ' `idsite` = ?,'
                . ' `name` = ?,'
                . ' `value` = ?',
            [
                $idVisit,
                $idGoal,
                $buster,
                $scopeId,
                $index,
                $idSite,
                $name,
                $value,
                $idSite,
                $name,
                $value,
            ]
        );
    }

    public function install(): void {
        $table = '`idvisit` BIGINT UNSIGNED NOT NULL,
                  `idgoal` INT UNSIGNED NOT NULL,
                  `buster` INT UNSIGNED NOT NULL,
                  `scope` SMALLINT UNSIGNED NOT NULL,
                  `index` SMALLINT UNSIGNED NOT NULL,
                  `idsite` INT UNSIGNED NOT NULL,
                  `name` VARCHAR(' . CustomVariablesExtended::MAX_LENGTH_VARIABLE_NAME . ') DEFAULT NULL,
                  `value` VARCHAR(' . CustomVariablesExtended::MAX_LENGTH_VARIABLE_VALUE . ') DEFAULT NULL,
                  PRIMARY KEY (`idvisit`, `idgoal`, `buster`, `scope`, `index`),
                  KEY (`idsite`, `index`, `name`, `scope`, `idgoal`, `idvisit`)';

        DbHelper::createTable($this->tableName, $table);
    }

    public function uninstall(): void {
        Db::dropTables([$this->tableNamePrefixed]);
    }

    /**
     * @return \Piwik\Tracker\Db|\Piwik\Db
     */
    private function getDb() {
        $db = Db::get();

        if ($db instanceof \Piwik\Tracker\Db) {
            return $db;
        }

        if ($db instanceof \Piwik\Db) {
            return $db;
        }

        throw new \Exception('Unsupported database type');
    }
}
