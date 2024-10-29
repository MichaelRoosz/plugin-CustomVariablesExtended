<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class LogTableLinkVisitAction {
    public const TABLE_NAME = 'log_custom_variable_link_va';

    /** @var string $tableName */
    private $tableName = self::TABLE_NAME;

    /** @var string $tableNamePrefixed */
    private $tableNamePrefixed;

    public function __construct() {
        $this->tableNamePrefixed = Common::prefixTable($this->tableName);
    }

    public function insertCustomVariable(int $idSite, int $idVisit, int $idLinkVisitAction, int $index, string $name, string $value): void {
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

    public function install(): void {
        $table = '`idlink_va` BIGINT UNSIGNED NOT NULL,
                  `index` SMALLINT UNSIGNED NOT NULL,
                  `idsite` INT UNSIGNED NOT NULL,
                  `idvisit` BIGINT UNSIGNED NOT NULL,
                  `name` VARCHAR(' . CustomVariablesExtended::MAX_LENGTH_VARIABLE_NAME . ') DEFAULT NULL,
                  `value` VARCHAR(' . CustomVariablesExtended::MAX_LENGTH_VARIABLE_VALUE . ') DEFAULT NULL,
                  PRIMARY KEY (`idlink_va`, `index`),
                  KEY (`idvisit`),
                  KEY (`idsite`, `index`, `name`, `idvisit`, `idlink_va`)';

        DbHelper::createTable($this->tableName, $table);
    }

    public function uninstall(): void {
        Db::dropTables([$this->tableNamePrefixed]);
    }

    /**
     * @return \Piwik\Tracker\Db|\Piwik\Db
     */
    private function getDb() {
        /** @phpstan-ignore return.type */
        return Db::get();
    }
}
