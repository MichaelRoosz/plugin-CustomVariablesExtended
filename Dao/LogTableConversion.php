<?php

namespace Piwik\Plugins\CustomVariablesExtended\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class LogTableConversion
{
    private $tableName = 'log_custom_variable_conversion';
    private $tableNamePrefixed;

    public function __construct()
    {
        $this->tableNamePrefixed = Common::prefixTable($this->tableName);
    }

    private function getDb()
    {
        return Db::get();
    }

    public function insertCustomVariable($idVisit, $idGoal, $idSite, $index, $name, $value)
    {
        $this->getDb()->query(
            'INSERT INTO ' . $this->tableNamePrefixed
                . ' (`idvisit`, `idgoal`, `idsite`, `index`, `name`, `value`)'
                . ' VALUES (?,?,?,?,?,?)'
                . ' ON DUPLICATE KEY UPDATE '
                . ' `value` = ?',
            [
                $idVisit,
                $idGoal,
                $idSite,
                $index,
                $name,
                $value,
                $value,
            ]
        );
    }

    public function install()
    {
        $table = "`idvisit` BIGINT UNSIGNED NOT NULL,
                  `idgoal` INT UNSIGNED NOT NULL,
                  `idsite` INT UNSIGNED NOT NULL,
                  `index` SMALLINT UNSIGNED NOT NULL,
                  `name` VARCHAR(" . CustomVariablesExtended::MAX_LENGTH_VARIABLE_NAME . ") DEFAULT NULL,
                  `value` VARCHAR(" . CustomVariablesExtended::MAX_LENGTH_VARIABLE_VALUE . ") DEFAULT NULL,
                  PRIMARY KEY (`idvisit`, `idgoal`, `index`),
                  KEY (`idsite`, `index`, `name`)";

        DbHelper::createTable($this->tableName, $table);
    }

    public function uninstall()
    {
        Db::dropTables(array($this->tableNamePrefixed));
    }
}
