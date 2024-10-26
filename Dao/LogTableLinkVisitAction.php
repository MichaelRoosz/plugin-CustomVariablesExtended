<?php

namespace Piwik\Plugins\CustomVariablesExtended\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class LogTableLinkVisitAction
{
    public const TABLE_NAME = 'log_custom_variable_link_va';

    private $tableName = self::TABLE_NAME;
    private $tableNamePrefixed;

    public function __construct()
    {
        $this->tableNamePrefixed = Common::prefixTable($this->tableName);
    }

    private function getDb()
    {
        return Db::get();
    }

    public function insertCustomVariable($idSite, $idVisit, $idLinkVisitAction, $index, $name, $value)
    {
        $this->getDb()->query(
            'INSERT INTO ' . $this->tableNamePrefixed
                . ' (`idlink_va`, `index`, `idsite`, `idvisit`, `name`, `value`)'
                . ' VALUES (?,?,?,?,?,?)'
                . ' ON DUPLICATE KEY UPDATE '
                . ' `idsite` = ?,'
                . ' `idvisit` = ?,'
                . ' `name` = ?,'
                . ' `value` = ?',
            [
                $idLinkVisitAction,
                $index,
                $idSite,
                $idVisit,
                $name,
                $value,
                $idSite,
                $idVisit,
                $name,
                $value,
            ]
        );
    }

    public function install()
    {
        $table = "`idlink_va` BIGINT UNSIGNED NOT NULL,
                  `index` SMALLINT UNSIGNED NOT NULL,
                  `idsite` INT UNSIGNED NOT NULL,
                  `idvisit` BIGINT UNSIGNED NOT NULL,
                  `name` VARCHAR(" . CustomVariablesExtended::MAX_LENGTH_VARIABLE_NAME . ") DEFAULT NULL,
                  `value` VARCHAR(" . CustomVariablesExtended::MAX_LENGTH_VARIABLE_VALUE . ") DEFAULT NULL,
                  PRIMARY KEY (`idlink_va`, `index`),
                  KEY (`idvisit`),
                  KEY (`idsite`, `index`, `name`, `idvisit`, `idlink_va`)";

        DbHelper::createTable($this->tableName, $table);
    }

    public function uninstall()
    {
        Db::dropTables(array($this->tableNamePrefixed));
    }
}
