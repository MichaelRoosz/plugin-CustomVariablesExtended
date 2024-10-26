<?php

namespace Piwik\Plugins\CustomVariablesExtended\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class LogTableVisit
{
    public const TABLE_NAME = 'log_custom_variable_visit';

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

    public function insertCustomVariable($idSite, $idVisit, $index, $name, $value)
    {
        $this->getDb()->query(
            'INSERT INTO ' . $this->tableNamePrefixed
                . ' (`idvisit`, `index`,  `idsite`, `name`, `value`)'
                . ' VALUES (?,?,?,?,?)'
                . ' ON DUPLICATE KEY UPDATE '
                . ' `idsite` = ?,'
                . ' `name` = ?,'
                . ' `value` = ?',
            [
                $idVisit,
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

    public function getCustomVariablesForVisit($idVisit)
    {
        $sql = 'SELECT * FROM ' . $this->tableNamePrefixed . ' WHERE idvisit = ?';

        $raw = $this->getDb()->fetchAll($sql, [$idVisit]);

        $cvars = [];
        foreach ($raw as $row) {
            $cvars['custom_var_k' . $row['index']] = [
                'name' => $row['name'],
                'value' => $row['value'],
            ];
        }

        return $cvars;
    }

    public function install()
    {
        $table = "`idvisit` BIGINT UNSIGNED NOT NULL,
                  `index` SMALLINT UNSIGNED NOT NULL,
                  `idsite` INT UNSIGNED NOT NULL,
                  `name` VARCHAR(" . CustomVariablesExtended::MAX_LENGTH_VARIABLE_NAME . ") DEFAULT NULL,
                  `value` VARCHAR(" . CustomVariablesExtended::MAX_LENGTH_VARIABLE_VALUE . ") DEFAULT NULL,
                  PRIMARY KEY (`idvisit`, `index`),
                  KEY (`idsite`, `index`, `name`, `idvisit`)";

        DbHelper::createTable($this->tableName, $table);
    }

    public function uninstall()
    {
        Db::dropTables(array($this->tableNamePrefixed));
    }
}
